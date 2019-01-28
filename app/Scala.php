<?php

namespace App;

use Cache;
//use Storage;
use Log;
//use Carbon\Carbon;
//use GuzzleHttp;

// This is a slimmed-down version of the Scala helper in cdash
class Scala
{

  private $pagelimit = 20;
  private $cacheminutes = 1440;
  private $cachehours = 24;
  private $token = '';

  public function __construct()
  {
    $this->token = Cache::remember('scalatoken', $this->cacheminutes, function () {
      return $this->getToken();
    });
  }

  private function CallScalaAPI($method, $url, $data = NULL, $token = NULL, $check_login = FALSE, $makeDirectory = FALSE)
  {
    $api_url = "https://avacmd04.scala.com:44354/ContentManager";
    Log::info("Scala API Call Made: \"" . $method . "\", \"" . $url . "\"");

    $url = $api_url . $url;
    $content = json_encode($data);

    Log::info("Scala API Call Data Sent: " . print_r($data, true));

    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_HEADER, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    $curl_headers = array();
    $curl_headers[] = "Content-type: application/json";
    if (!is_null($token)) {
      $curl_headers[] = "token:" . $token;
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
      $this->login();
    } elseif ($status != 201 && $status != 200 && $status != 203 && $status != 204) {
      $response = json_encode(array("error" => "ERROR", "result" => json_decode($json_response, true)));
      //header("Location: http://control.abnetwork.com/error");
      //redirect('error');
      //die("Error: call to URL $url failed with status $status, response $json_response, curl_error " . curl_error($curl) . ", curl_errno " . curl_errno($curl)."<br />".print_r($content,true)." - ".$token." - ".$url);
    } else {
      $response = json_encode(array("status" => $status, "result" => json_decode($json_response, true)));
    }

    curl_close($curl);

    $response = json_decode($json_response, true);
    if (strpos($url, '/api/rest/players') === false) {
      Log::info("Scala API Call Data Received: " . print_r($response, true));
    }

    return $response;
  }

  // Login to Scala
  private function login()
  {
    $result = $this->CallScalaAPI("POST", "/api/rest/auth/login", array("username" => "ABN_API", "password" => "C0d3API54321!", "networkid" => "", "rememberMe" => TRUE));
    $this->token = $result["apiToken"];
    //log_message("error","API Token Received: ".$this->token);
  }

  // Login to Scala and get token
  private function getToken()
  {
    $result = $this->CallScalaAPI("POST", "/api/rest/auth/login", array("username" => "ABN_API", "password" => "C0d3API54321!", "networkid" => "", "rememberMe" => TRUE));
    $scalaToken = $result["apiToken"];
    return $scalaToken;
  }

  // Check for login
  private function check_login()
  {
    $this->token = Cache::remember('scalatoken', $this->cacheminutes, function () {
      return $this->getToken();
    });
    $result = $this->CallScalaAPI("GET", "/api/rest/auth/get", "", $this->token, TRUE);
  }

  // Check if Scala active
  public function checkScalaActive(){
    $result = $this->check_login();
    return $result;
  }

  // Get media by name
  public function get_media_by_name($file = NULL) {
    if (!is_null($file)) {
      $this->check_login();
      $filters = "{'name':{'values':['%".$file."%'],comparator: 'like'}}";
      $fields = "id,name,downloadPath";

      $media = $this->CallScalaAPI("GET", "/api/rest/media?sort=lastModified&fields=".urlencode($fields)."&limit=0&filters=".urlencode($filters),"", $this->token);
      return $media;
    } else {
      return false;
    }
  }

  // Get logo from media
  public function get_logo($acctNum) {
    $file = $this->get_media_by_name(intval($acctNum) . '_ABN_LIVE_LOGO.png');
    if(isset($file['list'][0]['downloadPath'])) {
      $filename = $file['list'][0]['downloadPath'];
      $cdn = 'https://abncdn.s3.amazonaws.com/';
      $filename = str_replace('/resources/content/','',$filename);
      $filename = preg_replace('/\[.*?\]/','',$filename);
      $filename = $cdn . $filename;
      return $filename;
    } else {
      return false;
    }
  }

}
