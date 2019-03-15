<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use AWS;
use Log;

class AwsController extends Controller
{
  public function uploadImage($file)
  {
    $s3 = AWS::createClient('s3');

    $filename = "https://wevideo.s3.amazonaws.com/client_uploads/".$file;

    try {
     $result["preview"] = $s3->putObject([
      'Bucket' => 'wevideo',
      'Key'    => 'client_uploads/'.$file,
      'Body'   => fopen('uploads/img/'.$file, 'r'),
      'ACL'    => 'public-read',
     ]);
     return $filename;
    } catch (Aws\S3\Exception\S3Exception $e) {
      die ("Amazon Upload Error: ".$e);
      Log::info("Amazon Upload Error: ".$e);
    }

  }
}
