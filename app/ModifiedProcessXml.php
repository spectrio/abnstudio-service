<?php
namespace App;

use App\Branding;
use App\WeVideo;
use DOMDocument;
use Log;
use SimpleXMLElement;

class ModifiedProcessXml
{
    private $endTime = 0;
    private $templateDuration = 0;

    private function normalizePlaceholders($content)
    {
        $pattern = '/{{([^}]*(?:<[^>]+>[^}]*)*)}}/';

        $normalized = preg_replace_callback($pattern, function($matches) {
            $innerContent = $matches[1];
            $text = strip_tags($innerContent);

            Log::info('normalizePlaceholders callback - innerContent: ' . substr($innerContent, 0, 200));
            Log::info('normalizePlaceholders callback - extracted text: ' . $text);

            Log::info('normalizePlaceholders callback - returning normalized placeholder without extra wrapping: {{' . $text . '}}');
            return '{{' . $text . '}}';
        }, $content);

        Log::info('Normalized placeholders - FULL Before: ' . substr($content, 0, 500));
        Log::info('Normalized placeholders - FULL After: ' . substr($normalized, 0, 500));
        Log::info('Normalization removed ' . (strlen($content) - strlen($normalized)) . ' characters of markup');
        return $normalized;
    }

    private function convertTextKenBurnsToPosition($xmlObj)
    {
        Log::info("=== CONVERTING TEXT KENBURNS TO POSITION KEYFRAMES ===");

        $layersWithTextAndKenBurns = $xmlObj->xpath('//layer[text and filter[@type="kenBurns"]]');
        Log::info("Found " . count($layersWithTextAndKenBurns) . " layers with text elements and kenBurns filters");

        foreach ($layersWithTextAndKenBurns as $layer) {
            $textElement = $layer->text;
            $filter = $layer->xpath('filter[@type="kenBurns"]')[0];
            $layerTitle = (string)$layer['title'];

            Log::info("Processing kenBurns filter on text layer: " . urldecode($layerTitle));
            Log::info("  Original filter - startTop: " . $filter['startTop'] . ", endTop: " . $filter['endTop']);

            if (isset($textElement->top)) {
                Log::info("  Top animation already exists, skipping");
                continue;
            }

            $top = $textElement->addChild('top');
            $startEntry = $top->addChild('entry');
            $startEntry->addAttribute('time', $textElement['begin']);
            $startEntry->addAttribute('value', $filter['startTop']);

            $endEntry = $top->addChild('entry');
            $endEntry->addAttribute('time', (int)$textElement['begin'] + (int)$textElement['duration']);
            $endEntry->addAttribute('value', $filter['endTop']);

            Log::info("  Created top animation: time " . $textElement['begin'] . " -> " . ((int)$textElement['begin'] + (int)$textElement['duration']));
            Log::info("  Position values: " . $filter['startTop'] . " -> " . $filter['endTop']);

            $dom = dom_import_simplexml($filter);
            $dom->parentNode->removeChild($dom);

            Log::info("  Removed kenBurns filter, replaced with position keyframes");
        }

        Log::info("=== KENBURNS CONVERSION COMPLETE ===");
        return $xmlObj;
    }

    public function process($filename, $data, $thumbnailTime, $orientation, $dtv)
    {
        $xml = $filename;

        Log::info("XML being processed (first 2000 chars): ".substr($xml, 0, 2000));

        libxml_use_internal_errors(true);
        $xmlObj = simplexml_load_string($xml, null, LIBXML_NOCDATA);
        if ($xmlObj === false) {
            return (libxml_get_errors());
        }

        $this->loadXmlData($xmlObj);

        $xmlMeta = $this->loadMeta($xmlObj);
        Log::info("XML Metadata loaded: ".print_r(array_keys($xmlMeta), true));

        $allLayers = $xmlObj->xpath('//layer[@title]');
        Log::info("All layer titles in XML:");
        foreach ($allLayers as $layer) {
            Log::info("  - ".(string)$layer['title']);
        }

        $xmlObj = $this->loadBrandingData($xmlObj, $data, $xmlMeta);

        $xmlObj = $this->loadTemplateData($xmlObj, $data, $xmlMeta);

        $xmlObj = $this->convertTextKenBurnsToPosition($xmlObj);

        if (!$dtv) {
            $this->removeNode($xmlObj, '//audio/..');
        }

        $xmlFinal = $this->objToXml($xmlObj);

        $xmlFinal = $this->convertToVersionThreeLinks($xmlFinal);

        $json = $this->renderModified($xmlFinal, $data, $thumbnailTime, $orientation, $dtv);

        return $json;

    }

    public function loadXmlData($xmlObj)
    {
        $result = $xmlObj->xpath('//*[@duration]');
        foreach ($result as $node) {
            $begin = urldecode($node['begin']);
            $duration = urldecode($node['duration']);
            $end = $begin + $duration;
            if ($end > $this->templateDuration) {
                $this->templateDuration = $end;
            }
        }
        return;
    }

