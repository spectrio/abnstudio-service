<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\ProcessXml;
use App\Template;
use Illuminate\Support\Facades\DB;
use Mail;

class JobStatusController extends Controller
{
  // Get job status from WeVideo for a single item
  public function index(Request $request)
  {
    $this->validate($request, [
      'id' => 'required',
    ]);

    $jobId = $request->input('id');
    $processXml = new ProcessXml;
    return $processXml->jobStatus($jobId);
  }

  // Refresh template job status
  public function refresh()
  {
    $jobs = Template::incompleteJobs()->get();

    // Loop through all incomplete jobs and get their status from WeVideo
    foreach ($jobs as $job) {
      $processXml = new ProcessXml;
      $jobId = $job->job_id;
      $jobEmail = $job->email;
      $jobStatus = $processXml->jobStatus($jobId);
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
          $this->email($requestObj);
        }
      }
      if(!isset($jobStatus['url'])) {
        $jobStatus['url'] = null;
      }
      if(!isset($jobStatus['thumbnailUrl'])) {
        $jobStatus['thumbnailUrl'] = null;
      }

      $template = new Template;
      $template->updateStatus($jobId,$jobStatus);
    }

    return json_encode($jobs,1);
  }

  // Show the template queue
  public function queue()
  {
    // Refresh the DB before returning the view
    $refresh = $this->refresh();

    //$templates = JobStatus::all();
    $templates = Template::idDescending()->get();
    return view('queue')->with('templates',$templates);
  }

  public function email(Request $request) {
    $title = $request['title'];//"WeVideo Test Render";
    $recipients = $request['recipients'];//"chris.bartek@abnetwork.com";
    $message_text = $request['body'];//"Your test render is now ready.";
    Mail::send('vendor.notifications.render', ['title' => $title, 'body' => $message_text], function ($message) use ($recipients, $title, $message_text) {
        $message->subject($title);
        $message->from(env('MAIL_FROM_ADDRESS', 'development@abnetwork.com'), env('MAIL_FROM_NAME', 'WeVideo Service'));
        $message->to($recipients);
        $message->setBody($message_text);
        //$message->attach(public_path($file_attachment));
    });

    if (count(Mail::failures()) > 0) {
        $response['status'] = 'fail';
    } else {
        $response['status'] = 'success';
    }

    return $response;
  }
}
