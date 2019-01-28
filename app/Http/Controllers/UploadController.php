<?php
namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\ProcessXml;

class UploadController extends Controller
{
  public function upload(Request $request)
  {
    $time = Carbon::now();
    $file = $request->file('file');
    $orgFilename = $file->getClientOriginalName();
    $extension = $file->getClientOriginalExtension();
    $directory = 'xml';
    // Creating the file name: random string followed by the day, random number and the hour
    $filename = str_random(5).date_format($time,'d').rand(1,9).date_format($time,'h').".".$extension;
    $upload_success = $file->storeAs($directory, $filename, 'public');
    //$upload_success = $file->storeAs('uploads', $filename);

    if ($upload_success) {
      if($extension == 'xml') {
        $processXml = new ProcessXml();
        return $processXml->process($filename,$orgFilename);
      }
      return response()->json($upload_success, 200);
    }
    else {
      return response()->json('error', 400);
    }
  }
}
