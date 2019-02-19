<?php
namespace App;

use DOMDocument;
use SimpleXMLElement;
use App\Branding;
use App\Scala;

class ModifiedProcessXml
{
  private $endTime = 0;

  public function process($filename, $data, $thumbnailTime)
  {
    $xml = $filename;

    // Convert XML to object
    libxml_use_internal_errors(true);
    $xmlObj = simplexml_load_string($xml, null, LIBXML_NOCDATA);
    if ($xmlObj === false) {
      return( libxml_get_errors() );
    }

    // Load the template metadata to an array
    $xmlMeta = $this::loadMeta($xmlObj);
    //print_r($xmlMeta);die();

    // Load the branding data into the XML object
    $xmlObj = $this::loadBrandingData($xmlObj, $data, $xmlMeta);
    //$xmlFinal = $this::objToXml($xmlObj);die($xmlFinal);

    // Load the template data into the XML object
    $xmlObj = $this::loadTemplateData($xmlObj, $data, $xmlMeta);
    //print_r($xmlObj);die();

    // Convert object back into XML
    $xmlFinal = $this::objToXml($xmlObj);
    //die($xmlFinal);

    $json = $this::renderModified($xmlFinal, $data, $thumbnailTime);

    return $json;

  }


  // Load template metadata
  public function loadMeta($xmlObj)
  {
    $xmlMeta = [];
    $result = $xmlObj->xpath('//layer');
    foreach ($result as $node) {
      $title = urldecode($node['title']);
      if(strpos($title,'{') !== false) {
        $pos = strpos($title,'{');
        $jsonStr = substr($title,$pos);
        $json = json_decode($jsonStr,1);
        $key = (string)$node['title'];
        $xmlMeta[$key] = $json;
        $xmlMeta[$key]['json'] = $jsonStr;

        // Adjust cie value if necessary
        if(isset($json['cie'])) {
          if($json['cie'] == 1 || $json['cie'][0] == '-') {
            $end = preg_replace("/[^0-9]/", "", $json['cie'] );
            $orgEnd = $end;
            $children = $node->children();
            foreach($children as $child) {
              if($child['begin'] > $end && !isset($child['type'])) {
                $end = (int)$child['begin'];
              }
              $xmlMeta[$key]['cie'] = $end - $orgEnd;
            }
          }
          if($json['cie'][0] == '/') {
            $add = preg_replace("/[^0-9]/", "", $json['cie'] );
            $start = 0;
            $xmlMeta[$key]['cie_add'] = $add;
            $children = $node->children();
            foreach($children as $child) {
              if($child['begin'] > $start && !isset($child['type'])) {
                $start = (int)$child['begin'];
              }
              $xmlMeta[$key]['cie_start'] = $start;
            }
          }
        }
      }
    }
    return $xmlMeta;
  }


