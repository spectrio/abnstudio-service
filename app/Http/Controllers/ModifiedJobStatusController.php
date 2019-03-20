<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\ModifiedProcessXml;
use App\ModifiedTemplate;
use Illuminate\Support\Facades\DB;
use Mail;
use App\Jira;
use Illuminate\Support\Facades\Log;

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
    //Log::info('refresh()');

    // Loop through all incomplete jobs and get their status from WeVideo
    foreach ($jobs as $job) {
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

}
