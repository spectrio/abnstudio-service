<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Template;
use App\FormFields;
use App\ProcessXml;
//use Illuminate\Support\Facades\Auth;

class SaveTemplateController extends Controller
{
    public function __construct()
    {
        //$this->middleware('auth');
    }

    public function index(Request $request)
    {
      $this->validate($request, [
        'templateName' => 'required',
      ]);

      // Save the template to database

      // If HTTP PATCH method, set id so it'll update instead of insert
      if ($request->isMethod('patch'))
      {
        $tid = $request->input('tid');
        $save = Template::findOrFail($tid);
        // Remove fields so they can be reinserted below
        //$deleteRows = FormFields::where('tid', $tid)->delete();
        //$save->findOrFail(20);
        //return(json_encode($save));
        $output['success'] = 'Template updated successfully.';
      } else {
        $save = new Template;
        $output['success'] = 'Template created successfully.';
      }

      // User requested a render, send it to WeVideo and store the jobId
      if ($request->input('render') == 'true' || $request->input('render') == 1 ) {
        $processXml = new ProcessXml;
        $responseJson = $processXml->render($request->input('xmlDetails.xmlFinal'), $request->input('thumbnailTime'), $request->input('orientation'));
        $response = json_decode($responseJson,1);
        if($response == null) {
          unset($output['success']);
          $output['error'] = 'No response received from WeVideo.';
          return json_encode($output);
        }
        if(isset($response['error_code'])) {
          return($responseJson);
        }
        $jobId = $response['jobId'];
        $save->job_id = $jobId;
        $save->job_status = null;
      }

      $save->name = $request->input('templateName');
      $save->oems = $request->input('oems');
      $save->categories = $request->input('categories');
      $save->tags = $request->input('tags');
      $save->orientation = $request->input('orientation');
      $save->grp = $request->input('grp');
      //$save->template = file_get_contents(public_path().'/uploads/xml/'.$request->input('xmlDetails.filename'));
      $save->template = $request->input('xmlDetails.xmlFinal');
      $save->visible = $this->fixBool($request->input('publish'));
      $save->email = $request->input('renderEmail');
      $save->thumbnail_time = $request->input('thumbnailTime');
      $save->user = $request->input('uid');//Auth::id();
      $save->save();
      $tid = $save->tid;

      $xmlDetails = $request->input('xmlDetails');

      // Save each template field to the database
      $fields = $request->input('layers');
      if(!empty($fields) && $tid) {
        foreach($fields as $key => $field) {
          $saveFields = new FormFields;
          $saveFields->tid = $tid;
          $saveFields->layer = $key;//$field[];
          $saveFields->layer_type = $field['layerType'];//$field[];
          $saveFields->name = $field['fieldName'];
          $saveFields->type = $field['fieldType'];
          $saveFields->description = $field['fieldDescription'];
          $saveFields->default_val = $field['fieldDefault'];
          //$saveFields->layer_meta = $field['fieldMeta'] ?? null;
          $saveFields->length_max = $field['fieldMax'] ?? null;
          $saveFields->grp = $field['fieldGrp'] ?? null;
          $saveFields->save();
        }
      }

      // Output
      return json_encode($output);
      //return json_encode($request->input());
    }

    private function fixBool($str) {
      // Turn "true/false" or "yes/no" into 1/0
      if(strtolower($str[0]) === 't' || strtolower($str[0]) === 'y') {
        $str = 1;
      } else {
        $str = 0;
      }
      return $str;
    }
}