  // Apply branding data
  public function loadBrandingData($xmlObj,$data,$xmlMeta)
  {

    // Non OEM-specific data (logo)
    foreach ($xmlMeta as $layer => $meta) {
      // Apply branding to image elements
      $result = $xmlObj->xpath('//*[@title="' . $layer . '"]/image|//*[@title="' . $layer . '"]/video');
      if(!empty($result)) {
        foreach ($result as $node) {
          // Apply logotype (dealer logo or none)
          if(isset($xmlMeta[$layer]['clo']) && isset($data['template']['logotype'])) { // && isset($data['template']['logotype'])
            if($data['template']['logotype'] == 'dealer') {
              $id = $data['template']['acct']; // '20170';
              $white = 0;
              if($xmlMeta[$layer]['clo'] == 'w') {
                $white = 1;
              }
              $Scala = new Scala();
              $output['logo'] = $Scala->get_logo($id);
              if($white) {
                $output['logo'] = str_replace('.png','_WHITE.png',$output['logo']);
              }
              $node->attributes()->src = $output['logo'];//'http://10.1.10.141/img/abnlogo.png';
            } else {
              $node->attributes()->src = '';
            }

          }
        }
      }
    }

    // If OEM was passed in, apply branding template
    if($data['template']['oem'] ?? '') {
      $brandTemplate = Branding::getBranding($data['template']['oem'])->first();
      if ($brandTemplate) {
        $brandTemplate->toArray();
      }

      // Apply branding to text and html elements
      foreach ($xmlMeta as $layer => $meta) {
        $result = $xmlObj->xpath('//*[@title="' . $layer . '"]/html|//*[@title="' . $layer . '"]/text');
        if(!empty($result)) {
          foreach ($result as $node) {
            $modified = false;
            $content = (string)$node[0];

            // Copy all children as a separate object to reinsert later
            $children = $node->children();
            $childXML = '';
            foreach($children as $child) {
              $childXML .= $child->asXML();
            }
            $childObj = new SimpleXMLElement('<c>'.$childXML.'</c>');

            /* Obsolete
            // Apply bfc meta (Brand Font Color)
            if(isset($xmlMeta[$layer]['bfc']) && isset($brandTemplate['bfc'])) {
              $json = json_decode($brandTemplate['bfc'],1);
              $bfc = $json[$xmlMeta[$layer]['bfc']];
              $brand_pattern = '/color: rgb\(.*?\);/';
              $brand_replace = 'color: rgb(' . $bfc . ');';
              $content = preg_replace($brand_pattern, $brand_replace, $content);
              $node[0] = $content;
              $modified = true;
            }

            // Apply bff meta (Brand Font Face)
            if(isset($xmlMeta[$layer]['bff']) && isset($brandTemplate['bff'])) {
              $json = json_decode($brandTemplate['bff'],1);
              $bff = $json[$xmlMeta[$layer]['bff']] ?? $json['r'];
              $brand_pattern = '/font-family: .*?;/';
              $brand_replace = 'font-family: ' . $bff . ';';
              $content = preg_replace($brand_pattern, $brand_replace, $content);
              $node[0] = $content;
              $modified = true;
            }

            // Apply bbc meta (Brand Background Color)
            if(isset($xmlMeta[$layer]['bbc']) && isset($brandTemplate['bbc'])) {
              $json = json_decode($brandTemplate['bbc'],1);
              $bbc = $json[$xmlMeta[$layer]['bbc']];
              $bbcArr = explode(',',$bbc);
              $hex = sprintf("#%02x%02x%02x", $bbcArr[0], $bbcArr[1], $bbcArr[2]);
              $node->attributes()->backgroundColor = $hex;
            }
            */

            // Apply bta meta (Brand Theme Adjust)
            // X,Y coordinate adjustment per OEM
            if(isset($xmlMeta[$layer]['bta'][strtolower($data['template']['oem'])])) {
              $json = json_decode($brandTemplate['bta'],1);
              $bta = $xmlMeta[$layer]['bta'][strtolower($data['template']['oem'])];
              $btaArr = explode(',',$bta);
              $x = $node->attributes()->left + $btaArr[0];
              $y = $node->attributes()->top + $btaArr[1];
              $node->attributes()->left = $x;
              $node->attributes()->top = $y;
            }

            // Apply bts meta (Brand Theme Style, replacement for bff, bfc, bbc)
            // This adds CSS-esque "classes" to an element. Colors can be overridden with bto
            if(isset($xmlMeta[$layer]['bts']) && isset($brandTemplate['bts'])) {
              $json = json_decode($brandTemplate['bts'],1);
              $class = $xmlMeta[$layer]['bts'];
              // if there's no h1i defined, use h1
              if($class == 'h1i' && !isset($json['h1i'])) {
                $class = 'h1';
              }
              // if there's no li defined, use p
              if($class == 'li' && !isset($json['li'])) {
                $class = 'p';
              }
              $bts = $json[$class] ?? '';
              if(isset($bts['font'])) {
                $brand_pattern = '/font-family: .*?;/';
                $brand_replace = 'font-family: ' . $bts['font'] . ';';
                $content = preg_replace($brand_pattern, $brand_replace, $content);
              }
              if(isset($bts['size'])) {
                $brand_pattern = '/font-size: (.*?)px;/';
                preg_match($brand_pattern, $content, $matches);
                $orgSize = $matches[1];
                $newSize = $orgSize + (int)$bts['size'];
                $brand_replace = 'font-size: ' . $newSize . 'px;';
                $content = preg_replace($brand_pattern, $brand_replace, $content);
              }
              if(isset($bts['color']) && !isset($xmlMeta[$layer]['bto'][strtolower($data['template']['oem'])])) {
                if ($class == 'hr' || $class == 'bg') {
                  // If hr or bg, apply color to background
                  $colors = explode(',',$bts['color']);
                  $hex = sprintf("#%02x%02x%02x", $colors[0], $colors[1], $colors[2]);
                  $node->attributes()->backgroundColor = $hex;
                } else {
                  $brand_pattern = '/color: rgb\(.*?\);/';
                  $brand_replace = 'color: rgb(' . $bts['color'] . ');';
                  $content = preg_replace($brand_pattern, $brand_replace, $content);
                }
              }
              if(isset($bts['prefix'])) {
                $content = str_replace('{{', $bts['prefix'] . '{{', $content);
              }
              if(isset($bts['caps'])) {
                $brand_pattern = '/span style="/';
                $brand_replace = 'span style="text-transform: uppercase; ';
                $content = preg_replace($brand_pattern, $brand_replace, $content);
              }
              //print_r($content);die();
              $node[0] = $content;
              $modified = true;
            }

            // Apply bto meta (Brand Theme Override)
            //print_r($brandTemplate['bto']);die('end');
            if(isset($xmlMeta[$layer]['bto'][strtolower($data['template']['oem'])]) && isset($brandTemplate['bto'])) {
              $theme = $xmlMeta[$layer]['bto'][strtolower($data['template']['oem'])];
              $json = json_decode($brandTemplate['bto'],1);
              $bto = $json[$theme];
              if(trim(strip_tags($node[0]))) {
                // Text has content, so assume color replace
                $brand_pattern = '/color: rgb\(.*?\);/';
                $brand_replace = 'color: rgb(' . $bto . ');';
                $content = preg_replace($brand_pattern, $brand_replace, $content);
                $node[0] = $content;
                $modified = true;
              } else {
                // If empty text content, assume background
                $colors = explode(',',$bto);
                $hex = sprintf("#%02x%02x%02x", $colors[0], $colors[1], $colors[2]);
                $node->attributes()->backgroundColor = $hex;
              }

            }

            // Add the children back
            if($modified) {
              $this::addSubtree($node,$childObj);
            }
          }
        }

        // Apply branding to motion elements

        $result = $xmlObj->xpath('//*[@title="' . $layer . '"]/motionTitle');
        if(!empty($result)) {
          foreach ($result as $node) {

            // Apply mtc meta (Motion Title Color)
            if(isset($xmlMeta[$layer]['mtc']) && isset($brandTemplate['bfc'])) {
              $bfc = json_decode($brandTemplate['bfc'],1);
              foreach ($node->colors->color as $color) {
                $key = strval($color['key']);
                if(isset($bfc[ $xmlMeta[$layer]['mtc'][$key] ])) {
                  $color->attributes()->value = "rgba(" . $bfc[ $xmlMeta[$layer]['mtc'][$key] ] . ",1)";
                }
              }
            }

            // Apply mtf meta (Motion Title Font)
            if(isset($xmlMeta[$layer]['mtf']) && isset($brandTemplate['bff'])) {
              $bff = json_decode($brandTemplate['bff'],1);
              $i = 0;
              foreach ($node->lines->line as $line) {
                if(isset($bff[ $xmlMeta[$layer]['mtf'][$i] ])) {
                  $line->attributes()->font = $bff[ $xmlMeta[$layer]['mtf'][$i] ];
                }
                $i++;
              }
            }

          }
        }

        // Apply branding to image elements
        $result = $xmlObj->xpath('//*[@title="' . $layer . '"]/image|//*[@title="' . $layer . '"]/video');
        if(!empty($result)) {
          foreach ($result as $node) {
            // Apply bit meta (Brand Image Tint)
            if(isset($xmlMeta[$layer]['bit']) && isset($brandTemplate['bit'])) {
              $json = json_decode($brandTemplate['bit'],1);
              $bit = $json[$xmlMeta[$layer]['bit']];
              $bitArr = explode(',',$bit);
              if(isset($node->filter)) {
                if($node->filter->attributes()->type == 'colorCorrection') {
                  $filter = $node->filter;
                  $filter->attributes()->brightness = $bitArr[0];
                  $filter->attributes()->contrast = $bitArr[1];
                  $filter->attributes()->hue = $bitArr[2];
                  $filter->attributes()->saturation = $bitArr[3];
                  $filter->attributes()->type = 'colorCorrection';
                }
              } else {
                $filter = $node->addChild("filter");
                $filter->addAttribute('brightness', $bitArr[0]);
                $filter->addAttribute('contrast', $bitArr[1]);
                $filter->addAttribute('hue', $bitArr[2]);
                $filter->addAttribute('saturation', $bitArr[3]);
                $filter->addAttribute('type', 'colorCorrection');
              }
            }

            // Apply bir meta (Brand Image Replace)
            if(isset($xmlMeta[$layer]['bir']) && isset($brandTemplate['bir'])) {
              $json = json_decode($brandTemplate['bir'],1);
              $bir = $json[$xmlMeta[$layer]['bir']];
              $node->attributes()->src = $bir;
            }



            // Apply bis meta (Brand Image Swap)
            if(isset($xmlMeta[$layer]['bis'])) {
              $oem = $data['template']['oem'];
              $folderId = $xmlMeta[$layer]['bis'];
              $folder = json_decode($this->getFolder($folderId),1);
              $url = '';
              foreach($folder['data'] as $file) {
                if(stripos($file['title'],$oem) !== false) {
                  $url = $file['url'];
                  $node->attributes()->src = $url;
                  break;
                }
              }
            }

          }
        }

      }
    }
    return $xmlObj;
  }

