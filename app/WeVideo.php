<?php

namespace App;

use Cache;
use Illuminate\Database\Eloquent\Model;
use Log;

// WeVideo helper
// https://www.wevideo.com
class WeVideo extends Model
{
    // Table Name
    //protected $table = 'oem_codes';
    // Primary Key
    //public $primaryKey = 'code';

    //private $pagelimit = 20;
    private $cacheminutes = 1440;
    private $cachehours = 24;
    private $token = '';

    /*public function __construct()
    {
    $this->cacheMinutes = 60;
    $this->cookieJar = Cache::remember('wevideocookie', $this->cacheMinutes, function () {
    return ''; //tempnam('/tmp', 'wevideocookie');
    });
    $this->token = Cache::remember('wevideotoken', $this->cacheMinutes, function () {
    return ''; //return $this->getSSOAuth();
    });
    }

    public function getSSOAuth() {
    // Check to see if user is already logged in with this token
    $result = $this->CallWeVideoAPI("GET", "api/2/wevideouser/info/", "", $this->token, false);
    //print_r($result);//die();
    // Token missing or expired, so log in and store new token
    if (isset($result['success']) && !$result['success']) {
    $server = env('WEVIDEO_SERVER', 'www');
    $key = env('WEVIDEO_KEY', 'fvoFkqX2WtDkYmTUI9Cw3nJaBnoka2TVXV9THfvg');
    $postdata = array('version' => '1', 'content' => $xmlFinal, 'resolution' => $resolution, 'crf' => '20', 'fps' => '29.97', 'thumbnailTime' => $thumbnailTime);
    $postdata = json_encode($postdata, false);
    //echo $postdata;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://$server.wevideo.com/api/3/videos/create");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    "Authorization: WEVSIMPLE $key",
    "Content-Type: application/json"
    ));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    //curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $output = curl_exec($ch);
    curl_close($ch);

    $output = json_decode($output, 1);
    //$output['endTime'] = $this->endTime;
    $output['templateDuration'] = $templateDuration;
    $output = json_encode($output, 1);

    return $output;

    $result = $this->CallWeVideoAPI("GET", "login", array("action" => "authenticate", "ajform" => "1", "userName" => "abn.production@abnetwork.com", "password" => "Junction23"));
    $this->token = $result["token"];
    Cache::put('wevideotoken', $this->token, $this->cacheminutes);
    //echo "API Token Received: ".$this->token;
    //log_message("error","API Token Received: ".$this->token);
    }
    }*/

    public function __construct()
    {
        $this->token = Cache::remember('wevideotoken', $this->cacheminutes, function () {
            return '';
        });
        //$this->token = Cache::remember('wevideotoken', $this->cacheminutes, function () {
        //  return $this->getToken();
        //});
        //echo "cached token: " . $this->token . '<br>';
        //$this->login();
    }

    private function CallWeVideoAPI($method, $url, $data = null, $token = null, $check_login = true, $body = null)
    {
        $api_url = 'https://www.wevideo.com/'; //env('WEVIDEO_SERVER', "http://10.1.9.145/rest/v10/");
        Log::info('WeVideo API Call Made: "'.$method.'", "'.$url.'"');
        //echo "WeVideo API Call Made: \"" . $method . "\", \"" . $url . "\"";

        $urlOrg = $url;
        $url = $api_url.$url;

        if ($method == 'GET' && $data) {
            $url = $url.'?'.http_build_query($data);
        }

        $content = json_encode($data);
        if ($body) {
            $content = $body;
        }

        Log::info('WeVideo API Call Data Sent: '.print_r($data, true));
        //echo $url;

        $cookie_jar = tempnam('/tmp', 'wevideocookie');

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLINFO_HEADER_OUT, true);
        if (substr($urlOrg, 0, 5) == 'login') {
            curl_setopt($curl, CURLOPT_COOKIEJAR, $cookie_jar);
        } else {
            //curl_setopt($curl, CURLOPT_COOKIEFILE, $cookie_jar);
        }

        $curl_headers = array();
        $curl_headers[] = 'Content-Type: application/json';

        if (!is_null($this->token)) {
            //  $curl_headers[] = "OAuth-Token:" . $token;
            //echo "APPLYING ".$this->token;
            curl_setopt($curl, CURLOPT_COOKIE, 'JSESSIONID='.$this->token.';');
            //curl_setopt($curl, CURLOPT_COOKIE, "wevideo=7391774767506019554; AWSALB=TXfWCnt1txfkVnLVr1rS5SbOoxkI1DJ0axkutGz6BEb1sAyxDw3DPjwitv1+Q51R5+2ypAzlmwbhqpN3DQSU0bFrJ74453xw0j4oNNyWvfUWiE/6+pbcb1w8ymka; JSESSIONID=5CD166E28A2D0C9921A4EEB4E26EAB7E.prod02");
        }

