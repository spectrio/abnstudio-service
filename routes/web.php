<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    return $router->app->version();
});

$router->get('/test', function () use ($router) {
    return $router->app->version();
});

$router->get('/get', 'GetTemplateController@index');
$router->delete('/delete', 'GetTemplateController@delete');
$router->get('/status', 'JobStatusController@index');
$router->get('/refresh', 'JobStatusController@refresh');
//$router->get('/queue', 'JobStatusController@queue');
$router->post('/email', 'JobStatusController@email');
$router->get('/getlogo', 'GetTemplateController@getLogo');
$router->get('/save', 'SaveTemplateController@index');
$router->post('/upload', 'UploadController@upload');

$router->post('/modified/save', 'ModifyTemplateController@index');
$router->patch('/modified/save', 'ModifyTemplateController@index');
$router->get('/modified/get/{id}', 'ModifyTemplateController@get');
$router->get('/modified/list[/{mode}/{id}]', 'ModifyTemplateController@list');
$router->get('/modified/status', 'ModifiedJobStatusController@index');
$router->get('/modified/refresh', 'ModifiedJobStatusController@refresh');
$router->get('/modified/queue', 'ModifiedJobStatusController@queue');

$router->get('/channels[/{id}]', 'SugarController@index');
$router->get('/jira[/{id}]', 'JiraController@index');
