<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use App\ModifiedFields;
use DB;

class ModifiedFieldsTest extends TestCase
{
    // Get form fields
    public function testGetModifiedFields()
    {
      $formFields = new ModifiedFields;
      $query = DB::table('form_fields');
      $id = 1;
      $output = $formFields->scopeGetModifiedFields($query, $id)->get();
      $output = json_encode($output,1);

      $this->assertEquals($output,'[]');
    }
}
