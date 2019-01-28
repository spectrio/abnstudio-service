<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Jira;
use Illuminate\Support\Facades\Auth;

class JiraController extends Controller
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
      $Jira = new Jira();
      $output = $Jira->createIssue($id);
    }
    return json_encode($output);
  }


}