  // Fill in brackets with supplied data
  public function loadTemplateData($xmlObj,$data,$xmlMeta)
  {
    $type = '';
    $foundBlank = 0;
    $callback = function ($match) use (&$type, &$replacement, &$index, &$foundBlank) {
      if($type == 'list') {
        $index++;
        $pattern = '/{{([\s\S]*?)}}/';
        preg_match_all($pattern, $replacement, $matches);
        if(isset($matches[1][$index - 1])) {
          $matchIndex = $matches[1][$index - 1];
          if(!trim($matchIndex) && ($index < $foundBlank || !$foundBlank)) {
            $foundBlank = $index;
          }
          return $matchIndex;
        } else {
          return '';
        }
      }
      return nl2br($replacement);
    };
    foreach($data['templateFields'] as $field) {
      // Find and replace TEXT or HTML
      $result = $xmlObj->xpath('//*[@title="' . $field['layer'] . '"]/html|//*[@title="' . $field['layer'] . '"]/text');
      if(!empty($result)) {
        foreach ($result as $node) {
          $pattern = '/{{([\s\S]*?)}}/';
          $content = (string)$node[0];

          // Copy all children as a separate object to reinsert later
          $children = $node->children();
          $childXML = '';
          foreach($children as $child) {
            $childXML .= $child->asXML();
          }
          $childObj = new SimpleXMLElement('<c>'.$childXML.'</c>');

          if(substr_count($content, '{{') > 1) {
            $type = 'list';
          }

          // Apply user values to template
          $replacement = $field['content'];
          $index = 0;
          $newContent = preg_replace_callback($pattern, $callback, $content);
          $node[0] = $newContent;

          // Look for the earliest termination point (cie meta)
          if($type != 'list'
            && !trim($replacement)
            && isset($xmlMeta[$field['layer']]['cie'])) {
              $cie = $xmlMeta[$field['layer']]['cie'];
              if($cie < $this->endTime || $this->endTime == 0) {
                $this->endTime = $cie;
              }
          }
          if($type == 'list'
            && $foundBlank
            && isset($xmlMeta[$field['layer']])
            && isset($xmlMeta[$field['layer']]['cie_start'])) {
              $layer = $xmlMeta[$field['layer']];
              $this->endTime = $layer['cie_start'] + ($layer['cie_add'] * $foundBlank);
          }

          $type = '';

          // Add the children back
          $this::addSubtree($node,$childObj);

        }
      }

      // Find and replace IMAGE
      $result = $xmlObj->xpath('//*[@title="' . $field['layer'] . '"]/image');
      if(!empty($result)) {
        foreach ($result as $node) {
          $node['src'] = $field['content'];
        }
      }

      // Find and replace MOTIONTITLE
      $result = $xmlObj->xpath('//*[@title="' . $field['layer'] . '"]/motionTitle');
      if(!empty($result)) {
        foreach ($result as $node) {
          $i = 0;
          $pattern = '/{{([\s\S]*?)}}/';
          preg_match_all($pattern, $field['content'], $matches);
          foreach ($matches[1] as $line) {
            $newStr = preg_replace($pattern, $line, $node->children()->lines->line[$i]);
            if($newStr) {
              $node->children()->lines->line[$i] = $newStr;
            } else {
              $newStr = str_replace('{{','', str_replace('}}','',$node->children()->lines->line[$i]) );
              $node->children()->lines->line[$i] = $newStr;
            }
            $i++;
          }
        }
      }

    }

    return $xmlObj;
  }


