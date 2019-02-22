<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use App\Template;
use App\Http\Controllers\GetTemplateController;
use Illuminate\Http\Request;

class TemplateTest extends TestCase
{
    // Query id from database and see if returned
    public function testGetTemplate()
    {
      $id = 1;
      $template = Template::getTemplate($id)->get();
      //print_r($template);
      $this->assertEquals($template[0]['tid'],1);
    }

    // Get templates sorted
    public function testSortTemplates()
    {
      $id = 1;
      $template = Template::sortTemplates($id)->get();
      //print_r($template);
      $this->assertEquals($template[0]['tid'],1);
    }

    // Get templates by Id descending
    public function testIdDescending()
    {
      $id = 1;
      $template = Template::idDescending($id)->get();
      //print_r($template);
      $this->assertEquals($template[0]['tid'],1);
    }

    // Get incomplete jobs
    public function testIncompleteJobs()
    {
      $id = 1;
      $template = Template::incompleteJobs()->get();
      $template = (array)$template;
      $this->assertTrue(is_array($template));
    }

    // Get job status
    public function testUpdateStatus()
    {
      $id = -1;
      $jobStatus['status'] = 'TEST';
      $jobStatus['url'] = 'TEST';
      $jobStatus['thumbnailUrl'] = 'TEST';
      $model = new Template;
      $template = $model->updateStatus($id,$jobStatus);
      //print_r($template);
      $this->assertEquals($template[0], '{');
    }

    // Request id from controller and see if returned
    public function testGetTemplateController()
    {
      //Event::fake();
      $request = Request::create('/get', 'GET',['id' => 1]);
      $controller = new GetTemplateController();
      $response = $controller->index($request);
      $response = json_decode($response,1);
      $this->assertEquals($response['template'][0]['tid'],1);
    }

}
