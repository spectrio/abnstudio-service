<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\ModifiedTemplate;
use App\ModifiedFields;
use App\ModifiedProcessXml;
use App\RenderLog;
use App\Jira;
use Carbon\Carbon;
use Log;
//use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
//use Mail; // Laravel
use Illuminate\Support\Facades\Mail; // Lumen

class ModifyTemplateController extends Controller
{
    public function __construct()
    {
        //$this->middleware('auth');
    }

    public function index(Request $request)
    {
      //$this->validate($request, [
      //  'templateName' => 'required',
      //]);

      $data = json_decode($request->getContent(), true);
      $fields = $data['templateFields'];
      Log::info('=== BIRTHDAY SPOT DEBUG ===');
      Log::info('Template ID: ' . ($data['template']['tid'] ?? 'N/A'));
      Log::info('Template Fields: ' . print_r($fields, true));
      Log::info('Full Data: ' . print_r($data, true));
      //print_r($data);die();

      // Check for cropped images
      if(!empty($fields)) {
        foreach($fields as $key => $field) {
          // Field passed over an image
          if(isset($field['crop']) && !empty($field['crop'])){
            // Decode image
            $cropdata = explode(',',$field['crop']['src']);
            $mime = str_replace('data:','', str_replace(';base64','',$cropdata[0]) );
            $cropdata = base64_decode($cropdata[1]);
            $time = Carbon::now();
            $filename = str_random(5).date_format($time,'d').rand(1,9).date_format($time,'h');

            $im = imagecreatefromstring($cropdata);
            imagealphablending($im, true);
            if($im !== false) {
              // Crop the image
              $im2 = imagecrop($im, [
                'x' => $field['crop']['x'],
                'y' => $field['crop']['y'],
                'width' => $field['crop']['w'],
                'height' => $field['crop']['h']
              ]);
              if($im2 !== false) {
                $uploadDir = public_path('uploads/img') . '/';
                Log::info('Upload dir: ' . $uploadDir);
                Log::info('Dir exists: ' . (file_exists($uploadDir) ? 'yes' : 'no'));
                Log::info('Dir writable: ' . (is_writable($uploadDir) ? 'yes' : 'no'));
                if (!file_exists($uploadDir)) {
                  mkdir($uploadDir, 0777, true);
                }

                if($mime == 'image/png') {
                  $extension = 'png';
                  $filename .= ".".$extension;
                  imagealphablending($im2, true);
                  imagesavealpha($im2, true);
                  $saved = imagepng($im2, $uploadDir . $filename);
                  Log::info('imagepng result: ' . ($saved ? 'success' : 'failed') . ' path: ' . $uploadDir . $filename);
                } else {
                  $extension = 'jpg';
                  $filename .= ".".$extension;
                  $saved = imagejpeg($im2, $uploadDir . $filename);
                  Log::info('imagejpeg result: ' . ($saved ? 'success' : 'failed') . ' path: ' . $uploadDir . $filename);
                }

                // Upload image to CDN and set the content to result URL
                $awsUpload = app('App\Http\Controllers\AwsController')->uploadImage($filename);
                if($awsUpload) {
                  // Update URL data so it gets processed and saved correctly
                  $fields[$key]['content'] = $awsUpload;
                  $data['templateFields'][$key]['content'] = $awsUpload;
                }

                imagedestroy($im2);
              } else {
                die('{"error":"Could not create cropped image!"}');
              }
              imagedestroy($im);
            } else {
              die('{"error":"Could not create image!"}');
            }
          }
        }
      }


      // Handle OPTIONS method. Chrome requires a response due to CORS
      if ($request->isMethod('options'))
      {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE, PATCH');
        header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Key, Authorization');
        header('Access-Control-Allow-Credentials: true');
        exit(0);
      }

      // If HTTP PATCH method, set id so it'll update instead of insert
      if ($request->isMethod('patch') && isset($data['template']['mtid']))
      {
        $mtid = $data['template']['mtid'];
        $save = ModifiedTemplate::findOrFail($mtid);
        // Remove fields so they can be reinserted below
        $deleteRows = ModifiedFields::where('mtid', $mtid)->delete();
      } else {
        $save = new ModifiedTemplate;
      }

      $render = $data['template']['render'];
      $dtv = $data['template']['dtv'] ?? 0;

      Log::info('=== RENDER FLAG CHECK ===');
      Log::info('Render flag value: ' . ($render ? 'TRUE' : 'FALSE'));
      Log::info('DTV flag value: ' . ($dtv ? 'TRUE' : 'FALSE'));

      $playlists = null;
      if(isset($data['template']['playlists'])) {
        $playlists = implode(',', $data['template']['playlists']);
      }

      if(isset($data['template']['publish']) && $data['template']['publish']) {
        $publish = 1;
      }

      $startdate = null;
      if(isset($data['template']['startdate'])) {
        $startdate = date('Y-m-d H:i:s',strtotime(str_replace('-','/',$data['template']['startdate'])));
      }
      $enddate = null;
      if(isset($data['template']['enddate'])) {
        $enddate = date('Y-m-d H:i:s',strtotime(str_replace('-','/',$data['template']['enddate'])));
      }

      // Create a modified XML and render it
      $load = new ModifiedTemplate;
      $template = $load->getTemplate($data['template']['tid']);
      $templateData = $load->getTemplateData($data['template']['tid']);
      $thumbnailTime = $templateData['thumbnail_time'];
      $orientation = $templateData['orientation'];
      if($render) {
        Log::info('=== STARTING XML PROCESSING ===');
        $process = new ModifiedProcessXml;
        $renderResult = $process->process($template,$data,$thumbnailTime,$orientation,$dtv);
        $output['render'] = json_decode($renderResult,1);
        Log::info('=== XML PROCESSING COMPLETE ===');




        //die($renderResult);
      } else {
        // Send JIRA ticket
        //if(isset($jobStatus['status']) && $jobStatus['status'] == 'COMPLETED') {
          //Log::info('ModifyTemplateController: Create JIRA issue: '.print_r($jobStatus,1));
          $Jira = new Jira();
          $output = $Jira->createIssue($mtid, $data['template']['acct'], $data['template']['acctName'], $data['template']['username'], $playlists, $save->url_modified, $startdate, $enddate);
        //}
      }


      $renderOnly = $data['template']['renderOnly'] ?? false;
      //if($renderOnly) {
      //  return($renderResult);
      //}




      // Save the modified template to database
      $save->tid = $data['template']['tid'];
      $save->acct = $data['template']['acct'];
      $save->acct_name = $data['template']['acctName'];
      $save->name_modified = $data['template']['name_modified'];
      $save->oem = $data['template']['oem'] ?? null;
      $save->logotype = $data['template']['logotype'] ?? null;
      $save->publish = $publish ?? null;
      $save->username = $data['template']['username'] ?? null;
      $save->playlists = $playlists ?? null;
      $save->start_date =  $startdate;
      $save->end_date = $enddate;
      if($render) {
        $save->job_id = $output['render']['jobId'] ?? null;
        $save->job_status = null;
        $save->email = $data['template']['email'] ?? null;
        $save->url_modified = null;
        $save->thumbnail_modified = null;
      }
      if(!$renderOnly) {
        $save->save();
      }
      $mtid = $save->mtid;

      // Save each modified template field to the database
      if(!empty($fields)) {
        foreach($fields as $key => $field) {
          $saveFields = new ModifiedFields;
          $saveFields->mtid = $mtid ?? 0;
          $saveFields->layer = $field['layer'];
          $saveFields->content = $field['content'];
          if(!$renderOnly) {
            $saveFields->save();
          }

        }
      }

      // Log render
      if($render) {
        $duration = $output['render']['templateDuration'];
        if($output['render']['endTime'] > 0) {
          $duration = $output['render']['endTime'];
        }
        $renderLog = new RenderLog;
        $renderLog->tid         = $data['template']['tid'] ?? null;
        $renderLog->mtid        = $mtid ?? null;
        $renderLog->start_time  = date("Y-m-d H:i:s", strtotime('now'));
        $renderLog->end_time    = null;
        $renderLog->total_time  = null;
        $renderLog->render_duration    = $duration;
        $renderLog->job         = $output['render']['jobId'] ?? null;
        $renderLog->status      = 'QUEUED';
        $renderLog->save();
      }

      // Output
      if(!$renderOnly) {
        $output['success'] = 'Modified template created successfully.';
      } else {
        $output['success'] = 'Modified template creation simulated successfully.';
      }
      $output['mtid'] = $mtid;
      return json_encode($output);
      //return json_encode($request->input());
    }

