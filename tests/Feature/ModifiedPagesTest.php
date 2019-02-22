<?php

namespace Tests\Feature;

use Tests\TestCase;
use Auth;
use Mockery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\User;
use App\Http\Controllers\ReportsController;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;
use App\Exceptions\Handler;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\File;
use Adldap;

class ModifiedPagesTest extends TestCase
{

    public function testModifiedSavePage()
    {
        $user = new User(array('username' => 'test'));
        $this->be($user);
        $response = $this->post('/modified/save');
        $response->assertStatus(500);
    }

    public function testModifiedGetPage()
    {
        $user = new User(array('username' => 'test'));
        $this->be($user);
        $response = $this->get('/modified/get/1');
        $response->assertSuccessful();
    }

    public function testModifiedListPage()
    {
        $user = new User(array('username' => 'test'));
        $this->be($user);
        $response = $this->get('/modified/list/mode/id');
        $response->assertSuccessful();
    }

    public function testModifiedStatusPage()
    {
        $user = new User(array('username' => 'test'));
        $this->be($user);
        $response = $this->get('/modified/status?id=1');
        $response->assertSuccessful();
    }

    public function testModifiedRefreshPage()
    {
        $user = new User(array('username' => 'test'));
        $this->be($user);
        $response = $this->get('/modified/refresh');
        $response->assertSuccessful();
    }

    public function testModifiedQueuePage()
    {
        $user = new User(array('username' => 'test'));
        $this->be($user);
        $response = $this->get('/modified/queue');
        $response->assertSuccessful();
    }

}
