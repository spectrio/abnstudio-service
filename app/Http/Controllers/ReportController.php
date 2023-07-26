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

  public function sendReports(Request $request)
  {
    $start = $request->input('start') ?? date("Y-m-d", strtotime('7 days ago', strtotime('now'))); // 7 days ago
    $end = $request->input('end') ?? date("Y-m-d", strtotime('yesterday')); //yesterday, now

    $userFilename = 'reports/Weekly User Report ('.$start.' - '.$end.').csv';
    $templateFilename = 'reports/Weekly Template Usage ('.$start.' - '.$end.').csv';
    $templateFile = storage_path($templateFilename);
    $userFile = storage_path($userFilename);

    $request = new \Illuminate\Http\Request();
    $request->replace(['csv' => $templateFile, 'renders' => '1', 'start' => $start, 'end' => $end]);
    $this::getTemplates($request);

    $request->replace(['csv' => $userFile, 'renders' => '1', 'start' => $start, 'end' => $end]);
    $this::getUsers($request);

    // Email
    $title = '[TEST] ABN Studio! Usage Reports ('.$start.' - '.$end.')';
    $recipients = "chris.bartek@abnetwork.com";
    //$recipients = ["austin.phillips@abnetwork.com", "chris.bartek@abnetwork.com", "christan.miller@abnetwork.com", "cindy.goodin@abnetwork.com", "doug.porter@abnetwork.com", "jerry.daniels@abnetwork.com", "kate.carpenter@abnetwork.com", "robert.orndorff@abnetwork.com", "sean.mullins@abnetwork.com", "stephen.mcgowan@abnetwork.com", "steve.crabill@abnetwork.com"];
    $message_text = "ABN Studio! weekly usage reports are attached.";
    Mail::send('vendor.notifications.render', ['title' => $title, 'body' => $message_text], function ($message) use ($recipients, $title, $message_text, $templateFile, $userFile) {
      $message->subject($title);
      $message->from(env('MAIL_FROM_ADDRESS', 'development@abnetwork.com'), 'ABN Studio!');
      $message->to($recipients);
      $message->setBody($message_text);
      $message->attach($templateFile);
      $message->attach($userFile);
    });

    if (count(Mail::failures()) > 0) {
      $response['status'] = 'fail';
    } else {
      $response['status'] = 'success';
    }

    unlink($templateFile);
    unlink($userFile);

    $output['status'] = 'OK';
    return json_encode($output);
  }


  // Get list of templates
  public function getTemplates(Request $request)
  {
    $tid = $request->input('id') ?? '';
    $start = $request->input('start') ?? '';
    $end = $request->input('end') ?? '';
    $renders = $request->input('renders') ?? 0;
    $csv = $request->input('csv') ?? '';

    $templates = DB::table('templates')->select('tid', 'name', 'grp')->orderBy('name', 'asc');

    // TID was passed in
    if($tid) {
      $templates->where('tid', '=', $tid);
    }

    $templates = json_decode($templates->get()->toJson(),1);

    if($renders) {
      foreach ($templates as $k => $v) {
        $request = new \Illuminate\Http\Request();
        $request->replace(['tid' => $v['tid'], 'renders' => '1', 'noconv' => '1']);
        if($start) {
          $request->replace(['tid' => $v['tid'], 'renders' => '1', 'noconv' => '1', 'start' => $start]);
        }
        if($end) {
          $request->replace(['tid' => $v['tid'], 'renders' => '1', 'noconv' => '1', 'start' => $start, 'end' => $end]);
        }
        $spotList = $this::getSpots($request);
        $totalRenderCnt = 0;
        $totalTime = 0;
        $totalDuration = 0;
        $count = 0;
        foreach ($spotList as $kk => $vv) {
          $totalTime += $vv['renderTotalTime'];
          $totalDuration += $vv['renderTotalDuration'];
          $totalRenderCnt += $vv['renderCount'];
          $count++;
        }
        if($count) {
          $avgTime = $totalTime / $count;
          $avgDuration = $totalDuration / $count;
        } else {
          $avgTime = 0;
          $avgDuration = 0;
        }

        $templates[$k]['spotCount'] = $count;
        $templates[$k]['renderCount'] = $totalRenderCnt;
        $templates[$k]['renderTotalTime'] = $this::convertSecs($totalTime);
        $templates[$k]['renderTotalDuration'] = $this::convertSecs($totalDuration);
        $templates[$k]['renderAvgTime'] = $this::convertSecs(round($avgTime));
        $templates[$k]['renderAvgDuration'] = $this::convertSecs(round($avgDuration));

        // Filter out empties
        if(!$templates[$k]['spotCount']) {
          unset($templates[$k]);
        }
      }

    }

    if(!$csv) {
      return array_values($templates);
    } else {
      return $this::arr2csv($templates,$csv);
    }

  }


  // Get list of users
  public function getUsers(Request $request)
  {
    $user = $request->input('user') ?? '';
    $start = $request->input('start') ?? '';
    $end = $request->input('end') ?? '';
    $renders = $request->input('renders') ?? 0;
    $csv = $request->input('csv') ?? '';

    $users = DB::table('users')->select('id', 'name', 'username')->orderBy('username', 'asc');

    // User was passed in
    if($user) {
      if(is_numeric($user)) {
        $users->where('id', '=', $user);
      } else {
        $users->where('username', '=', $user);
      }
    }

    $users = json_decode($users->get()->toJson(),1);

    if($renders) {
      foreach ($users as $k => $v) {
        $request = new \Illuminate\Http\Request();
        $request->replace(['user' => $v['id'], 'renders' => '1', 'noconv' => '1']);
        if($start) {
          $request->replace(['user' => $v['id'], 'renders' => '1', 'noconv' => '1', 'start' => $start]);
        }
        if($end) {
          $request->replace(['user' => $v['id'], 'renders' => '1', 'noconv' => '1', 'start' => $start, 'end' => $end]);
        }
        $spotList = $this::getSpots($request);
        $totalRenderCnt = 0;
        $totalTime = 0;
        $totalDuration = 0;
        $count = 0;
        foreach ($spotList as $kk => $vv) {
          $totalTime += $vv['renderTotalTime'];
          $totalDuration += $vv['renderTotalDuration'];
          $totalRenderCnt += $vv['renderCount'];
          $count++;
        }
        if($count) {
          $avgTime = $totalTime / $count;
          $avgDuration = $totalDuration / $count;
        } else {
          $avgTime = 0;
          $avgDuration = 0;
        }

        $users[$k]['spotCount'] = $count;
        $users[$k]['renderCount'] = $totalRenderCnt;
        $users[$k]['renderTotalTime'] = $this::convertSecs($totalTime);
        $users[$k]['renderTotalDuration'] = $this::convertSecs($totalDuration);
        $users[$k]['renderAvgTime'] = $this::convertSecs(round($avgTime));
        $users[$k]['renderAvgDuration'] = $this::convertSecs(round($avgDuration));

        // Filter out empties
        if(!$users[$k]['spotCount']) {
          unset($users[$k]);
        }
      }

    }

    if(!$csv) {
      return array_values($users);
    } else {
      return $this::arr2csv($users,$csv);
    }
  }


  // Get list of Accounts
  public function getAccts(Request $request)
  {
    $id = $request->input('id') ?? '';
    $start = $request->input('start') ?? '';
    $end = $request->input('end') ?? '';
    $renders = $request->input('renders') ?? 0;
    $csv = $request->input('csv') ?? '';

    $accts = DB::table('accounts')->select('id', 'name')->orderBy('name', 'asc');

    // User was passed in
    if($id) {
      if(is_numeric($id)) {
        $accts->where('id', '=', $id);
      } else {
        $accts->where('name', 'like', '%' . $id . '%');
      }
    }

    $accts = json_decode($accts->get()->toJson(),1);

    if($renders) {
      foreach ($accts as $k => $v) {
        $request = new \Illuminate\Http\Request();
        $request->replace(['acct' => $v['id'], 'renders' => '1', 'noconv' => '1']);
        if($start) {
          $request->replace(['acct' => $v['id'], 'renders' => '1', 'noconv' => '1', 'start' => $start]);
        }
        if($end) {
          $request->replace(['acct' => $v['id'], 'renders' => '1', 'noconv' => '1', 'start' => $start, 'end' => $end]);
        }
        $spotList = $this::getSpots($request);
        $totalRenderCnt = 0;
        $totalTime = 0;
        $totalDuration = 0;
        $count = 0;
        foreach ($spotList as $kk => $vv) {
          $totalTime += $vv['renderTotalTime'];
          $totalDuration += $vv['renderTotalDuration'];
          $totalRenderCnt += $vv['renderCount'];
          $count++;
        }
        if($count) {
          $avgTime = $totalTime / $count;
          $avgDuration = $totalDuration / $count;
        } else {
          $avgTime = 0;
          $avgDuration = 0;
        }

        $accts[$k]['spotCount'] = $count;
        $accts[$k]['renderCount'] = $totalRenderCnt;
        $accts[$k]['renderTotalTime'] = $this::convertSecs($totalTime);
        $accts[$k]['renderTotalDuration'] = $this::convertSecs($totalDuration);
        $accts[$k]['renderAvgTime'] = $this::convertSecs(round($avgTime));
        $accts[$k]['renderAvgDuration'] = $this::convertSecs(round($avgDuration));

        // Filter out empties
        if(!$accts[$k]['id'] || !$accts[$k]['spotCount']) {
          unset($accts[$k]);
        }
      }

    }

    if(!$csv) {
      return array_values($accts);
    } else {
      return $this::arr2csv($accts,$csv);
    }
  }


  // Get list of spots
  public function getSpots(Request $request)
  {
    $tid = $request->input('tid') ?? 0;
    $mtid = $request->input('mtid') ?? 0;
    $user = $request->input('user') ?? '';
    $acct = $request->input('acct') ?? '';
    $start = $request->input('start') ?? '';
    $end = $request->input('end') ?? '';
    $renders = $request->input('renders') ?? 0;
    $noconv = $request->input('noconv') ?? 0;

    $spots = DB::table('templates_modified');//->select('tid','username','created_at','updated_at');

    // TID was passed in
    if($tid) {
      $spots->where('tid', '=', $tid);
    }
    // MTID was passed in
    if($mtid) {
      $spots->where('mtid', '=', $mtid);
    }
    // Start was passed in
    if($start) {
      $spots->whereDate('created_at', '>=', $start);
    }
    // End was passed in
    if($end) {
      $spots->whereDate('created_at', '<=', $end);
    }
    // User was passed in
    if($user) {
      if(is_numeric($user)) {
        $userLookup = DB::table('users')->select('username')->where('id', '=', $user);
        $userLookup = json_decode($userLookup->get()->toJson(),1);
        $user = $userLookup[0]['username'];
      }
      $spots->where('username', '=', $user);
    }
    // Acct was passed in
    if($acct) {
      $spots->where('acct', '=', $acct);
    }

    $spots = json_decode($spots->get()->toJson(),1);

    // Renders flag was passed in, calculate totals and averages for each
    if($renders) {
      foreach ($spots as $k => $v) {
        //echo $v['mtid'] . " ";
        $request = new \Illuminate\Http\Request();
        $request->replace(['noconv' => '1', 'mtid' => $v['mtid']]);
        if($start) {
          $request->replace(['noconv' => '1', 'mtid' => $v['mtid'], 'start' => $start]);
        }
        if($end) {
          $request->replace(['noconv' => '1', 'mtid' => $v['mtid'], 'start' => $start, 'end' => $end]);
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
        if($count) {
          $avgTime = $totalTime / $count;
          $avgDuration = $totalDuration / $count;
        } else {
          $avgTime = 0;
          $avgDuration = 0;
        }

        //echo ": COUNT:" . $count . " TotalDuration:" . $totalDuration . "TotalTime:". $totalTime . 'AVG: ' . $avgTime . ", " . $avgDuration;
        //print_r($renderList);
        $spots[$k]['renderCount'] = $count;
        $spots[$k]['renderAvgTime'] = $this::convertSecs(round($avgTime),$noconv);
        $spots[$k]['renderAvgDuration'] = $this::convertSecs(round($avgDuration)/1000,$noconv);
        $spots[$k]['renderTotalTime'] = $this::convertSecs($totalTime,$noconv);
        $spots[$k]['renderTotalDuration'] = $this::convertSecs($totalDuration/1000,$noconv);
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
    $acct = $request->input('acct') ?? 0;
    $user = $request->input('user') ?? 0;
    $start = $request->input('start') ?? '';
    $end = $request->input('end') ?? date("Y-m-d", strtotime('now'));
    $noconv = $request->input('noconv') ?? 0;

    $renders = DB::table('renders');
    $renders->leftJoin('templates_modified', 'templates_modified.mtid', '=', 'renders.mtid')->select('rid', 'renders.tid', 'renders.mtid', 'start_time', 'end_time', 'total_time', 'render_duration', 'job', 'status', 'acct', 'acct_name', 'name_modified', 'username');

    // RID was passed in
    if($id) {
      $renders->where('rid', '=', $id);
    }
    // TID was passed in
    if($tid) {
      $renders->where('renders.tid', '=', $tid);
    }
    // MTID was passed in
    if($mtid) {
      $renders->where('renders.mtid', '=', $mtid);
    }
    // Acct was passed in
    if($acct) {
      $renders->where('acct', '=', $acct);
    }
    // User was passed in
    if($user) {
      $renders->where('username', '=', $user);
    }
    // Range was passed in
    if($start) {
      $renders->whereDate('renders.start_time', '>=', $start)->whereDate('renders.end_time', '<=', $end);
    }

    $renders = json_decode($renders->get()->toJson(),1);

    if(!$noconv) {
      foreach($renders as $k => $v) {
        $renders[$k]['total_time'] = $this::convertSecs(round($v['total_time']),$noconv);
        $renders[$k]['render_duration'] = $this::convertSecs(round($v['render_duration']/1000),$noconv);
      }
    }

    return $renders;
  }


  // Convert array to csv
  function arr2csv($arr,$filename='php://output') {
    $file = fopen($filename, 'w');
    // write keys to first line
    foreach (array_values($arr)[0] as $k => $v) {
      $keys[] = $k;
    }
    fputcsv($file, $keys);
    foreach ($arr as $row) {
      fputcsv($file, $row);
    }
    if($filename != 'php://output') {
      //file_put_contents($filename,$file);
      fclose($file);
      return $file;
    }

  }


  // Convert seconds to H:M:S
  function convertSecs($secs,$bypass=false) {
    if($bypass) {
      // No conversion, just return the original number
      return $secs;
    }
    if($secs > 85399) {
      // Show days
      $secs = gmdate("z H:i:s", $secs);
    } else {
      if($secs > 3599) {
        // Show hours
        $secs = gmdate("H:i:s", $secs);
      } else {
        // Just show minutes and seconds
        $secs = gmdate("i:s", $secs);
      }
    }
    return $secs;
  }


}
