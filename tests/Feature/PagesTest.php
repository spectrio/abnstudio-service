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

class PagesTest extends TestCase
{

    public function testHomePage()
    {
        $user = new User(array('username' => 'test'));
        $this->be($user);
        $response = $this->get('/home');
        $response->assertSuccessful();
    }

    public function testAddPage()
    {
        $user = new User(array('username' => 'test'));
        $this->be($user);
        $response = $this->get('/add');
        $response->assertSuccessful();
    }

    public function testQueuePage()
    {
        $user = new User(array('username' => 'test'));
        $this->be($user);
        $response = $this->get('/queue');
        $response->assertSuccessful();
    }

    public function testEditPage()
    {
        $user = new User(array('username' => 'test'));
        $this->be($user);
        $response = $this->get('/edit');
        $response->assertSuccessful();
    }

    public function testGetPage()
    {
        $user = new User(array('username' => 'test'));
        $this->be($user);
        $id = 1;
        $response = $this->get('/get?id='.$id);
        $response->assertSuccessful();
    }

    public function testStatusPage()
    {
        $user = new User(array('username' => 'test'));
        $this->be($user);
        $id = 1;
        $response = $this->get('/status?id='.$id);
        $response->assertSuccessful();
    }

    public function testRefreshPage()
    {
        $user = new User(array('username' => 'test'));
        $this->be($user);
        $response = $this->get('/refresh');
        $response->assertSuccessful();
    }

    public function testUploadPage()
    {
        $user = new User(array('username' => 'test'));
        $this->be($user);
        $response = $this->post('/upload');
        $response->assertStatus(500);
    }

    public function testSavePage()
    {
        $user = new User(array('username' => 'test'));
        $this->be($user);
        $response = $this->post('/save');
        $response->assertStatus(302);
    }

    public function testSavePatchPage()
    {
        $user = new User(array('username' => 'test'));
        $this->be($user);
        $response = $this->patch('/save');
        $response->assertStatus(302);
    }

    public function testEmailPage()
    {
        $user = new User(array('username' => 'test'));
        $this->be($user);
        $response = $this->patch('/email');
        $response->assertStatus(500);
    }

    public function testConvertPage()
    {
        $user = new User(array('username' => 'test'));
        $this->be($user);
        $response = $this->patch('/convert/1');
        $response->assertSuccessful();
    }

}
