<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\ModifiedTemplate;
use App\ModifiedFields;
use App\ModifiedProcessXml;
use Carbon\Carbon;
//use Illuminate\Support\Facades\Auth;

class ModifyTemplateController extends Controller
{
    public function __construct()
    {
        //$this->middleware('auth');
    }

    public function index(Request $request)
    {
      //$this->validate($request, [
      //  'templateName' => 'required',
      //]);

      $data = json_decode($request->getContent(), true);
      $fields = $data['templateFields'];
      //print_r($data);die();

      // Check for cropped images
      if(!empty($fields)) {
        foreach($fields as $key => $field) {
          // Field passed over an image
          if(isset($field['crop']) && !empty($field['crop'])){
            // Decode image
            $cropdata = explode(',',$field['crop']['src']);
            $mime = str_replace('data:','', str_replace(';base64','',$cropdata[0]) );
            $cropdata = base64_decode($cropdata[1]);
            $time = Carbon::now();
            $filename = str_random(5).date_format($time,'d').rand(1,9).date_format($time,'h');

            $im = imagecreatefromstring($cropdata);
            imagealphablending($im, true);
            if($im !== false) {
              // Crop the image
              $im2 = imagecrop($im, [
                'x' => $field['crop']['x'],
                'y' => $field['crop']['y'],
                'width' => $field['crop']['w'],
                'height' => $field['crop']['h']
              ]);
              if($im2 !== false) {
                if($mime == 'image/png') {
                  // Save PNG
                  //header('Content-Type: ' . $mime);
                  $extension = 'png';
                  $filename .= ".".$extension;
                  imagealphablending($im2, true);
                  imagesavealpha($im2, true);
                  imagepng($im2, 'uploads/img/' . $filename);
                } else {
                  // Save JPEG
                  $extension = 'jpg';
                  $filename .= ".".$extension;
                  imagejpeg($im2, 'uploads/img/' . $filename);
                }

                // Upload image to CDN and set the content to result URL
                $awsUpload = app('App\Http\Controllers\AwsController')->uploadImage($filename);
                if($awsUpload) {
                  // Update URL data so it gets processed and saved correctly
                  $fields[$key]['content'] = $awsUpload;
                  $data['templateFields'][$key]['content'] = $awsUpload;
                }

                imagedestroy($im2);
              } else {
                die('{"error":"Could not create cropped image!"}');
              }
              imagedestroy($im);
            } else {
              die('{"error":"Could not create image!"}');
            }
          }
        }
      }


      // Handle OPTIONS method. Chrome requires a response due to CORS
      if ($request->isMethod('options'))
      {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE, PATCH');
        header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Key, Authorization');
        header('Access-Control-Allow-Credentials: true');
        exit(0);
      }

      // If HTTP PATCH method, set id so it'll update instead of insert
      if ($request->isMethod('patch') && isset($data['template']['mtid']))
      {
        $mtid = $data['template']['mtid'];
        $save = ModifiedTemplate::findOrFail($mtid);
        // Remove fields so they can be reinserted below
        $deleteRows = ModifiedFields::where('mtid', $mtid)->delete();
      } else {
        $save = new ModifiedTemplate;
      }

      // Create a modified XML and render it
      $load = new ModifiedTemplate;
      $template = $load->getTemplate($data['template']['tid']);
      $templateData = $load->getTemplateData($data['template']['tid']);
      $thumbnailTime = $templateData['thumbnail_time'];
      $process = new ModifiedProcessXml;
      $renderResult = $process->process($template,$data,$thumbnailTime);
      $output['render'] = json_decode($renderResult,1);
      //die($renderResult);

      $renderOnly = $data['template']['renderOnly'] ?? false;
      //if($renderOnly) {
      //  return($renderResult);
      //}

      if(isset($data['template']['playlists'])) {
        $playlists = implode(',', $data['template']['playlists']);
      }

      if(isset($data['template']['publish']) && $data['template']['publish']) {
        $publish = 1;
      }

      $startdate = null;
      if(isset($data['template']['startdate'])) {
        $startdate = date('Y-m-d H:i:s',strtotime(str_replace('-','/',$data['template']['startdate'])));
      }
      $enddate = null;
      if(isset($data['template']['startdate'])) {
        $enddate = date('Y-m-d H:i:s',strtotime(str_replace('-','/',$data['template']['enddate'])));
      }


      // Save the modified template to database
      $save->tid = $data['template']['tid'];
      $save->acct = $data['template']['acct'];
      $save->acct_name = $data['template']['acctName'];
      $save->name_modified = $data['template']['name_modified'];
      $save->oem = $data['template']['oem'] ?? null;
      $save->logotype = $data['template']['logotype'] ?? null;
      $save->publish = $publish ?? null;
      $save->username = $data['template']['username'] ?? null;
      $save->playlists = $playlists ?? null;
      $save->start_date =  $startdate;
      $save->end_date = $enddate;
      $save->job_id = $output['render']['jobId'] ?? null;
      $save->job_status = null;
      $save->url_modified = null;
      $save->thumbnail_modified = null;
      if(!$renderOnly) {
        $save->save();
      }
      $mtid = $save->mtid;

      // Save each modified template field to the database
      if(!empty($fields)) {
        foreach($fields as $key => $field) {
          $saveFields = new ModifiedFields;
          $saveFields->mtid = $mtid ?? 0;
          $saveFields->layer = $field['layer'];
          $saveFields->content = $field['content'];
          if(!$renderOnly) {
            $saveFields->save();
          }

        }
      }

      // Output
      if(!$renderOnly) {
        $output['success'] = 'Modified template created successfully.';
      } else {
        $output['success'] = 'Modified template creation simulated successfully.';
      }
      $output['mtid'] = $mtid;
      return json_encode($output);
      //return json_encode($request->input());
    }

