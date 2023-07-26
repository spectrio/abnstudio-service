<?php

namespace App;

use Cache;
use Log;
use Illuminate\Database\Eloquent\Model;
use DB;

// SugarCRM (Expand) helper
// http://10.1.9.145/rest/v10/help
class Sugar extends Model
{
  // Table Name
  protected $table = 'oem_codes';
  // Primary Key
  public $primaryKey = 'code';

  private $pagelimit = 20;
  private $cacheminutes = 1440;
  private $cachehours = 24;
  private $token = '';

  public function __construct()
  {
    $this->token = Cache::remember('sugartoken', $this->cacheminutes, function () {
      return $this->getToken();
    });
    //echo "cached token: " . $this->token . '<br>';
    $this->login();
  }

  private function CallSugarAPI($method, $url, $data = NULL, $token = NULL, $check_login = TRUE)
  {
    $api_url = env('SUGAR_URL', "http://10.1.9.145/rest/v10/");
    Log::info("Sugar API Call Made: \"" . $method . "\", \"" . $url . "\"");

    $url = $api_url . $url;
    $content = json_encode($data);

    Log::info("Sugar API Call Data Sent: " . print_r($data, true));

    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_HEADER, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    $curl_headers = array();
    $curl_headers[] = "Content-type: application/json";
    if (!is_null($token)) {
      $curl_headers[] = "OAuth-Token:" . $token;
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

    //curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

    $json_response = curl_exec($curl);

    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    if ($check_login && ($status == 400 || $status == 401)) {
      $this->login();
    } elseif ($status != 201 && $status != 200 && $status != 203 && $status != 204) {
      $response = json_encode(array("error" => "ERROR", "result" => json_decode($json_response, true)));
      //die("Error: call to URL $url failed with status $status, response $json_response, curl_error " . curl_error($curl) . ", curl_errno " . curl_errno($curl)."<br />".print_r($content,true)." - ".$token." - ".$url);
    } else {
      $response = json_encode(array("status" => $status, "result" => json_decode($json_response, true)));
    }
    curl_close($curl);

    $response = json_decode($json_response, true);
    /*if (strpos($url, '/api/rest/players') === false) {
      Log::info("Sugar API Call Data Received: " . print_r($response, true));
    }*/

    return $response;
  }

  // Login to Sugar
  private function login()
  {
    // Check to see if user is already logged in with this token
    $result = $this->CallSugarAPI("GET", "me", "", $this->token, false);
    //print_r($result);die();
    // Token missing or expired, so log in and store new token
    if(isset($result['error'])) {
      $result = $this->CallSugarAPI("POST", "/oauth2/token", array("grant_type" => "password", "client_id" => "sugar", "client_secret" => "", "username" => env('SUGAR_USERNAME'), "password" => env('SUGAR_PASSWORD'), "platform" => "base"));
      $this->token = $result["access_token"];
      Cache::put('sugartoken', $this->token, $this->cacheminutes);
      //echo "API Token Received: ".$this->token;
      //log_message("error","API Token Received: ".$this->token);
    }

  }

  // Login to Sugar and get token
  private function getToken()
  {
    //$result = $this->CallSugarAPI("POST", "/oauth2/token", array("grant_type" => "password", "client_id" => "sugar", "client_secret" => "", "username" => "lmarlette", "password" => "Y9p10N", "platform" => "base"));
    $result = $this->login();
    $sugarToken = $result["access_token"];
    return $sugarToken;
  }

  // Check for login
  private function check_login()
  {
    $this->token = Cache::remember('sugartoken', $this->cacheminutes, function () {
      return $this->getToken();
    });
    $result = $this->CallSugarAPI("GET", "channels", "", $this->token, TRUE);
    //print_r($result);die();
  }

  // Check if Sugar active
  public function checkSugarActive(){
    $result = $this->check_login();
    return $result;
  }

  // Get list of channels/playlists by account number
  public function get_channels_by_account($account = NULL) {
      //$this->check_login();
      $filters = "filter[0][account_number_c]=$account";
      $fields = "name,accounts_nets_channels,description,deleted,screenorientation_c,harrisplayerid_c,account_number_c";
      $channels = $this->CallSugarAPI("GET", "nets_channels?fields=".$fields."&".$filters."","", $this->token);
      return $channels;
  }

  // Get list of accounts (without blank account numbers)
  public function get_accounts() {
      $filters = 'filter=[{"account_c":{"$not_equals":""}}]';
      $fields = "name,account_c";
      $order_by = "name";
      $max_num = "-1";
      $accounts = $this->CallSugarAPI("GET", "Accounts?fields=".$fields."&order_by=".$order_by."&max_num=".$max_num."&".$filters."","", $this->token);
      return $accounts;
  }

  // Get OEMs
  public function scopeGetOems($query)
  {
    return DB::select('SELECT code,oems FROM oem_codes');
  }

}
