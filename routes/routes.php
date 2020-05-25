<?php
/**
 * Created by PhpStorm.
 * User: chenyu
 * Date: 2019-09-07
 * Time: 14:54
 */

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

//获取应用token（登录）
Route::resource('/email_templates', 'EmailTemplateController');
Route::resource('/email_missions', 'EmailMissionController');
Route::post("/email_queue/create/{email_mission}", 'WechatMenuController@store');
Route::resource('email_mission.email_queues', 'EmailQueueController');