    public function get($id)
    {
      if($id) {
        $template = ModifiedTemplate::getModifiedTemplate($id)->get();
        $fields = ModifiedFields::getModifiedFields($id)->get();

        if ($template->isEmpty()) {
          return '{"success":false, "message":"Template not found."}';
        }

        $output['template'] = $template[0];
        $output['templateFields'] = $fields;
        return json_encode($output);
      } else {
        return '{"success":false, "message":"ID required."}';
      }
    }

    public function list(Request $request,$mode,$id,$filter=null)
    {
      $filter = $request->input('filter') ?? '';
      if($id) {
        if($mode == 'oems' || $mode == 'categories' || $mode == 'tags') {
          if(env('DB_CONNECTION') == 'sqlite') {
            $templates = ModifiedTemplate::getModifiedTemplatesLike($mode,$id)->get();
          } else {
            $templates = ModifiedTemplate::getModifiedTemplatesFind($mode,$id)->get();
          }

        }
        else {
          if(!$filter) {
            $templates = ModifiedTemplate::getModifiedTemplates($mode,$id)->get();
          } else {
            $templates = ModifiedTemplate::getModifiedTemplates($mode,$id)->where('grp',$filter)->get();
            //return "!!".$filter;
          }

        }
        foreach($templates as $temp) {
          unset($temp['template']);
          $output['templates'] = $templates;
        }

        if(!isset($output)) {
          return '{"success":false, "message":"0 records found."}';
        }
        return json_encode($output);
      } else {
        return '{"success":false, "message":"Account number required."}';
      }
    }

