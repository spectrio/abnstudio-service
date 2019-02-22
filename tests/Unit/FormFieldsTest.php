<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use App\FormFields;
use DB;

class FormFieldsTest extends TestCase
{
    // Get form fields
    public function testGetFormFields()
    {
      $formFields = new FormFields;
      $query = DB::table('form_fields');
      $id = 1;
      $output = $formFields->scopeGetFormFields($query, $id)->get();
      $output = json_encode($output,1);

      $this->assertEquals($output,'[]');
    }
}
