<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use App\ProcessXml;
use Illuminate\Http\Request;

class ProcessXmlTest extends TestCase
{
    // Attempt to process a normal XML file
    public function testGoodXml()
    {
      $file = '../../../xml/test_good.xml';
      $orgFilename = 'test.xml';
      $processXml = new ProcessXml;
      $output = $processXml->process($file,$orgFilename);
      $output = json_decode($output, true);
      $this->assertEquals($output['orgFilename'],$orgFilename);
    }

    // Attempt to process a malformed XML file
    public function testBadXml()
    {
      $file = '../../../xml/test_bad.xml';
      $orgFilename = 'test.xml';
      $processXml = new ProcessXml;
      $output = $processXml->process($file,$orgFilename);
      $output = json_decode(json_encode($output), true);
      $this->assertNotEmpty($output[0]['message']);
    }

    // Attempt to process a normal XML string
    public function testBadRawXml()
    {
      $file = '<xml><test></test></xml>';
      $orgFilename = 'test.xml';
      $processXml = new ProcessXml;
      $output = $processXml->process($file,$orgFilename);
      $output = json_decode($output, true);
      $this->assertEmpty($output['orgFilename']);
    }

    // Attempt to process a malformed XML string
    public function testGoodRawXml()
    {
      $file = '<timeline version="1"><layers><layer><text><![CDATA[<div></div>]]></text></layer></layers></timeline>';
      $orgFilename = 'test.xml';
      $processXml = new ProcessXml;
      $output = $processXml->process($file,$orgFilename);
      $output = json_decode($output, true);
      $this->assertEquals($output['orgFilename'],$orgFilename);
    }

    // Try sending a (bad) test XML file to WeVideo
    // We just want to test the connection, not junk up their servers
    public function testWeVideoRender()
    {
      $file = '<xml><test></test></xml>';
      $processXml = new ProcessXml;
      $output = $processXml->render($file);
      $output = json_decode($output, true);
      $this->assertEquals($output['error_code'],'bad_request');
    }

    // Try sending a (bad) status id to WeVideo
    public function testWeVideoJobStatus()
    {
      $fakeId = 'TEST';
      $processXml = new ProcessXml;
      $output = $processXml->jobStatus($fakeId);
      $output = json_decode($output, true);

      $this->assertEquals($output['status'],'NOT_FOUND');
    }

    // Try sending a (bad) timeline id to WeVideo
    public function testConvertTimeline()
    {
      $fakeId = '123456';

      $processXml = new ProcessXml;
      $output = $processXml->convertTimeline($fakeId);
      $output = json_decode($output, true);
      //print_r($output);

      $this->assertEquals($output['message'],'An error occured');
    }

    //
    public function testTextToHtml()
    {
      $filename = 'test_ugly.xml';
      $xml = file_get_contents(public_path()."../../xml/$filename");
      $processXml = new ProcessXml;
      $xmlOut = $processXml->textToHtml($xml);
      $result = false;
      if(strpos($xmlOut,'<html begin="') !== false) {
        $result = true;
      }
      $this->assertTrue($result);
    }

    //
    public function testGenerateJsonManifest()
    {
      $filename = 'test_good.xml';
      $xml = file_get_contents(public_path()."../../xml/$filename");
      //echo $xml;
      $processXml = new ProcessXml;
      $xmlObj = simplexml_load_string($xml);
      $jsonOut = $processXml->generateJsonManifest($xmlObj,'','','');
      if(strpos($jsonOut,'"title": "fade"') !== false) {
        $result = true;
      }
      $this->assertTrue($result);
    }

    // Send an XML with expected bad attributes and check for cleaned version
    public function testCleanup()
    {
      $filename = 'test_ugly.xml';
      $xml = file_get_contents(public_path()."../../xml/$filename");
      $filenameClean = 'test_cleaned.xml';
      $xmlClean = file_get_contents(public_path()."../../xml/$filenameClean");
      libxml_use_internal_errors(true);
      libxml_clear_errors();
      $xmlObj = simplexml_load_string($xml, null, LIBXML_NOCDATA);
      if ($xmlObj === false) {
        print_r( libxml_get_errors() );
      }
      $processXml = new ProcessXml;
      $cleaned = $processXml->cleanup($xmlObj);
      $output = $processXml->objToXml($cleaned);

      $this->assertEquals($this->collapseString($output),$this->collapseString($xmlClean));
    }

    // Convert XML to an object, then back again, and check integrity
    public function testObjToXml()
    {
      $filename = 'test_cleaned.xml';
      $xml = file_get_contents(public_path()."../../xml/$filename");
      libxml_use_internal_errors(true);
      libxml_clear_errors();
      $xmlObj = simplexml_load_string($xml, null, LIBXML_NOCDATA);
      if ($xmlObj === false) {
        print_r( libxml_get_errors() );
      }
      $processXml = new ProcessXml;
      $output = $processXml->objToXml($xmlObj);

      $this->assertEquals($this->collapseString($output),$this->collapseString($xml));
    }

    private function collapseString($str) {
      $str = preg_replace( "/\r|\n/", "", $str );
      $str = str_replace(' ', '', $str);
      //preg_replace('/[ \t]+/', ' ', $str);
      return $str;
    }
}