        $key = env('WEVIDEO_KEY', 'fvoFkqX2WtDkYmTUI9Cw3nJaBnoka2TVXV9THfvg');

        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Authorization: WEVSIMPLE '.$key,
            'Content-Type: application/json'
        ));

        //curl_setopt($curl, CURLOPT_HTTPHEADER, $curl_headers);
        //curl_setopt($curl, CURLOPT_HEADER ,true);

        if ($method == 'POST') {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $content);
        }
        if ($method == 'PUT') {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($curl, CURLOPT_POSTFIELDS, $content);
        }
        if ($method == 'DELETE') {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        //curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        //curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        //curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);

        $json_response = curl_exec($curl);
        //echo curl_getinfo($curl,CURLINFO_EFFECTIVE_URL );
        //echo $json_response;
        Log::info($json_response);

        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($check_login && ($status == 400 || $status == 401)) {
            $this->login();
        } elseif ($status != 201 && $status != 200 && $status != 203 && $status != 204) {
            $response = json_encode(array('error' => 'ERROR', 'result' => json_decode($json_response, true)));
            //die("Error: call to URL $url failed with status $status, response $json_response, curl_error " . curl_error($curl) . ", curl_errno " . curl_errno($curl)."<br />".print_r($content,true)." - ".$token." - ".$url);
        } else {
            $response = json_encode(array('status' => $status, 'result' => json_decode($json_response, true)));
        }

        //print_r( curl_getinfo($curl));
        curl_close($curl);

        if (substr($urlOrg, 0, 5) == 'login') {
            $cookie_str = file_get_contents($cookie_jar);
            if ($cookie_str) {
                $cookie_arr = $this->extractCookies($cookie_str);
                foreach ($cookie_arr as $k => $v) {
                    if ($v['name'] == 'JSESSIONID') {
                        $token = $v['value'];
                    }
                }
                //echo $token;
                $returnArr = array();
                $returnArr['token'] = $token;
                return $returnArr;
            }
        }

        $response = json_decode($json_response, true);
        Log::info('WeVideo API Call Data Received: '.print_r($response, true));

        return $response;
    }

    // Login to WeVideo
    public function login()
    {
        // Check to see if user is already logged in with this token
        $result = $this->CallWeVideoAPI('GET', 'api/2/wevideouser/info/', '', $this->token, false);
        //print_r($result);//die();
        // Token missing or expired, so log in and store new token
        if (isset($result['success']) && !$result['success']) {
            $result = $this->CallWeVideoAPI('GET', 'login', array('action' => 'authenticate', 'ajform' => '1', 'userName' => 'abn.production@abnetwork.com', 'password' => 'Junction23'));
            $this->token = $result['token'];
            Cache::put('wevideotoken', $this->token, $this->cacheminutes);
            //echo "API Token Received: ".$this->token;
            //log_message("error","API Token Received: ".$this->token);
        }
    }

    // Login to WeVideo and get token
    /*private function getToken()
    {
    //$result = $this->CallWeVideoAPI("POST", "/oauth2/token", array("grant_type" => "password", "client_id" => "sugar", "client_secret" => "", "username" => "lmarlette", "password" => "Y9p10N", "platform" => "base"));
    $result = $this->login();
    $wevideoToken = $result["access_token"];
    return $wevideoToken;
    }*/

    // Check for login
    /*private function check_login()
    {
    $this->token = Cache::remember('wevideotoken', $this->cacheminutes, function () {
    return $this->getToken();
    });
    $result = $this->CallWeVideoAPI("GET", "channels", "", $this->token, TRUE);
    //print_r($result);die();
    }*/

    // Check if WeVideo active
    /*public function checkWeVideoActive(){
    $result = $this->check_login();
    return $result;
    }*/

    // Get media asset list
    public function get_media($folder = null)
    {
        //$this->check_login();
        //$this->login();
        $result = $this->CallWeVideoAPI('GET', 'api/3/media/'.$folder.'/files', '', $this->token, false);
        if (isset($result['metadata']) && $result['metadata']['noResults'] > 0) {
            return $result;
        } else {
            // Nothing found, use search method instead
            $body = '{"query": {"bool": {"must": [{"terms": {"mediaType": ["upload","export"]}}],"must_not": [],"should": [{"term": {"parentFolderId": "'.$folder.'"}},{"terms": {"sharedFolders": ["'.$folder.'"]}}],"minimum_should_match": 1}},"from": 0,"size": 120}';
            $result = $this->CallWeVideoAPI('POST', 'api/3/media/search', '', $this->token, false, $body);
            if (isset($result['metadata']) && $result['metadata']['noResults'] > 0) {
                return $result;
            } else {
                $result['metadata']['noResults'] = 0; //'{"data": [],"metadata":{"noResults": 0}}';
                return $result;
            }
        }

        //return $channels;
    }

    private function extractCookies($string)
    {
        $lines = explode(PHP_EOL, $string);
        foreach ($lines as $line) {
            $cookie = array();

            // detect httponly cookies and remove #HttpOnly prefix
            if (substr($line, 0, 10) == '#HttpOnly_') {
                $line = substr($line, 10);
                $cookie['httponly'] = true;
            } else {
                $cookie['httponly'] = false;
            }

            // we only care for valid cookie def lines
            if (!empty($line) && $line[0] != '#' && substr_count($line, "\t") == 6) {

                // get tokens in an array
                $tokens = explode("\t", $line);

                // trim the tokens
                $tokens = array_map('trim', $tokens);

                // Extract the data
                $cookie['domain'] = $tokens[0]; // The domain that created AND can read the variable.
                $cookie['flag'] = $tokens[1]; // A TRUE/FALSE value indicating if all machines within a given domain can access the variable.
                $cookie['path'] = $tokens[2]; // The path within the domain that the variable is valid for.
                $cookie['secure'] = $tokens[3]; // A TRUE/FALSE value indicating if a secure connection with the domain is needed to access the variable.

                $cookie['expiration-epoch'] = $tokens[4]; // The UNIX time that the variable will expire on.
                $cookie['name'] = urldecode($tokens[5]); // The name of the variable.
                $cookie['value'] = urldecode($tokens[6]); // The value of the variable.

                // Convert date to a readable format
                $cookie['expiration'] = date('Y-m-d h:i:s', $tokens[4]);

                // Record the cookie.
                $cookies[] = $cookie;
            }
        }

        return $cookies;
    }
}
