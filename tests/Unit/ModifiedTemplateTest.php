<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use App\ModifiedTemplate;
use Illuminate\Http\Request;

class ModifiedTemplateTest extends TestCase
{
    // Get a modified template, see if it's returned
    public function testGetModifiedTemplate()
    {
      $id = 1;
      $template = ModifiedTemplate::getModifiedTemplate($id)->get();
      //print_r($template);
      $this->assertEquals($template[0]['tid'],1);
    }

    // Get modified templates using LIKE
    public function testGetModifiedTemplatesLike()
    {
      $id = 12345;
      $mode = 'acct';
      $template = ModifiedTemplate::getModifiedTemplatesLike($mode,$id)->get();
      $template = (array)$template;
      //print_r($template[0]);
      $this->assertTrue(!isset($template[0]));
    }

    // Get template, see if XML returned
    public function testGetTemplate()
    {
      $tid = 1;
      $modifiedTemplate = new ModifiedTemplate;
      $template = $modifiedTemplate->getTemplate($tid);
      //print_r($template);
      $this->assertEquals($template[0],'<');
    }

    // Get template, see if data returned
    public function testGetTemplateData()
    {
      $tid = 1;
      $modifiedTemplate = new ModifiedTemplate;
      $template = $modifiedTemplate->getTemplateData($tid);
      //print_r($template['thumbnail_time']);
      $this->assertTrue(isset($template['tid']));
    }

    // Get modified templates sorted
    public function testSortTemplates()
    {
      $id = 1;
      $template = ModifiedTemplate::sortTemplates($id)->get();
      //print_r($template);
      $this->assertEquals($template[0]['tid'],1);
    }

    // Get modified templates by ID descending
    public function testIdDescending()
    {
      $id = 1;
      $template = ModifiedTemplate::idDescending($id)->get();
      //print_r($template);
      $this->assertEquals($template[0]['tid'],1);
    }

    // Get a list of incomplete jobs (modified)
    public function testIncompleteJobs()
    {
      $id = 1;
      $template = ModifiedTemplate::incompleteJobs()->get();
      $template = (array)$template;
      $this->assertTrue(is_array($template));
    }

    // Update modified job status
    public function testUpdateStatus()
    {
      $id = -1;
      $jobStatus['status'] = 'TEST';
      $jobStatus['url'] = 'TEST';
      $jobStatus['thumbnailUrl'] = 'TEST';
      $model = new ModifiedTemplate;
      $template = $model->updateStatus($id,$jobStatus);
      $this->assertEquals($template[0], '{');
    }


}