  // Insert SimpleXML object into another SimpleXML object
  private function addSubtree(&$xml1, &$xml2)
  {
      $dom1 = dom_import_simplexml($xml1);
      $dom2 = dom_import_simplexml($xml2);
      $dom2 = $dom1->ownerDocument->importNode($dom2, TRUE);
      $dom1->appendChild($dom2);
  }


  // Submit the XML to WeVideo
  public function renderModified($xmlFinal, $data, $thumbnailTime = 5)
  {
    $thumbnailTime *= 1000;

    $xmlFinal = $this->embedFonts($xmlFinal);

    $server = env('WEVIDEO_SERVER', 'www');
    $key = env('WEVIDEO_KEY', 'fvoFkqX2WtDkYmTUI9Cw3nJaBnoka2TVXV9THfvg');
    $postdata = array('version' => '1', 'content' => $xmlFinal, 'resolution' => '1080p', 'crf' => '20', 'fps' => '25', 'thumbnailTime' => $thumbnailTime);
    if($this->endTime) {
      $postdata['endTime'] = $this->endTime;
    }
    $postdata = json_encode($postdata,false);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://$server.wevideo.com/api/3/videos/create");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
      "Authorization: WEVSIMPLE $key",
      "Content-Type: application/json"
    ));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $output = curl_exec ($ch);
    curl_close ($ch);
    return $output;
  }


  // Convert SimpleXml object back into XML
  public function objToXml($xmlObj)
  {
    $doc = new DOMDocument();
    $doc->formatOutput = TRUE;
    $doc->loadXML($xmlObj->asXML());
    $xmlFinal = $doc->saveXML();

    // Remove any insertion points
    $xmlFinal = str_replace('<c>','',str_replace('</c>','',$xmlFinal));

    return($xmlFinal);
  }


  // Get job status from WeVideo
  public function jobStatus($jobId)
  {
    $server = env('WEVIDEO_SERVER', 'www');
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL,"http://$server.wevideo.com/api/3/videos/status/$jobId");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $output = curl_exec ($ch);
    curl_close ($ch);
    return $output;
  }


  // Get folder contents from WeVideo
  // This API call is undocumented by WeVideo
  public function getFolder($folder)
  {
    $server = env('WEVIDEO_SERVER', 'www');
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL,"http://www.wevideo.com/api/3/media/$folder/files");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $output = curl_exec ($ch);
    curl_close ($ch);
    return $output;
  }


  // Embed all font-face CSS fonts
  private function embedFonts($xml)
  {
    $callback = function ($match) {
      $fontName = $match[1];
      $filename = public_path()."/fonts/$fontName.woff";
      if(is_readable($filename)) {
        $base64 = base64_encode(file_get_contents($filename));
          return '@font-face {font-family: "'.$fontName.'"; font-style: normal; font-weight: 400; src: url(data:application/font-woff;charset=utf-8;base64,'.$base64.') format("woff");}';
      }
    };
    $pattern = '/@font-face {font-family: "(.*?)";}/';
    $xml = preg_replace_callback($pattern, $callback, $xml);
    return $xml;
  }

}
