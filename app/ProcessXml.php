<?php
namespace App;

use DOMDocument;

class ProcessXml
{
    public function process($filename, $orgFilename)
    {
      if (!function_exists('public_path')) {
        function public_path($path = null) {
          return rtrim(app()->basePath('public/' . $path), '/');
        }
      }
      if (!function_exists('storage_path')) {
        function storage_path($path = null) {
          return rtrim(app()->basePath('storage/' . $path), '/');
        }
      }

      if($filename[0] != '<') {
        // Filename passed in, load it to a string
        //$xml = file_get_contents(public_path()."/uploads/xml/$filename");
        $xml = file_get_contents(storage_path()."/app/public/xml/$filename");
      } else {
        // XML string was passed in, use as-is
        $xml = $filename;
      }


      // Convert XML to object
      libxml_use_internal_errors(true);
      $xmlObj = simplexml_load_string($xml, null, LIBXML_NOCDATA);
      if ($xmlObj === false) {
        return( libxml_get_errors() );
      }

      //$xmlObj = $this::cleanup($xmlObj);

      // Convert object back into XML
      $xmlFinal = $this::objToXml($xmlObj);

      //$xmlFinal = $this::textToHtml($xmlFinal);
      //$xmlFinal = $this::postProcess($xmlFinal);

      $json = $this::generateJsonManifest($xmlObj,$xmlFinal, $filename,$orgFilename);

      return $json;

      //print_r($xmlFinal);
      //die();

    }


  // WeVideo generates non-compliant XML out of the gate. The following cleans it up
  public function cleanup($xmlObj) {
    //$this::removeNode($xmlObj, "//text/@processHtml");
    //$this::removeNode($xmlObj, "//text/@volume");
    //$this::removeNode($xmlObj, "//text/filter");

    // added per suggestion from WeVideo support
    $result = $xmlObj->xpath("//text");
    foreach ($result as $node) {
      $parent = $node->xpath("parent::*");
      $meta = urldecode($parent[0]['title']);
      if(strpos($meta,'{') !== false) {
        $meta = explode('{',$meta);
        $meta = '{' . trim($meta[1]);
        $node->addAttribute('meta',$meta);
      }

      //$node->addAttribute('autoFit','false');
      // debugging purposes
      //$color = substr(md5(rand()), 0, 6);
      //$node->addAttribute('backgroundColor','#'.$color);
    }
    return ($xmlObj);
  }

  // Remove XML node via xpath
  private function removeNode($xmlObj, $xpath) {
    $result = $xmlObj->xpath($xpath);
    foreach ($result as $node) {
        unset($node[0]);
    }
    return($xmlObj);
  }