    public function get($id)
    {
      if($id) {
        $template = ModifiedTemplate::getModifiedTemplate($id)->get();
        $fields = ModifiedFields::getModifiedFields($id)->get();

        $output['template'] = $template[0];
        $output['templateFields'] = $fields;
        return json_encode($output);
      } else {
        return '{"success":false, "message":"ID required."}';
      }
    }

    public function list(Request $request,$mode,$id,$filter=null)
    {
      $filter = $request->input('filter') ?? '';
      if($id) {
        if($mode == 'oems' || $mode == 'categories' || $mode == 'tags') {
          if(env('DB_CONNECTION') == 'sqlite') {
            $templates = ModifiedTemplate::getModifiedTemplatesLike($mode,$id)->get();
          } else {
            $templates = ModifiedTemplate::getModifiedTemplatesFind($mode,$id)->get();
          }

        }
        else {
          if(!$filter) {
            $templates = ModifiedTemplate::getModifiedTemplates($mode,$id)->get();
          } else {
            $templates = ModifiedTemplate::getModifiedTemplates($mode,$id)->where('grp',$filter)->get();
            //return "!!".$filter;
          }

        }
        foreach($templates as $temp) {
          unset($temp['template']);
          $output['templates'] = $templates;
        }

        if(!isset($output)) {
          return '{"success":false, "message":"0 records found."}';
        }
        return json_encode($output);
      } else {
        return '{"success":false, "message":"Account number required."}';
      }
    }

    public function delete(Request $request)
    {
      //$this->middleware('auth');
      $id = $request->input('id') ?? 0;
      if(isset($id)) {
        $modifiedTemplate = new ModifiedTemplate;
        $deleteTemplate = $modifiedTemplate->GetModifiedTemplate($id)->delete();
        if($deleteTemplate) {
          $deleteRows = ModifiedFields::where('mtid', $id)->delete();
          $output['success'] = "Modified Template deleted.";
        } else {
          $output['error'] = "Modified Template not found.";
        }

        return $output;
      }
    }

}
