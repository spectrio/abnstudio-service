<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\ModifiedProcessXml;
use App\ModifiedTemplate;
use Illuminate\Support\Facades\DB;
use Mail;

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

    // Loop through all incomplete jobs and get their status from WeVideo
    foreach ($jobs as $job) {
      $process = new ModifiedProcessXml;
      $jobId = $job->job_id;
      $jobEmail = $job->email;
      $jobStatus = $process->jobStatus($jobId);
      $jobStatus = json_decode($jobStatus,1);

      // If missing, add default values
      if(!isset($jobStatus['status'])) {
        $jobStatus['status'] = 'UNKNOWN';
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
