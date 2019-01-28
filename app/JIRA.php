<?php

namespace App;

//use App\Helpers\Contracts\JIRAContract;
//use Illuminate\Foundation\Bus\DispatchesJobs;
use Cache;
use Storage;
use Log;
//use App\OEM;
// use App\Expand;
//use App\MediaCache;
//use App\Classes\UTF8Class;
//use Redis;

// JIRA helper (based on cdash JIRA API)
class JIRA //implements JIRAContract
{

    //private $username = "mdavis";
    //private $password = "D0ct0rD00m";

    public function __construct()
    {
        $this->cacheminutes = config("app.cacheminutes");
        $this->cachehours = config("app.cachehours");

        //Cache::remember('jira_issues', $this->cacheminutes, function () {
        //    return $this->searchAllIssues();
        //});

    }

    // make public for now
    private function CallJiraAPI($method, $url, $data = NULL, $token = NULL, $check_login = FALSE, $makeDirectory = FALSE)
    {
        $api_url = env('JIRA_URL', "https://secure.abnetwork.com/Jira");
        Log::info("JIRA API Call Made: \"" . $method . "\", \"" . $url . "\"");

        $url = $api_url . $url;

        $content = json_encode($data);

        Log::info("JIRA API Call Data Sent: " . print_r($data, true));

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $curl_headers = array();
        $curl_headers[] = "Content-type: application/json";

        if (!is_null($token)) {
            $curl_headers[] = "cookie: JSESSIONID=" . $token;
        }

        curl_setopt($curl, CURLOPT_HTTPHEADER, $curl_headers);

        if ($method == "POST") {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $content);
        }
        if ($method == "PUT") {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
            curl_setopt($curl, CURLOPT_POSTFIELDS, $content);
        }
        if ($method == "DELETE") {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "DELETE");
        }

        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

        $json_response = curl_exec($curl);

        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($check_login && ($status == 400 || $status == 401)) {
            $this->check_login();
        } elseif ($status != 201 && $status != 200 && $status != 203 && $status != 204) {
            $response = json_encode(array("error" => "ERROR", "result" => json_decode($json_response, true)));
        } else {
            $response = json_encode(array("status" => $status, "result" => json_decode($json_response, true)));
        }

        curl_close($curl);
        $response = json_decode($json_response, true);
        return $response;
    }

    private function check_login()
    {
        $this->token = $this->getToken();
        $result = $this->CallJiraAPI("GET", "/rest/auth/1/session", '', $this->token);
    }

    private function getToken()
    {
        $result = $this->CallJiraAPI("POST", "/rest/auth/1/session", array("username" => env('JIRA_USERNAME'), "password" => env('JIRA_PASSWORD')));
        $jiraToken = $result["session"]['value'] ?? '';
        return $jiraToken;
    }

    public function getSingleIssue($issue = null)
    {
        $this->check_login();
        // custom field for Player name customfield_10101
        $response = $this->CallJiraAPI("GET", "/rest/api/2/issue/" . $issue, "", $this->token);
        return $response;
    }

    public function searchAllIssues()
    {
        $this->check_login();

        // custom field for Player name customfield_10101
        // custom field for customer id customfield_10102

        // search for all statuses
        $search = "?jql=project=CS%20&maxResults=2000";

        $data = array();
        $response = $this->CallJiraAPI("GET", "/rest/api/2/search" . $search, "", $this->token);

        foreach ($response['issues'] AS $key => $issue) {
            $data[$key]['JIRA_ID'] = $issue['key'];
            $data[$key]['customer_id'] = isset($issue['fields']['customfield_10102']) ? $issue['fields']['customfield_10102'] : "";
            $data[$key]['customer_name'] = isset($issue['fields']['customfield_10003']) ? $issue['fields']['customfield_10003'] : "";
            $data[$key]['description'] = isset($issue['fields']['description']) ? $issue['fields']['description'] : "";
            $data[$key]['summary'] = $issue['fields']['summary'];
        }

        return $data;

    }

    /*public function getCustomersNewInstall()
    {
        $this->check_login();

        // custom field for Player name customfield_10101
        // custom field for customer id customfield_10102

        // search for all statuses
        $search = '?jql=project%20=%20INST%20and%20type%20=%20"New%20Install"%20AND%20status%20NOT%20IN%20("Stage%206","Stage%207","Done","Closed")&maxResults=1000';

        $data = array();
        $response = $this->CallJiraAPI("GET", "/rest/api/2/search" . $search, "", $this->token);

        $new_list = [];

        if ( isset($response['issues'])) {
            // collect the customer_id that are offline first
            foreach ($response['issues'] AS $key => $issue) {
                if (isset($issue['fields']['customfield_10102'])) {
                    $new_list[] = $issue['fields']['customfield_10102'];
                }
            }

            // delete the new_install_list
            $device_list = Redis::keys('new_install_list:*');

            foreach ($device_list AS $key => $device) {
                Redis::del($device);
            }

            //  then readd the list
            foreach ($new_list AS $customer_id) {
                Redis::hMset('new_install_list:' . $customer_id, [
                    'new_install' => true]);
            }

            $device_list = Redis::keys('new_install_list:*');

            foreach ($device_list AS $key => $device) {
                $create_new[] = $device;
            }
        } else {
            // delete all new_install_list
            $device_list = Redis::keys('new_install_list:*');

            foreach ($device_list AS $key => $device) {
                Redis::del($device);
            }
        }

    }*/


    public function searchAllIssuesUnresolved()
    {
        $this->check_login();

        // custom field for Player name customfield_10101
        // custom field for customer id customfield_10102

        // search for all statuses
        $search = "?jql=project=ITSW%20&maxResults=2";

        $data = array();
        $response = $this->CallJiraAPI("GET", "/rest/api/2/search" . $search, "", $this->token);
        //print_r($this->token);die("!");

        /* foreach ($response['issues'] AS $key => $issue) {
            $data[$key]['JIRA_ID'] = $issue['key'];
            $data[$key]['customer_id'] = isset($issue['fields']['customfield_10102']) ? $issue['fields']['customfield_10102'] : "";
            $data[$key]['customer_name'] = isset($issue['fields']['customfield_10003']) ? $issue['fields']['customfield_10003'] : "";
            $data[$key]['description'] = isset($issue['fields']['description']) ? $issue['fields']['description'] : "";
            $data[$key]['summary'] = $issue['fields']['summary'];
        } */

        return $response;
    }

    public function createIssue()
    {
        $this->check_login();

        $data = array();
        $data['fields']['project']['key'] = 'ITSW';
        $data['fields']['summary'] = 'THIS IS A TEST';
        $data['fields']['description'] = 'TEST ISSUE CREATION USING JIRA REST API -CB';
        $data['fields']['issuetype']['name'] = 'Bug';
        die(json_encode($data));

        $response = $this->CallJiraAPI("POST", "/rest/api/2/issue/", $data, $this->token);

        return $response;
    }

    public function getJiraInfo($customer_id = null)
    {
        $this->cacheminutes = config('app.cacheminutes');

        if (Cache::has('jira_issues')) {
            $issues = Cache::get('jira_issues');
        } else {
            $issues = Cache::remember('jira_issues', $this->cacheminutes, function () {
                return $this->searchAllIssues();
            });
        }

        $data = array();
        foreach ($issues as $key => $issue) {
            if ($issue['customer_id'] == $customer_id) {
                $data[$key] = $issue;
            }
        }

        return $data;

    }

}

?>
