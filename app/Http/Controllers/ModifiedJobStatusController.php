<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\ModifiedProcessXml;
use App\ModifiedTemplate;
use App\RenderLog;
use Illuminate\Support\Facades\DB;
use App\Jira;
use Illuminate\Support\Facades\Log;
//use Mail; // Laravel
use Illuminate\Support\Facades\Mail; // Lumen

class ModifiedJobStatusController extends Controller
{
  // Get job status from WeVideo for a single item
  public function index(Request $request)
  {
    $this->validate($request, [
      'id' => 'required',
    ]);

    $jobId = $request->input('id');
    $process = new ModifiedProcessXml;
    return $process->jobStatus($jobId);
  }

  // Refresh template job status
  public function refresh()
  {
    $jobs = ModifiedTemplate::incompleteJobs()->get();
    Log::info('refresh()');

    // Loop through all incomplete jobs and get their status from WeVideo
    foreach ($jobs as $job) {
      Log::info('Refreshing '.print_r($job->job_id,true));
      //print_r($job);die();
      $process = new ModifiedProcessXml;
      $jobId = $job->job_id;
      $jobEmail = $job->email;
      $jobMtid = $job->mtid;
      $jobPublish = $job->publish;
      $jobAcct = $job->acct;
      $jobAcctName = $job->acct_name;
      $jobUsername = $job->username;
      $jobPlaylists = $job->playlists;
      $jobStartDate = $job->start_date;
      $jobEndDate = $job->end_date;
      $jobStatus = $process->jobStatus($jobId);
      $jobStatus = json_decode($jobStatus,1);

      // If missing, add default values
      if(!isset($jobStatus['status'])) {
        $jobStatus['status'] = 'UNKNOWN';
      } else {
        if($jobStatus['status'] == 'COMPLETED' && $jobEmail) {
          // Send email
          $request['title'] = "WeVideo Test Render";
          $request['body'] = "Your test render is now ready.<br><br>".$jobStatus['url'];
          $request['recipients'] = $jobEmail;
          $requestObj = new \Illuminate\Http\Request();
          $requestObj->replace($request);
          //$this->email($requestObj);
        }
      }

      // If completed, send JIRA ticket
      if(isset($jobStatus['status']) && $jobStatus['status'] == 'COMPLETED') {

        // Job marked as publish, so send out a JIRA ticket
        Log::info('Controller: Create JIRA issue: '.print_r($jobStatus,1));
        if($jobPublish) {
          $Jira = new Jira();
          $output = $Jira->createIssue($jobMtid, $jobAcct, $jobAcctName, $jobUsername, $jobPlaylists, $jobStatus['url'], $jobStartDate, $jobEndDate);
        }
      }

      if(!isset($jobStatus['url'])) {
        $jobStatus['url'] = null;
      }
      if(!isset($jobStatus['thumbnailUrl'])) {
        $jobStatus['thumbnailUrl'] = null;
      }
      $modifiedTemplate = new ModifiedTemplate;
      $modifiedTemplate->updateStatus($jobId,$jobStatus);

      $renderLog = new RenderLog;
      $renderLog->updateRender($jobId,$jobStatus);
    }

    return json_encode($jobs,1);
  }

  // Show the template queue
  public function queue()
  {
    // Refresh the DB before returning
    $refresh = $this->refresh();

    $templates = ModifiedTemplate::idDescending()->get();
    return json_encode($templates);
  }

  public function email(Request $request) {
    $title = $request['title'];
    $recipients = $request['recipients'];
    $message_text = $request['body'];
    try {
      Mail::send('vendor.notifications.render', ['title' => $title, 'body' => $message_text], function ($message) use ($recipients, $title, $message_text) {
        $message->subject($title);
        $message->from(env('MAIL_FROM_ADDRESS', 'development@abnetwork.com'), env('MAIL_FROM_NAME', 'WeVideo Service'));
        $message->to($recipients);
        $message->setBody($message_text);
      });

      if (count(Mail::failures()) > 0) {
        $response['status'] = 'fail';
      } else {
        $response['status'] = 'success';
      }
    } catch (Exception $e) {
      $response['status'] = 'transport failure';
    }

    return $response;
  }

  public function emailTest(Request $request) {
    $title = 'Test Email';
    $recipients = 'chris.bartek@abnetwork.com';
    $message_text = 'This is an automated email test, please disregard.';
    try {
      Mail::send('vendor.notifications.render', ['title' => $title, 'body' => $message_text], function ($message) use ($recipients, $title, $message_text) {
        $message->subject($title);
        $message->from(env('MAIL_FROM_ADDRESS', 'development@abnetwork.com'), env('MAIL_FROM_NAME', 'WeVideo Service'));
        $message->to($recipients);
        $message->setBody($message_text);
      });

      if (count(Mail::failures()) > 0) {
        $response['status'] = 'fail';
      } else {
        $response['status'] = 'success';
      }
    } catch (Exception $e) {
      $response['status'] = 'transport failure';
    }

    return $response;
  }

}
