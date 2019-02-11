<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Sugar;
use Illuminate\Support\Facades\Auth;

class SugarController extends Controller
{
  public function __construct()
  {
    //$this->middleware('auth');
  }

  public function index($id = null)
  {
    $output = 'No response with ID: ' . $id;
    // ID was passed in
    if($id) {
      $Sugar = new Sugar();
      $output = $Sugar->get_channels_by_account($id);
      foreach ($output['records'] as $k => $v) {
        $channelList[] = $v['name'];
      }
      $output['oemList'] = $this->getOemList($channelList);
    }
    return json_encode($output);
  }


  public function getOemList($channels)
  {
    $Sugar = new Sugar();
    $oemCodes = json_decode(json_encode($Sugar->getOems()),1);
    foreach($oemCodes as $v) {
      $oemList[$v['code']] = $v['oems'];
    }
    //print_r($channels);die();
    //$channels = array("CH1234", "GK1234", "DJ1234");
    $oems = '';
    foreach ($channels as $v) {
      $oems .= $oemList[substr($v,0,2)] . ',';
    }
    $oems = explode(',',$oems);
    $oems = array_map('trim',$oems);
    return array_filter(array_unique($oems));
  }

}
