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
    return 'ROES (Render On Edge Service) API <br>' . $router->app->version();
});

$router->get('/healthcheck.html', function () use ($router) {
    return 'Server Up';
});

$router->get('/get', 'GetTemplateController@index');
$router->delete('/delete', 'GetTemplateController@delete');
$router->get('/delete', 'GetTemplateController@delete');
$router->get('/status', 'JobStatusController@index');
$router->get('/refresh', 'JobStatusController@refresh');
$router->get('/queue', 'JobStatusController@queue');
$router->post('/email', 'JobStatusController@email');
$router->get('/getlogo', 'GetTemplateController@getLogo');
$router->post('/save', 'SaveTemplateController@index');
$router->get('/save', 'SaveTemplateController@index');
$router->patch('/save', 'SaveTemplateController@index');
$router->post('/upload', 'UploadController@upload');

$router->post('/modified/save', 'ModifyTemplateController@index');
$router->patch('/modified/save', 'ModifyTemplateController@index');
$router->get('/modified/get/{id}', 'ModifyTemplateController@get');
$router->get('/modified/list[/{mode}/{id}]', 'ModifyTemplateController@list');
$router->delete('modified/delete', 'ModifyTemplateController@delete');
$router->get('/modified/status', 'ModifiedJobStatusController@index');
$router->get('/modified/refresh', 'ModifiedJobStatusController@refresh');
$router->get('/modified/queue', 'ModifiedJobStatusController@queue');

$router->get('/channels[/{id}]', 'SugarController@index');
$router->get('/accounts[/{cache}]', 'SugarController@getAccounts');
$router->get('/accounts_cache', 'SugarController@getAccountsFromCache');
$router->get('/jira[/{id}]', 'JiraController@index');
$router->get('/reports', 'ModifyTemplateController@reports');
$router->get('/reports/templates', 'ReportController@getTemplates');
$router->get('/reports/spots', 'ReportController@getSpots');
$router->get('/reports/renders', 'ReportController@getRenders');

$router->get('/emailtest', 'ModifiedJobStatusController@emailTest');

$router->get('/wevideo/login', 'WeVideoController@login');
$router->get('/wevideo/get[/{id}]', 'WeVideoController@get_media');
