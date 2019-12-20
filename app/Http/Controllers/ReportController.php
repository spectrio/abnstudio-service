<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Template;
use App\FormFields;
//use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
//use Mail; // Laravel
use Illuminate\Support\Facades\Mail; // Lumen

class ReportController extends Controller
{
  public function __construct()
  {
    //$this->middleware('auth');
  }

  public function getTemplates(Request $request)
  {
    $id = $request->input('id') ?? 0;
    $oem = $request->input('oem') ?? '';
    $oems = $request->input('oems') ?? '';
    // ID was passed in
    //if($id) {
      //$lastMonday = date("Y-m-d", strtotime('monday this week', strtotime('now')));
      $xDaysAgo = date("Y-m-d", strtotime('7 days ago', strtotime('now'))); // 7 days ago
      $today = date("Y-m-d", strtotime('yesterday')); //yesterday, now

      $templates = DB::table('templates')->select('tid','name','grp')->get()->toJson();
      $templates = json_decode($templates,1);
      //print_r($templates);

      $templateArr = array();
      $users = array();

      foreach($templates as $k => $v) {
        $templateArr[$v['tid']] = $v['name'];
      }

      $modifiedTemplates = DB::table('templates_modified')->select('tid','username','created_at','updated_at')->where('created_at', '>=', $xDaysAgo)->get();
      $modifiedTemplates = json_decode($modifiedTemplates,1);
      return $modifiedTemplates;

    //}

    return json_encode($output);
  }


  // Get list of spots
  public function getSpots(Request $request)
  {
    $tid = $request->input('tid') ?? 0;
    $mtid = $request->input('mtid') ?? 0;
    $user = $request->input('user') ?? '';
    $start = $request->input('start') ?? '';
    $end = $request->input('end') ?? '';
    $renders = $request->input('renders') ?? 0;

    $spots = DB::table('templates_modified');//->select('tid','username','created_at','updated_at');

    // TID was passed in
    if($tid) {
      $spots->where('tid', '=', $tid);
    }
    // MTID was passed in
    if($mtid) {
      $spots->where('mtid', '=', $mtid);
    }
    // User was passed in
    if($user) {
      $spots->where('username', '=', $user);
    }
    // Start was passed in
    if($start) {
      $spots->whereDate('created_at', '>=', $start)->get();
    }
    // End was passed in
    if($end) {
      $spots->whereDate('created_at', '<=', $end)->get();
    }

    $spots = json_decode($spots->get()->toJson(),1);

    // Renders flag was passed in
    if($renders) {
      foreach ($spots as $k => $v) {
        //echo $v['mtid'] . " ";
        $request = new \Illuminate\Http\Request();
        $request->replace(['mtid' => $v['mtid']]);
        if($start) {
          $request->replace(['mtid' => $v['mtid'], 'start' => $start]);
        }
        if($end) {
          $request->replace(['mtid' => $v['mtid'], 'start' => $start, 'end' => $end]);
        }
        $renderList = $this::getRenders($request);
        $totalTime = 0;
        $totalDuration = 0;
        $count = 0;
        foreach ($renderList as $kk => $vv) {
          $totalTime += $vv['total_time'];
          $totalDuration += $vv['render_duration'];
          $count++;
        }
        $avgTime = $totalTime / $count;
        $avgDuration = $totalDuration / $count;
        //echo ": COUNT:" . $count . " TotalDuration:" . $totalDuration . "TotalTime:". $totalTime . 'AVG: ' . $avgTime . ", " . $avgDuration;
        //print_r($renderList);
        $spots[$k]['renderCount'] = $count;
        $spots[$k]['renderAvgTime'] = $avgTime;
        $spots[$k]['renderAvgDuration'] = $avgDuration;
        $spots[$k]['renderTotalTime'] = $totalTime;
        $spots[$k]['renderTotalDuration'] = $totalDuration;
      }
    }

    return $spots;
  }


  // Get list of renders
  public function getRenders(Request $request)
  {
    $id = $request->input('id') ?? 0;
    $tid = $request->input('tid') ?? 0;
    $mtid = $request->input('mtid') ?? 0;
    $start = $request->input('start') ?? '';
    $end = $request->input('end') ?? date("Y-m-d", strtotime('now'));;

    $renders = DB::table('renders');

    // RID was passed in
    if($id) {
      $renders->where('rid', '=', $id);
    }
    // TID was passed in
    if($tid) {
      $renders->where('tid', '=', $tid);
    }
    // MTID was passed in
    if($mtid) {
      $renders->where('mtid', '=', $mtid);
    }
    // Range was passed in
    if($start) {
      $renders->whereDate('start_time', '>=', $start)->whereDate('end_time', '<=', $end);
    }

    $renders = json_decode($renders->get()->toJson(),1);
    return $renders;
  }


}
