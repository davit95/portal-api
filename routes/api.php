<?php

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

//Route::group(['middleware' => ['cors']], function() {
//
//});

Route::post('/register', 'AuthController@register');
Route::post('/login', [ 'as' => 'login', 'uses' => 'AuthController@login']);
Route::post('/send-code', 'AuthController@sendCode');
Route::post('/activate', 'AuthController@checkEmailCodeStatus');
Route::get('/test', [ 'as' => 'test', 'uses' => 'AuthController@test']);

Route::group(['middleware' => ['auth:api']], function() {
    Route::post('/logout', [ 'as' => 'logout', 'uses' => 'AuthController@logout']);
    Route::post('/change-email', [ 'as' => 'change-email', 'uses' => 'AuthController@changeEmail'] );
    Route::post('/write',  [ 'as' => 'test', 'uses' => 'AuthController@write']);
    Route::get('/me', [ 'as' => 'me', 'uses' => 'AuthController@getUser']);
});
