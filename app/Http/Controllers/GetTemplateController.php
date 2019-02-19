<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Template;
use App\FormFields;
use App\ProcessXml;
use App\Scala;
use App\Sugar;
use Illuminate\Support\Facades\Auth;

class GetTemplateController extends Controller
{
  public function __construct()
  {
    //$this->middleware('auth');
  }

  public function index(Request $request)
  {
    $id = $request->input('id') ?? 0;
    $oem = $request->input('oem') ?? '';
    $oems = $request->input('oems') ?? '';
    // ID was passed in
    if($id) {
      $template = Template::getTemplate($id)->get();
      $fields = FormFields::getFormFields($id)->get();

      $output['template'] = $template;
      $output['templateFields'] = $fields;

      $xml = $template[0]['template'];

      $processXml = new ProcessXml();
      $output['xmlDetails'] = json_decode($processXml->process($xml,''),1);
      $output['xmlDetails']['filename'] = '';

      // Get info for each image
      if(!empty($fields)){
        foreach ($fields as $field) {
          if($field['layer_type'] === 'image') {
            foreach ($output['xmlDetails']['elements'] as $k => $v) {
              if($v['title'] == $field['layer']) {
                $size = getimagesize($v['content']);
                $output['imgDetails'][$field['layer']] = $size;
                $output['imgDetails'][$field['layer']]['url'] = $v['content'];
              }
            }
            /*preg_match('/{.*?}/',urldecode($field['layer']), $meta);
            $metaArr = json_decode($meta[0],1);
            $output['imgDetails'][$field['layer']]['meta'] = $meta[0];
            if(isset($metaArr['clo'])) {
              $Scala = new Scala();
              $output['imgDetails'][$field['layer']]['logoUrl'] = $Scala->get_logo(20170);
            }*/
          }
        }
      }

    }
    
    // OEM was passed in
    else if($oem) {
      if(strtolower($oem) == 'all') {$oem = '';}
      $templates = Template::getTemplatesByOem($oem)->get();
      $output['templates'] = $templates;
    }

    // Multiple OEMs passed in
    else if($oems) {
      $oemsGood = array();
      $oemArr = explode(',',$oems);
      $oemArr[] = 'all';
      $templates = Template::sortTemplates()->get();
      $templates = json_decode(json_encode($templates),1);
      foreach($templates as $k => $v) {
          foreach($oemArr as $oemQuery) {
            if(stripos($v['oems'],$oemQuery) !== false) {
              $oemsGood[$k] = $k;
            }
          }
        if(!isset($oemsGood[$k])) {
          unset($templates[$k]);
        }
      }
      $output['templates'] = $templates;
    }

    // Return all templates
    else {
      $templates = Template::sortTemplates()->get();
      $output['templates'] = $templates;
    }


    return json_encode($output);
  }

  public function delete(Request $request)
  {
    $this->middleware('auth');

    $id = $request->input('id') ?? 0;
    if(isset($id)) {
      $deleteTemplate = Template::getTemplate($id)->delete();
      if($deleteTemplate) {
        $deleteRows = FormFields::where('tid', $id)->delete();
        $output['success'] = "Template deleted.";
      } else {
        $output['error'] = "Template not found.";
      }

      return $output;
    }
  }

  public function getLogo(Request $request)
  {
    $id = $request->input('id') ?? 0;
    $white = $request->input('white') ?? 0;
    if($id) {
      $Scala = new Scala();
      $output['logo'] = $Scala->get_logo($id);
      if($white) {
        $output['logo'] = str_replace('.png','_WHITE.png',$output['logo']);
      }
      return $output;
    } else {
      return '';
    }
  }

}
