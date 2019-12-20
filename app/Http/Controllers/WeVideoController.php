<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\WeVideo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WeVideoController extends Controller
{
  public function __construct()
  {
    //$this->middleware('auth');
  }


  // Create a list of customer channels
  public function login()
  {
    $WeVideo = new WeVideo();
    $output = $WeVideo->login();
    return json_encode($output);
  }

  public function get_media($id)
  {
    $WeVideo = new WeVideo();
    $output = $WeVideo->get_media($id);
    return json_encode($output);
  }

}