  // Submit the XML to WeVideo
  // https://wevideo-static.s3.amazonaws.com/APIdocs/VideoCreationAPI/index.html
  public function render($xmlFinal, $thumbnailTime = 5, $orientation = 'H') {
    $thumbnailTime *= 1000; // convert to milliseconds

    if(!$orientation) {$orientation = 'H';}
    $resolution = '1080p';
    if($orientation == 'V') {$resolution = '1080x1920';}

    $xmlFinal = $this->embedFonts($xmlFinal);

    $server = env('WEVIDEO_SERVER', 'www');
    $key = env('WEVIDEO_KEY', 'fvoFkqX2WtDkYmTUI9Cw3nJaBnoka2TVXV9THfvg');
    $postdata = array('version' => '1', 'content' => $xmlFinal, 'resolution' => $resolution, 'crf' => '20', 'fps' => '29.97', 'thumbnailTime' => $thumbnailTime);
    $postdata = json_encode($postdata,false);
    //echo $postdata;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://$server.wevideo.com/api/3/videos/create");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
      "Authorization: WEVSIMPLE $key",
      "Content-Type: application/json"
    ));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    //curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $output = curl_exec ($ch);
    curl_close ($ch);
    return $output;
  }

  // Convert WeVideo timeline to API format
  public function convertTimeline($timelineId) {
    $server = env('WEVIDEO_SERVER', 'www');
    $key = env('WEVIDEO_KEY', 'fvoFkqX2WtDkYmTUI9Cw3nJaBnoka2TVXV9THfvg');
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://$server.wevideo.com/api/3/videos/convert/$timelineId");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
      "Authorization: WEVSIMPLE $key",
    ));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $output = curl_exec ($ch);
    curl_close ($ch);
    return $output;
  }

  // Perform fixes on the XML data
  // Not used anymore. It was easier to convert TEXT elements to HTML to avoid
  // all the associated idiosyncrasies
  public function postProcess($xmlFinal) {
    // Change text tag contents back into CDATA
    // Apparently it's not necessary to do, so we won't do it

    //$pattern = '/(\<text )(.*?)(\>)(.*?)(\r\n|\r|\n)/';
    /*$pattern = '/(\<text )(.*?)(\>)(.*?)(\<)/';
    $xmlFinal = preg_replace_callback($pattern, 'fixCDATA', $xmlFinal);

    function fixCDATA ($match) {
      $cdata = "<![CDATA[" . html_entity_decode($match[4] . ']]');
      return $match[1].$match[2].">" . $cdata . "\r\n<";
    }

    print_r($xmlFinal);
    die();*/

    // Divide line sizes
    $pattern = '/(line-height:)(.*?)(;)/';
    //$xmlFinal = preg_replace_callback($pattern, 'fixLineHeight', $xmlFinal);

    function fixLineHeight ($match) {
      //$lineHeight = $match[2];
      //if($lineHeight == 'normal') {$lineHeight = 50;}
      $lineHeight = 60;
      return $match[1].' '.$lineHeight.'%;';
    }

    // Double font sizes
    $pattern = '/(font-size:)(.*?)(px;)/';
    //$xmlFinal = preg_replace_callback($pattern, 'fixSize', $xmlFinal);

    function fixSize ($match) {
      $sizeMultiplier = (400 - $match[2]) / 8;
      $size = ($match[2] * 2.5) + $sizeMultiplier;
      //if($size > 400) {$size = 400;}
      $size = round($size);
      return $match[1].' '.$size.$match[3];
    }
  }

  // Creates the JSON output for the frontend
  public function generateJsonManifest($xmlObj,$xmlFinal,$filename,$orgFilename) {
    // Loop through layers and grab relevant data
    $json = [];

    if(!isset($xmlObj->layers->layer)) {return "XmlObj Error";}
    foreach ($xmlObj->layers->layer as $layer) {
      $title = strval($layer->attributes()->title);
      $friendlyTitle = $this->friendlyName($title);

      // Metadata in layer name?
      if(strpos(urldecode($title),'{') !== false) {
        //$title = $this->friendlyName($title);
        $titleArr = explode('{', urldecode($title), 2); //explode('{', $title, 2);
        $friendlyTitle = trim($titleArr[0]);
        $json['elements'][$friendlyTitle]['meta'] = '{' . trim($titleArr[1]);
      }
      $json['elements'][$friendlyTitle]['title'] = $title; //rawurlencode($friendlyTitle);

      // Layer has TEXT elements
      if (property_exists($layer, 'text')) {
        // text layer is a list
        if(substr_count(strval($layer->text), '{{') > 1) {
          $json['elements'][$friendlyTitle]['type'] = 'list';
          $json['elements'][$friendlyTitle]['content'] = strval($layer->text);

          $pattern = '/{{(.*?)}}/';
          $check_hash = preg_match_all($pattern, strval($layer->text), $items);
          $listArr = [];
          foreach ($items[1] as $item){
            $listArr[] = strip_tags($item);
          }
          $json['elements'][$friendlyTitle]['contentText'] = '{{' . implode('}}{{',$listArr) . '}}';
        } else {
          // text layer is not a list
          $json['elements'][$friendlyTitle]['type'] = 'text';
          $json['elements'][$friendlyTitle]['content'] = strval($layer->text);
          $contentText = $this->removeExtraSpaces(strip_tags(strval($layer->text)));
          $contentText = str_replace('{{','',str_replace('}}','',$contentText));
          $json['elements'][$friendlyTitle]['contentText'] = $contentText;
          //$json['elements'][$friendlyTitle]['contentTemplate'] = str_replace(trim(strip_tags(strval($layer->text))),'{{content}}', strval($layer->text));
        }
      }

      // Layer has IMAGE elements
      if (property_exists($layer, 'image')) {
        $json['elements'][$friendlyTitle]['type'] = 'image';
        $json['elements'][$friendlyTitle]['content'] = strval($layer->image->attributes()->src);
      }

      // Layer has VIDEO elements
      if (property_exists($layer, 'video')) {
        $json['elements'][$friendlyTitle]['type'] = 'video';
        $json['elements'][$friendlyTitle]['content'] = strval($layer->video->attributes()->src);
      }

      // Layer has MOTIONTITLE elements
      if (property_exists($layer, 'motionTitle')) {
        $json['elements'][$friendlyTitle]['type'] = 'motion';
        $contentText = '';
        foreach ($layer->motionTitle->lines->line as $line) {
          $contentText .= '{{' . trim($line[0]) . '}}';
        }
        $json['elements'][$friendlyTitle]['contentText'] = $contentText;
        $json['elements'][$friendlyTitle]['content'] = $contentText;
      }
    }

    //$xmlFixed = $this->objToXml($this->objRemoveMeta($xmlObj));

    $json['filename'] = $filename;
    $json['orgFilename'] = $orgFilename;
    $json['xmlFinal'] = $xmlFinal;//$xmlFixed;

    $json = json_encode($json, JSON_PRETTY_PRINT);
    return $json;
  }


  // Remove metadata from layer titles (and apply friendly title names)
  public function objRemoveMeta($xmlObj) {
    $result = $xmlObj->xpath("//layer/@title");
    foreach ($result as $node) {
      $title = $this->friendlyName($node[0]);
      //$titleArr = explode('%20%7B', $title, 2);
      $titleArr = explode('{', $title, 2);
      $node[0] = rawurlencode( trim($titleArr[0]) );
    }
    return $xmlObj;
  }


  // Convert SimpleXml object back into XML
  public function objToXml($xmlObj) {
    $doc = new DOMDocument();
    $doc->formatOutput = TRUE;
    $doc->loadXML($xmlObj->asXML());
    $xmlFinal = $doc->saveXML();
    return($xmlFinal);
  }

  // Convert TEXT tags to HTML tags
  // TEXT tags render improperly in the API compared to the WeVideo interface
  // HTML tags render much more closely to intentional results (with some hackery)
  public function textToHtml($xmlFinal) {
    $callback = function ($match) {
      // Gather attributes
      $attributes = $match[1];
      preg_match_all('/(.*?)="(.*?)"/', $attributes, $attrArr);
      foreach($attrArr[1] as $k => $v) {
        $attrs[trim($v)] = trim($attrArr[2][$k]);
      }
      $attributes = 'begin="'. ($attrs['begin'] ?? '') .'" duration="'. ($attrs['duration'] ?? '') .'"';

      // Gather list of fonts
      preg_match_all('/font-family:(.*?);/', $match[2], $fontArr);
      foreach($fontArr[1] as $k => $v) {
        $fonts[] = trim($v);
      }
      $fonts = array_unique($fonts ?? []);
      $fontStr = '';
      if (!empty($fonts)) {
        foreach($fonts as $font) {
          $fontStr .= $this::loadFont($font);
        }
      }

      // HTML element doesn't allow children afaik
      $children = $match[3];

      $width = ($attrs['width'] ?? '0') . 'px';
      $height = ($attrs['height'] ?? '0') . 'px';
      $top = ($attrs['top'] ?? '0') . 'px';
      $left = ($attrs['left'] ?? '0') . 'px';

      // Get metadata from layer name
      if(isset($attrs['meta'])) {
        $match[1] = preg_replace('/meta=".*?"/', '', $match[1]);
        $meta = json_decode(html_entity_decode($attrs['meta']),1);
      }
      // Apply debug mode (deb)
      $debug = '';
      if(isset($meta['deb'])) {
        $debug = 'border:2px dotted #f00';
      }$debug = 'border:2px dotted #f00';
      // Apply CSS (css)
      $css = '';
      if(isset($meta['css'])) {
        $css = $meta['css'];
      }
      // Apply rotation (rot)
      $rot = '';
      if(isset($meta['rot'])) {
        $rot = 'transform:rotate('. $meta['rot'] .'deg);';
      }

      // Wrap content around TEXT tag contents
      $html = "<![CDATA[";
      $html .= '<html><head>
      <style>body {overflow:hidden;}';
      $html .= $fontStr;
      $html .= '</style>
      </head><body>
    ';
      $html .= "<div style='$debug;$rot;$css;padding:25px 20px;position:absolute;width:$width;height:$height;top:$top;left:$left;'>";
      $html .= html_entity_decode($match[2]);
      $html .= "</div>";
      $html .= "</body></html>";
      $html .= "]]>";
      // If the element has no content, then just leave it as text
      if(!strip_tags(html_entity_decode($match[2]))) {
        //preg_match_all('/background-color:(.*?);/', $match[2], $bgColor);
        //$bgColor = trim($bgColor[1][0]);
        $match[1] = preg_replace('/meta=".*?"/', '', $match[1]);
        return "<text $match[1]>$match[2]$match[3]</text>";
      }
      // If there's metadata as an attribute, we need to remove it
      if(isset($attrs['meta'])) {
        // The meta noc flag is set, so do not render as HTML
        if($meta['noc'] ?? false) {
          return "<text $match[1]>$match[2]$match[3]</text>";
        }
      }
      return "<html ".$attributes.">" . $html . $children . "</html>";
    };

    $pattern = '/\<text (.*?)\>(.*)([\s\S]*?)\<\/text>/';
    $xmlFinal = preg_replace_callback($pattern, $callback, $xmlFinal);
    return $xmlFinal;
  }

  // Get job status from WeVideo
  public function jobStatus($jobId) {
    $server = env('WEVIDEO_SERVER', 'www');
    // Test Job ID: '2018_05_04_317594ef-6955-4399-aaad-bc4838a79ca6'

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL,"http://$server.wevideo.com/api/3/videos/status/$jobId");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $output = curl_exec ($ch);
    curl_close ($ch);
    return $output;
  }

  // Load and embed a font as CSS
  private function loadFont($fontName, $base64 = false) {
    $filename = public_path()."/fonts/$fontName.woff";
    if(!$base64) {
      return PHP_EOL.'@font-face {font-family: "'.$fontName.'";}'.PHP_EOL;
    } else {
      if(is_readable($filename)) {
        $base64 = base64_encode(file_get_contents($filename));
          return PHP_EOL.'@font-face {font-family: "'.$fontName.'"; font-style: normal; font-weight: 400; src: url(data:application/font-woff;charset=utf-8;base64,'.$base64.') format("woff");}'.PHP_EOL;
      }
    }
  }

  // Embed all font-face CSS fonts
  private function embedFonts($xml) {
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

  // Generate a cleaned up layer name
  private function friendlyName($uglyName) {
    $name = urldecode($uglyName);
    $name = str_replace('_', ' ', $name);
    $name = ucwords($name);
    $name = $this->removeExtraSpaces($name);
    return $name;
  }

  // Remove extra spaces
  private function removeExtraSpaces($str) {
    $str = trim(preg_replace('/\s\s+/', ' ', str_replace("\n", " ", $str)));
    return $str;
  }
}
