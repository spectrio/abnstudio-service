<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Sugar;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SugarController extends Controller
{
  public function __construct()
  {
    //$this->middleware('auth');
  }


  // Create a list of customer channels
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


  // Create a list of OEMs from customer channels
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


  // Create a list of all customer accounts. Optionally cache results to DB
  public function getAccounts($cache = null)
  {
    $Sugar = new Sugar();
    $accounts = json_decode(json_encode($Sugar->get_accounts()),1);
    $accounts = $accounts['records'];
    $accountList = array();
    if($cache) {
      //DB::table('accounts')->truncate();
      DB::table('accounts')->where('id', 'like', '%%')->delete();
    }
    foreach($accounts as $v) {
      $accountList[$v['account_c']]['guid'] = $v['id'];
      $accountList[$v['account_c']]['name'] = $v['name'];
      $accountList[$v['account_c']]['modified'] = $v['date_modified'];
      if($cache) {
        DB::table('accounts')->insert([
          'id' => $v['account_c'],
          'name' => $v['name'],
          'guid' => $v['id'],
          'modified' => $v['date_modified']
        ]);
      }
    }
    return $accountList;
  }


  // Create a list of all customer accounts. Optionally cache results to DB
  public function getAccountsFromCache()
  {
    $accountsCache = DB::table('accounts')->get();
    return $accountsCache;
  }

}