    public function loadMeta($xmlObj)
    {
        Log::info("=== LOADMETA START ===");
        $xmlMeta = [];
        $result = $xmlObj->xpath('//layer');
        Log::info("Found " . count($result) . " layers to process");

        foreach ($result as $node) {
            $title = urldecode($node['title']);
            $encodedTitle = (string) $node['title'];
            Log::info("Processing layer: '{$title}' (encoded: '{$encodedTitle}')");

            if (strpos($title, '{') !== false) {
                $pos = strpos($title, '{');
                $jsonStr = substr($title, $pos);
                $json = json_decode($jsonStr, 1);
                $key = (string) $node['title'];
                $xmlMeta[$key] = $json;
                $xmlMeta[$key]['json'] = $jsonStr;

                Log::info("  Layer has metadata: " . json_encode($json));

                Log::info("  Checking for normalization...");
                $children = $node->children();
                Log::info("  Found " . count($children) . " child elements");

                foreach ($children as $child) {
                    $childName = $child->getName();
                    Log::info("    Child element type: {$childName}");



                    if ($childName == 'text' || $childName == 'html') {
                        $content = (string) $child[0];
                        $placeholderCount = substr_count($content, '{{');
                        $contentPreview = substr($content, 0, 200);

                        Log::info("    Content preview: {$contentPreview}");
                        Log::info("    Placeholder count: {$placeholderCount}");
                        Log::info("    Has cie metadata: " . (isset($json['cie']) ? 'YES (' . $json['cie'] . ')' : 'NO'));

                        if ($placeholderCount > 1) {
                            Log::info("    List layer detected with {$placeholderCount} placeholders - keeping cie metadata intact");
                        }
                    }
                }

                if (isset($json['cie'])) {
                    Log::info("  Processing cie metadata: " . $json['cie']);

                    if ($json['cie'] == 1 || $json['cie'][0] == '-') {
                        Log::info("  cie type: single value or negative");
                        $end = preg_replace('/[^0-9]/', '', $json['cie']);
                        $orgEnd = $end;
                        $children = $node->children();
                        foreach ($children as $child) {
                            if ($child['begin'] > $end && !isset($child['type'])) {
                                $end = (int) $child['begin'];
                            }
                            $xmlMeta[$key]['cie'] = $end - $orgEnd;
                        }
                        Log::info("  Adjusted cie value: " . $xmlMeta[$key]['cie']);
                    }
                    if ($json['cie'][0] == '/') {
                        Log::info("  cie type: list (starts with /)");
                        $add = preg_replace('/[^0-9]/', '', $json['cie']);
                        $start = 0;
                        $xmlMeta[$key]['cie_add'] = $add;
                        $children = $node->children();
                        foreach ($children as $child) {
                            if ($child['begin'] > $start && !isset($child['type'])) {
                                $start = (int) $child['begin'];
                            }
                            $xmlMeta[$key]['cie_start'] = $start;
                        }
                        Log::info("  Set cie_add: {$add}, cie_start: {$start}");
                    }
                } else {
                    Log::info("  No cie metadata to process (removed or never existed)");
                }
            } else {
                Log::info("  Layer has no metadata");
            }
        }

        Log::info("=== LOADMETA END ===");
        Log::info("Final xmlMeta keys: " . implode(', ', array_keys($xmlMeta)));
        return $xmlMeta;
    }