    public function delete(Request $request)
    {
      //$this->middleware('auth');
      $id = $request->input('id') ?? 0;
      if(isset($id)) {
        $modifiedTemplate = new ModifiedTemplate;
        $deleteTemplate = $modifiedTemplate->GetModifiedTemplate($id)->delete();
        if($deleteTemplate) {
          $deleteRows = ModifiedFields::where('mtid', $id)->delete();
          $output['success'] = "Modified Template deleted.";
        } else {
          $output['error'] = "Modified Template not found.";
        }

        return $output;
      }
    }

    public function reports()
    {
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
      foreach($modifiedTemplates as $k => $v) {
        $diff = strtotime($v['updated_at']) - strtotime($v['created_at']);
        if(!isset($users[$v['username']])) {
          $users[$v['username']] = array();
        }
        if(!isset($users[$v['username']]['qty'])) {
          $users[$v['username']]['qty'] = 0;
        }
        $users[$v['username']]['qty'] += 1;
        //$users[$v['username']]['templates'][] += $diff;
        $users[$v['username']]['times'][] = $diff;

        //
        if(!isset($templateList[$templateArr[$v['tid']]])) {
          $templateList[$templateArr[$v['tid']]] = array();
        }
        if(!isset($templateList[$templateArr[$v['tid']]]['qty'])) {
          $templateList[$templateArr[$v['tid']]]['qty'] = 0;
        }
        $templateList[$templateArr[$v['tid']]]['qty'] += 1;
        $templateList[$templateArr[$v['tid']]]['times'][] = $diff;
      }

      // Build user CSV
      ksort($users);
      $userCsv = '"username","qty","avg"'.PHP_EOL;
      foreach($users as $k => $v) {
        $a = array_filter($v['times']);
        if(count($a)) {
          $average = array_sum($a) / count($a);
        } else {
          $average = 0;
        }
        $users[$k]['avg'] = gmdate("H:i:s", $average);
        if($average > 85399) {
          $users[$k]['avg'] = gmdate("z H:i:s", $average);
        }
        $userCsv .= '"'.$k.'","'.$v['qty'].'","'.$users[$k]['avg'].'"'.PHP_EOL;
      }
      //echo $userCsv;

      // Build template CSV
      ksort($templateList);
      $templateCsv = '"template","qty","avg"'.PHP_EOL;
      foreach($templateList as $k => $v) {
        $a = array_filter($v['times']);
        if(count($a)) {
          $average = array_sum($a) / count($a);
        } else {
          $average = 0;
        }
        $templateList[$k]['avg'] = gmdate("H:i:s", $average);
        if($average > 85399) {
          $templateList[$k]['avg'] = gmdate("z H:i:s", $average);
        }
        $templateCsv .= '"'.$k.'","'.$v['qty'].'","'.$templateList[$k]['avg'].'"'.PHP_EOL;
      }
      //echo $templateCsv;

      $userFilename = 'reports/Weekly User Report ('.$xDaysAgo.' - '.$today.').csv';
      $templateFilename = 'reports/Weekly Template Usage ('.$xDaysAgo.' - '.$today.').csv';
      file_put_contents(storage_path($userFilename),$userCsv);
      file_put_contents(storage_path($templateFilename),$templateCsv);

      // Email
      $title = 'ABN Studio! Usage Reports ('.$xDaysAgo.' - '.$today.')';
      //$recipients = "chris.bartek@abnetwork.com";
      $recipients = ["austin.phillips@abnetwork.com", "chris.bartek@abnetwork.com", "christan.miller@abnetwork.com", "cindy.goodin@abnetwork.com", "doug.porter@abnetwork.com", "jerry.daniels@abnetwork.com", "kate.carpenter@abnetwork.com", "robert.orndorff@abnetwork.com", "sean.mullins@abnetwork.com", "stephen.mcgowan@abnetwork.com", "steve.crabill@abnetwork.com"];
      $message_text = "ABN Studio! weekly usage reports are attached.";
      Mail::send('vendor.notifications.render', ['title' => $title, 'body' => $message_text], function ($message) use ($recipients, $title, $message_text, $templateFilename, $userFilename) {
        $message->subject($title);
        $message->from(env('MAIL_FROM_ADDRESS', 'development@abnetwork.com'), env('MAIL_FROM_NAME', 'ABN Studio!'));
        $message->to($recipients);
        $message->setBody($message_text);
        $message->attach(storage_path($templateFilename));
        $message->attach(storage_path($userFilename));
      });

      if (count(Mail::failures()) > 0) {
        $response['status'] = 'fail';
      } else {
        $response['status'] = 'success';
      }

      unlink(storage_path($templateFilename));
      unlink(storage_path($userFilename));

      return $response;

    }

}
