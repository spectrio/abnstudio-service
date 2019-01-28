<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Sugar;
use Illuminate\Support\Facades\Auth;

class SugarController extends Controller
{
  public function __construct()
  {
    //$this->middleware('auth');
  }

  public function index($id = null)
  {
    $output = 'No response with ID: ' . $id;
    // ID was passed in
    if($id) {
      $Sugar = new Sugar();
      $output = $Sugar->get_channels_by_account($id);
    }
    return json_encode($output);
  }


}