    public function loadBrandingData($xmlObj, $data, $xmlMeta)
    {

        foreach ($xmlMeta as $layer => $meta) {
            $result = $xmlObj->xpath('//*[@title="'.$layer.'"]/image|//*[@title="'.$layer.'"]/video');
            if (!empty($result)) {
                foreach ($result as $node) {
                    // Apply logotype (dealer logo or none)
					// If "dealer", automatically detect light or dark based on template
					if (isset($xmlMeta[$layer]['clo']) && isset($data['template']['logotype'])) {
                        if ($data['template']['logotype'] == 'dealer') {
                            $id = $data['template']['acct'];
                            $white = 0;
                            if ($xmlMeta[$layer]['clo'] == 'w') {
                                $white = 1;
                            }
                            $output['logo'] = $this->getLogo($id);
                            if ($white) {
                                $output['logo'] = str_replace('logo_original', 'alternate_logo_original', $output['logo']);
                            }
                            $node->attributes()->src = $output['logo'];
                        } else {
                            if ($data['template']['logotype'] == 'dark' || $data['template']['logotype'] == 'light') {


                                $id = $data['template']['acct'];
                                $output['logo'] = $this->getLogo($id);
                                if ($data['template']['logotype'] == 'dark') {
                                    $output['logo'] = str_replace('logo_original', 'alternate_logo_original', $output['logo']);
                                }
                                $node->attributes()->src = $output['logo'];
                            } else {
                                if ($data['template']['logotype'] == 'blank') {
                                    $node->attributes()->src = '';
                                }
                            }
                        }

                    }
                }
            }
        }

        // If OEM was passed in, apply branding template
        if ($data['template']['oem'] ?? '') {
            $brandTemplate = Branding::getBranding($data['template']['oem'])->first();
            if ($brandTemplate) {
                $brandTemplate->toArray();
            }

            // Apply branding to text and html elements
            foreach ($xmlMeta as $layer => $meta) {
                $result = $xmlObj->xpath('//*[@title="'.$layer.'"]/html|//*[@title="'.$layer.'"]/text');
                if (!empty($result)) {
                    foreach ($result as $node) {
                        $modified = false;
                        $content = (string) $node[0];

                        // Copy all children as a separate object to reinsert later
                        $children = $node->children();
                        $childXML = '';
                        foreach ($children as $child) {
                            $childXML .= $child->asXML();
                        }
                        $childObj = new SimpleXMLElement('<c>'.$childXML.'</c>');

                        if (isset($xmlMeta[$layer]['bta'][strtolower($data['template']['oem'])])) {
                            $json = json_decode($brandTemplate['bta'], 1);
                            $bta = $xmlMeta[$layer]['bta'][strtolower($data['template']['oem'])];
                            $btaArr = explode(',', $bta);
                            $x = $node->attributes()->left + $btaArr[0];
                            $y = $node->attributes()->top + $btaArr[1];
                            $node->attributes()->left = $x;
                            $node->attributes()->top = $y;
                        }

                        // Apply bts meta (Brand Theme Style, replacement for bff, bfc, bbc)
                        // This adds CSS-esque "classes" to an element. Colors can be overridden with bto
                        if (isset($xmlMeta[$layer]['bts']) && isset($brandTemplate['bts'])) {
                            $json = json_decode($brandTemplate['bts'], 1);
                            $class = $xmlMeta[$layer]['bts'];
                            // if there's no h1i defined, use h1
                            if ($class == 'h1i' && !isset($json['h1i'])) {
                                $class = 'h1';
                            }
                            // if there's no li defined, use p
                            if ($class == 'li' && !isset($json['li'])) {
                                $class = 'p';
                            }
                            $bts = $json[$class] ?? '';
                            if (isset($bts['font'])) {
                                $brand_pattern = '/font-family: .*?;/';
                                $brand_replace = 'font-family: '.$bts['font'].';';
                                $content = preg_replace($brand_pattern, $brand_replace, $content);
                            }
                            if (isset($bts['size'])) {
                                $brand_pattern = '/font-size: (.*?)px;/';
                                preg_match($brand_pattern, $content, $matches);
                                $orgSize = $matches[1];
                                $newSize = $orgSize + (int) $bts['size'];
                                $brand_replace = 'font-size: '.$newSize.'px;';
                                $content = preg_replace($brand_pattern, $brand_replace, $content);
                            }
                            if (isset($bts['color']) && !isset($xmlMeta[$layer]['bto'][strtolower($data['template']['oem'])])) {
                                if ($class == 'hr' || $class == 'bg') {
                                    // If hr or bg, apply color to background
                                    $colors = explode(',', $bts['color']);
                                    $hex = sprintf('#%02x%02x%02x', $colors[0], $colors[1], $colors[2]);
                                    $node->attributes()->backgroundColor = $hex;
                                } else {
                                    $brand_pattern = '/color: rgb\(.*?\);/';
                                    $brand_replace = 'color: rgb('.$bts['color'].');';
                                    $content = preg_replace($brand_pattern, $brand_replace, $content);
                                }
                            }
                            if (isset($bts['prefix'])) {
                                $content = str_replace('{{', $bts['prefix'].'{{', $content);
                            }
                            if (isset($bts['caps'])) {
                                $brand_pattern = '/span style="/';
                                $brand_replace = 'span style="text-transform: uppercase; ';
                                $content = preg_replace($brand_pattern, $brand_replace, $content);
                            }
                            $node[0] = $content;
                            $modified = true;
                        }

                        // Apply bto meta (Brand Theme Override)
                        if (isset($xmlMeta[$layer]['bto'][strtolower($data['template']['oem'])]) && isset($brandTemplate['bto'])) {
                            $theme = $xmlMeta[$layer]['bto'][strtolower($data['template']['oem'])];
                            $json = json_decode($brandTemplate['bto'], 1);
                            $bto = $json[$theme];
                            if (trim(strip_tags($node[0]))) {
                                $brand_pattern = '/color: rgb\(.*?\);/';
                                $brand_replace = 'color: rgb('.$bto.');';
                                $content = preg_replace($brand_pattern, $brand_replace, $content);
                                $node[0] = $content;
                                $modified = true;
                            } else {
                                $colors = explode(',', $bto);
                                $hex = sprintf('#%02x%02x%02x', $colors[0], $colors[1], $colors[2]);
                                $node->attributes()->backgroundColor = $hex;
                            }

                        }

                        if ($modified) {
                            $this->addSubtree($node, $childObj);
                        }
                    }
                }

                $result = $xmlObj->xpath('//*[@title="'.$layer.'"]/motionTitle');
                if (!empty($result)) {
                    foreach ($result as $node) {

                Log::debug('Applying mtc color');
                        if (isset($xmlMeta[$layer]['mtc']) && isset($brandTemplate['bfc'])) {
                $bfc = json_decode($brandTemplate['bfc'], 1);
                Log::debug('bfc: '.print_r($bfc,true));
                            foreach ($node->colors->color as $color) {
                $key = strval($color['key']);
                Log::debug('key: '.$key);
                Log::debug('layer: '.print_r($xmlMeta[$layer],true));
                if (isset($xmlMeta[$layer]['mtc'][$key]) && isset($bfc[$xmlMeta[$layer]['mtc'][$key]])) {
                    Log::debug('key '.$key.' is set');
                                    $color->attributes()->value = 'rgba('.$bfc[$xmlMeta[$layer]['mtc'][$key]].',1)';
                                }
                            }
                        }

                        if (isset($xmlMeta[$layer]['mtf']) && isset($brandTemplate['bff'])) {
                            $bff = json_decode($brandTemplate['bff'], 1);
                            $i = 0;
                            foreach ($node->lines->line as $line) {
                                if (isset($bff[$xmlMeta[$layer]['mtf'][$i]])) {
                                    $line->attributes()->font = $bff[$xmlMeta[$layer]['mtf'][$i]];
                                }
                                $i++;
                            }
                        }
                    }
                }

                $result = $xmlObj->xpath('//*[@title="'.$layer.'"]/image|//*[@title="'.$layer.'"]/video');
                if (!empty($result)) {
                    foreach ($result as $node) {
                        if (isset($xmlMeta[$layer]['bit']) && isset($brandTemplate['bit'])) {
                            $json = json_decode($brandTemplate['bit'], 1);
                            $bit = $json[$xmlMeta[$layer]['bit']];
                            $bitArr = explode(',', $bit);
                            if (isset($node->filter)) {
                                if ($node->filter->attributes()->type == 'colorCorrection') {
                                    $filter = $node->filter;
                                    $filter->attributes()->brightness = $bitArr[0];
                                    $filter->attributes()->contrast = $bitArr[1];
                                    $filter->attributes()->hue = $bitArr[2];
                                    $filter->attributes()->saturation = $bitArr[3];
                                    $filter->attributes()->type = 'colorCorrection';
                                }
                            } else {
                                $filter = $node->addChild('filter');
                                $filter->addAttribute('brightness', $bitArr[0]);
                                $filter->addAttribute('contrast', $bitArr[1]);
                                $filter->addAttribute('hue', $bitArr[2]);
                                $filter->addAttribute('saturation', $bitArr[3]);
                                $filter->addAttribute('type', 'colorCorrection');
                            }
                        }

                        if (isset($xmlMeta[$layer]['bir']) && isset($brandTemplate['bir'])) {
                            $json = json_decode($brandTemplate['bir'], 1);
                            $bir = $json[$xmlMeta[$layer]['bir']];
                            $node->attributes()->src = $bir;
                        }

                        // Apply bis meta (Brand Image Swap)
                        if (isset($xmlMeta[$layer]['bis'])) {
                            $oem = $data['template']['oem'];
                            $folderId = $xmlMeta[$layer]['bis'];
                            $WeVideo = new WeVideo();
                            $folder = $WeVideo->get_media($folderId);
                            //$folder = json_decode($this->getFolder($folderId),1);
                            $url = '';
                            $title = '';
                            foreach ($folder['data'] as $file) {
                                if (stripos($file['title'], $oem) !== false) {
                                    $urlParts = parse_url($file['url']);
                                    $urlExplode = explode('/', $urlParts['path']);
                                    $urlExplode[2] = '3';
                                    $urlCombine = implode('/', $urlExplode);
                                    $url = $urlCombine;
                                    $title = $file['title'];
                                    $title = str_replace(' ', '+', $title);
                                    $title = 'chevy+compliant';
                                    $node->attributes()->src = $url;
                                    $node->attributes()->title = $title;
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

    public function convertToVersionThreeLinks($xml = null) {
        $pattern = '/\/api\/\d+/';
        if(!is_null($xml) && preg_match($pattern, $xml)) {
            return preg_replace($pattern, '/api/3', $xml);
        } else {
            return $xml;
        }
    }

    public function validateMediaRedirect($url = null)
    {
        if (!is_null($url)) {
            $mediaRedirect = [];
            $mediaRedirect['url'] = $url;
            $url = filter_var($url, FILTER_SANITIZE_URL);
            $mediaRedirect['sanitized'] = $url;
            $mediaRedirect['valid'] = filter_var($url, FILTER_VALIDATE_URL);
            if ($mediaRedirect['valid']) {
                $ch = curl_init();

                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

                $key = env('WEVIDEO_KEY', 'fvoFkqX2WtDkYmTUI9Cw3nJaBnoka2TVXV9THfvg');

                curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    'Authorization: WEVSIMPLE '.$key,
                    'Content-Type: application/json'
                ));

                curl_setopt($ch, CURLOPT_HEADER, true);

                // Timeout in seconds
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);

                $mediaRedirect['curl'] = curl_exec($ch);

                curl_close($ch);

                $headers = [];
                $output = rtrim($mediaRedirect['curl']);
                $data = explode("\n", $output);
                $headers['status'] = $data[0];
                array_shift($data);

                foreach ($data as $part) {
                    $middle = explode(':', $part,2);
                    if (!isset($middle[1])) {$middle[1] = null;}
                    $headers[trim($middle[0])] = trim($middle[1]);
                }
                $mediaRedirect['headers'] = $headers;
                if (array_key_exists('Location', $headers)) {
                    $url = filter_var($headers['Location'], FILTER_SANITIZE_URL);
                    $urlIsValid = filter_var($url, FILTER_VALIDATE_URL);
                    if ($urlIsValid) {
                        return $url;
                    }
                }
                Log::info($mediaRedirect);
            }
        }
        return $url;
    }

    public function loadTemplateData($xmlObj, $data, $xmlMeta)
    {
        $type = '';
        $foundBlank = 0;
        $callback = function ($match) use (&$type, &$replacement, &$index, &$foundBlank) {
            if ($type == 'list') {
                $index++;
                $pattern = '/{{([\s\S]*?)}}/';
                preg_match_all($pattern, $replacement, $matches);
                if (isset($matches[1][$index - 1])) {
                    $matchIndex = $matches[1][$index - 1];
                    if (!trim($matchIndex) && ($index < $foundBlank || !$foundBlank)) {
                        $foundBlank = $index;
                    }
                    return $matchIndex;
                } else {
                    return '';
                }
            }

            $replacement = str_replace("\r\n", "\n", $replacement);
            return str_replace("\n", '<br/>', $replacement);
        };
        foreach ($data['templateFields'] as $field) {
            Log::info("========== PROCESSING FIELD ==========");
            Log::info("Field layer: " . $field['layer']);
            Log::info("Field content: " . $field['content']);
            Log::info("Field content length: " . strlen($field['content']));

            $xpath_query = '//*[@title="'.$field['layer'].'"]/html|//*[@title="'.$field['layer'].'"]/text';
            Log::info("XPath query: ".$xpath_query);
            $result = $xmlObj->xpath($xpath_query);
            Log::info("XPath result count: ".count($result));

            if (count($result) === 0) {
                Log::warning("WARNING: No layer found for: " . $field['layer']);
            }

        if (!empty($result)) {
            foreach ($result as $node) {
                $pattern = '/{{([\s\S]*?)}}/';
                $content = (string) $node[0];
                Log::info("Original node content (FULL): " . $content);

                $content = $this->normalizePlaceholders($content);

                $children = $node->children();
                $childXML = '';
                foreach ($children as $child) {
                    $childXML .= $child->asXML();
                }
                $childObj = new SimpleXMLElement('<c>'.$childXML.'</c>');

                if (substr_count($content, '{{') > 1) {
                    $type = 'list';
                    Log::info("Detected list type layer with " . substr_count($content, '{{') . " placeholders");
                }

                $replacement = $field['content'];
                $index = 0;
                $newContent = preg_replace_callback($pattern, $callback, $content);
                Log::info("Final content after replacement (FULL): " . $newContent);
                Log::info("Replacement value used: " . $replacement);
                $node[0] = $newContent;

                if ($type != 'list'
                    && !trim($replacement)
                    && isset($xmlMeta[$field['layer']]['cie'])) {
                    $cie = $xmlMeta[$field['layer']]['cie'];
                    if ($cie < $this->endTime || $this->endTime == 0) {
                        $this->endTime = $cie;
                    }
                    Log::info("Set endTime to: " . $this->endTime);
                }
                if ($type == 'list'


                    && $foundBlank
                    && isset($xmlMeta[$field['layer']])
                    && isset($xmlMeta[$field['layer']]['cie_start'])) {
                    $layer = $xmlMeta[$field['layer']];
                    $this->endTime = $layer['cie_start'] + ($layer['cie_add'] * $foundBlank);
                    Log::info("=== LIST LAYER END TIME CALCULATION ===");
                    Log::info("Layer: " . $field['layer']);
                    Log::info("cie_start: " . $layer['cie_start']);
                    Log::info("cie_add: " . $layer['cie_add']);
                    Log::info("foundBlank (first empty item index): " . $foundBlank);
                    Log::info("Calculated endTime: " . $this->endTime . " = " . $layer['cie_start'] . " + (" . $layer['cie_add'] . " * " . $foundBlank . ")");
                    Log::info("Set endTime (list) to: " . $this->endTime);

                    if (isset($childObj->filter)) {
                        foreach ($childObj->filter as $filter) {
                            if ((string)$filter['type'] === 'kenBurns') {
                                $startTop = (float)$filter['startTop'];
                                $endTop = (float)$filter['endTop'];
                                $totalMovement = $endTop - $startTop;

                                $totalPlaceholders = substr_count($content, '{{');

                                Log::info("=== KENBURNS FILTER ADJUSTMENT ===");
                                Log::info("Original startTop: " . $startTop);
                                Log::info("Original endTop: " . $endTop);
                                Log::info("Total movement: " . $totalMovement);
                                Log::info("Total placeholders in template: " . $totalPlaceholders);
                                Log::info("Filled items (foundBlank): " . $foundBlank);

                                $movementPerItem = $totalMovement / $totalPlaceholders;
                                Log::info("Movement per item: " . $movementPerItem);

                                $newEndTop = $startTop + ($movementPerItem * $foundBlank);
                                Log::info("New endTop (to show only filled items): " . $newEndTop);

                                $filter['endTop'] = $newEndTop;
                                Log::info("kenBurns filter endTop updated from " . $endTop . " to " . $newEndTop);

                                break;
                            }
                        }
                    }
                } else if ($type == 'list') {
                    Log::info("=== LIST LAYER BUT NO END TIME CALCULATION ===");
                    Log::info("foundBlank: " . $foundBlank);
                    Log::info("Has xmlMeta for layer: " . (isset($xmlMeta[$field['layer']]) ? 'YES' : 'NO'));
                    if (isset($xmlMeta[$field['layer']])) {
                        Log::info("xmlMeta contents: " . json_encode($xmlMeta[$field['layer']]));
                        Log::info("Has cie_start: " . (isset($xmlMeta[$field['layer']]['cie_start']) ? 'YES' : 'NO'));
                    }
                }

                $type = '';

                $this->addSubtree($node, $childObj);

            }
        } else {
            Log::info("No TEXT or HTML found for layer: ".$field['layer']);
        }

        $result = $xmlObj->xpath('//*[@title="'.$field['layer'].'"]/image');
        Log::info("Looking for IMAGE elements in layer: ".$field['layer']." - found: " . count($result));
        if (!empty($result)) {
            foreach ($result as $node) {
                $node['src'] = $field['content'];
                Log::info("Image src set to: " . $field['content']);

                if (isset($xmlMeta[$field['layer']]['cie']) && strpos($field['content'], 'wevideo-images') !== false) {
                    $cie = $xmlMeta[$field['layer']]['cie'];
                    if ($cie < $this->endTime || $this->endTime == 0) {
                        $this->endTime = $cie;
                    }
                    Log::info("Image has cie metadata, set endTime to: " . $this->endTime);
                } else {
                    Log::info("Image cie check - has cie: " . (isset($xmlMeta[$field['layer']]['cie']) ? 'yes' : 'no') . ", contains wevideo-images: " . (strpos($field['content'], 'wevideo-images') !== false ? 'yes' : 'no'));
                }
            }
        } else {
            Log::info("No IMAGE elements found for layer: ".$field['layer']);
        }

        $result = $xmlObj->xpath('//*[@title="'.$field['layer'].'"]/motionTitle');
        Log::info("Looking for MOTIONTITLE elements - found: " . count($result));
        if (!empty($result)) {
            foreach ($result as $node) {
                    $i = 0;
                    $pattern = '/{{([\s\S]*?)}}/';
                    preg_match_all($pattern, $field['content'], $matches);
                    Log::info("Motion title - found " . count($matches[1]) . " lines to replace");
                    foreach ($matches[1] as $line) {
                        Log::info("Motion title line $i: " . $line);
                        $line = addcslashes($line, '$');
                        $newStr = preg_replace($pattern, $line, $node->children()->lines->line[$i]);
                        if ($newStr) {
                            $node->children()->lines->line[$i] = $newStr;
                        } else {
                            $newStr = str_replace('{{', '', str_replace('}}', '', $node->children()->lines->line[$i]));
                            $node->children()->lines->line[$i] = $newStr;
                        }
                        $i++;
                    }

                }
            }

        }
        return $xmlObj;
    }

    private function addSubtree(&$xml1, &$xml2)
    {
        $dom1 = dom_import_simplexml($xml1);
        $dom2 = dom_import_simplexml($xml2);
        $dom2 = $dom1->ownerDocument->importNode($dom2, true);
        $dom1->appendChild($dom2);
    }

    private function removeNode($xmlObj, $xpath)
    {
        $result = $xmlObj->xpath($xpath);
        foreach ($result as $node) {
            unset($node[0]);
        }
        return ($xmlObj);
    }

    public function renderModified($xmlFinal, $data, $thumbnailTime = 5, $orientation = 'H', $dtv = 0)
    {
        Log::info('=== RENDERMODIFIED START ===');
        Log::info('Orientation: ' . $orientation . ', DTV: ' . $dtv);
        Log::info('EndTime: ' . $this->endTime . ', TemplateDuration: ' . $this->templateDuration);

        $thumbnailTime *= 1000;

        if (!$orientation) {$orientation = 'H';}
        $resolution = '1080p';
        if ($orientation == 'V') {$resolution = '1080x1920';}

        if ($dtv) {$resolution = '932x524';}

        $xmlFinal = $this->embedFonts($xmlFinal);

        Log::info('Final XML to be sent (first 5000 chars): ' . substr($xmlFinal, 0, 5000));
        Log::info('Final XML to be sent (last 2000 chars): ' . substr($xmlFinal, -2000));

        $xmlDebugDir = storage_path('logs/xml_debug');
        if (!is_dir($xmlDebugDir)) {
            mkdir($xmlDebugDir, 0755, true);
        }
        $timestamp = date('Y-m-d_His');
        $xmlDebugFile = $xmlDebugDir . '/wevideo_xml_' . $timestamp . '.xml';
        file_put_contents($xmlDebugFile, $xmlFinal);
        Log::info('*** COMPLETE XML saved to: ' . $xmlDebugFile);
        Log::info('*** XML file size: ' . filesize($xmlDebugFile) . ' bytes');

        $server = env('WEVIDEO_SERVER', 'www');
        $key = env('WEVIDEO_KEY', 'fvoFkqX2WtDkYmTUI9Cw3nJaBnoka2TVXV9THfvg');
        $postdata = array('version' => '1', 'content' => $xmlFinal, 'resolution' => $resolution, 'crf' => '20', 'fps' => '29.97', 'thumbnailTime' => $thumbnailTime);


        if ($this->endTime) {
            $postdata['endTime'] = $this->endTime;
            Log::info('Including endTime in post data: ' . $this->endTime);
        }
        $postdata = json_encode($postdata, false);
        Log::info('Post data size: ' . strlen($postdata) . ' bytes');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://$server.wevideo.com:443/api/3/videos/create");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Authorization: WEVSIMPLE $key",
            'Content-Type: application/json'
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADERFUNCTION,
        function($curl, $header) use (&$headers)
            {
                $len = strlen($header);
                $header = explode(':', $header, 2);
                if (count($header) < 2)
                return $len;

                $headers[strtolower(trim($header[0]))][] = trim($header[1]);

                return $len;
            }
        );
        $output = curl_exec($ch);

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        Log::info("*** WeVideo API HTTP Status Code: " . $httpCode);

        if (curl_errno($ch)) {
            $curlError = curl_error($ch);
            Log::error("*** CURL ERROR: " . $curlError);
            Log::error("*** CURL Error Number: " . curl_errno($ch));
        }

        Log::info("XML Post response headers: ".print_r($headers,true));
        curl_close($ch);

        Log::info("*** XML post response (RAW): ".print_r($output,true));
        Log::info("*** XML post response length: " . strlen($output) . " bytes");

        $decodedOutput = json_decode($output, 1);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error("*** JSON DECODE ERROR: " . json_last_error_msg());
            Log::error("*** Response was not valid JSON");
        }

        if (is_array($decodedOutput)) {
            if (isset($decodedOutput['error'])) {
                Log::error("*** WEVIDEO API ERROR: " . print_r($decodedOutput['error'], true));
            }
            if (isset($decodedOutput['message'])) {
                Log::info("*** WeVideo API Message: " . $decodedOutput['message']);
            }
            if (isset($decodedOutput['id'])) {
                Log::info("*** WeVideo Job ID created: " . $decodedOutput['id']);
            }
            if (isset($decodedOutput['status'])) {
                Log::info("*** WeVideo Job Status: " . $decodedOutput['status']);
            }
        }

        $output = $decodedOutput;
        $output['endTime'] = $this->endTime;
        $output['templateDuration'] = $this->templateDuration;
        $output = json_encode($output, 1);
        Log::info('=== RENDERMODIFIED END ===');
        return $output;
    }

    public function jobStatus($jobId)
    {
        Log::info('=== CHECKING JOB STATUS ===');
        Log::info('Job ID: ' . $jobId);

        $server = env('WEVIDEO_SERVER', 'www');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://$server.wevideo.com:443/api/3/videos/status/$jobId");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec($ch);

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        Log::info("*** Job Status API HTTP Code: " . $httpCode);

        if (curl_errno($ch)) {
            $curlError = curl_error($ch);
            Log::error("*** Job Status CURL ERROR: " . $curlError);
            Log::error("*** CURL Error Number: " . curl_errno($ch));
        }

        curl_close($ch);

        Log::info('Job Status Response (RAW): '.print_r($output, true));
        Log::info('Job Status Response Length: ' . strlen($output) . ' bytes');

        $decodedStatus = json_decode($output, 1);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error("*** Job Status JSON DECODE ERROR: " . json_last_error_msg());
        }

        if (is_array($decodedStatus)) {
            if (isset($decodedStatus['error'])) {
                Log::error("*** WEVIDEO JOB STATUS ERROR: " . print_r($decodedStatus['error'], true));
            }
            if (isset($decodedStatus['status'])) {
                Log::info("*** Current Job Status: " . $decodedStatus['status']);
            }
            if (isset($decodedStatus['url'])) {
                Log::info("*** Video URL: " . $decodedStatus['url']);
            }


            if (isset($decodedStatus['thumbnailUrl'])) {
                Log::info("*** Thumbnail URL: " . $decodedStatus['thumbnailUrl']);
            }
            if (isset($decodedStatus['progress'])) {
                Log::info("*** Progress: " . $decodedStatus['progress'] . "%");
            }
            if (isset($decodedStatus['message'])) {
                Log::info("*** WeVideo Message: " . $decodedStatus['message']);
            }
        }

        Log::info('=== JOB STATUS CHECK COMPLETE ===');
        return $output;
    }

    public function objToXml($xmlObj)
    {
        $doc = new DOMDocument();
        $doc->formatOutput = true;
        $doc->loadXML($xmlObj->asXML());
        $xmlFinal = $doc->saveXML();

        $xmlFinal = str_replace('<c>', '', str_replace('</c>', '', $xmlFinal));

        Log::info("=== FINAL XML CONVERSION ===");
        Log::info("Final XML length: " . strlen($xmlFinal));

        $layersInFinalXml = substr_count($xmlFinal, '<layer');
        $imagesInFinalXml = substr_count($xmlFinal, '<image');
        $textsInFinalXml = substr_count($xmlFinal, '<text');
        $htmlsInFinalXml = substr_count($xmlFinal, '<html');

        Log::info("Final XML - Layers: $layersInFinalXml, Images: $imagesInFinalXml, Texts: $textsInFinalXml, HTMLs: $htmlsInFinalXml");

        preg_match_all('/<image[^>]*src="([^"]*)"/', $xmlFinal, $imageSources);
        Log::info("Image sources in final XML:");
        foreach ($imageSources[1] as $idx => $src) {
            $srcPreview = strlen($src) > 100 ? substr($src, 0, 100) . '...' : $src;
            Log::info("  Image " . ($idx + 1) . ": " . ($src === '' ? 'EMPTY' : $srcPreview));
        }

        preg_match_all('/<video[^>]*src="([^"]*)"/', $xmlFinal, $videoSources);
        Log::info("Video sources in final XML:");
        foreach ($videoSources[1] as $idx => $src) {
            $srcPreview = strlen($src) > 100 ? substr($src, 0, 100) . '...' : $src;
            Log::info("  Video " . ($idx + 1) . ": " . ($src === '' ? 'EMPTY' : $srcPreview));
        }

        return ($xmlFinal);
    }

    public function getFolder($folder)
    {
        $server = env('WEVIDEO_SERVER', 'www');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://www.wevideo.com:443/api/3/media/$folder/files");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }

    private function embedFonts($xml)
    {
        $callback = function ($match) {
            $fontName = $match[1];
            $filename = public_path()."/fonts/$fontName.woff";
            if (is_readable($filename)) {
                $base64 = base64_encode(file_get_contents($filename));
                return '@font-face {font-family: "'.$fontName.'"; font-style: normal; font-weight: 400; src: url(data:application/font-woff;charset=utf-8;base64,'.$base64.') format("woff");}';
            }
        };
        $pattern = '/@font-face {font-family: "(.*?)";}/';
        $xml = preg_replace_callback($pattern, $callback, $xml);
        return $xml;
    }

    public function getLogo($account)
    {
        $output = 'https://player.abn.live/api/v2/accounts/'.$account.'/logo_original';
        return $output;
    }

    public function getLogoOld($account, $x)
    {
        $output = array();
        $output['img'] = 'https://player.abn.live/api/v2/accounts/'.$account.'/logo_large';
        $output['imgSize'] = getimagesize($output['img']);

        $output['newWidth'] = 0;
        $output['newHeight'] = 0;
        $output['yAdj'] = 0;

        if (isset($x)) {
            $xSize = $x;
            if (!isset($y)) {
                $ySize = $xSize / 1.96;
            } else {
                $ySize = $y;
            }

            $originalWidth = $output['imgSize'][0];
            $originalHeight = $output['imgSize'][1];

            $ratio = $originalWidth / $originalHeight;

            $targetWidth = $targetHeight = min($xSize, max($originalWidth, $originalHeight));

            if ($ratio < 1) {
                $targetWidth = round($targetHeight * $ratio);
            } else {
                $targetHeight = round($targetWidth / $ratio);
            }

            $srcWidth = $originalWidth;
            $srcHeight = $originalHeight;
            $srcX = $srcY = 0;

            $yAdj = round(($ySize - $targetHeight) / 2);

            $output['newWidth'] = intval($targetWidth);
            $output['newHeight'] = intval($targetHeight);
            $output['yAdj'] = $yAdj;
        }

        return $output;
    }

}