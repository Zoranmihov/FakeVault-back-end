<?php

use App\Http\Controllers\AuthController;
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

Route::get('works', [AuthController::class, 'working']);
Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);



Route::middleware('auth:sanctum')->group(function () {
    //Users
    Route::get('profile', [AuthController::class, 'profile']);
    Route::get('logout', [AuthController::class, 'logout']);

    //Files
    Route::post('fileupload', [AuthController::class, 'uploadFile']);
    Route::post('downloadfile', [AuthController::class, 'downloadFile']);
    Route::post('deletefile', [AuthController::class, 'deleteFile']);
    Route::post('sharefile', [AuthController::class, 'shareFile']);
    Route::post('unsharefile', [AuthController::class, 'unShareFile']);
    Route::post('unfollow', [AuthController::class, 'unFollowFile']);
    Route::get('myuploadedfiles', [AuthController::class, 'myUploadedFiles']);
    Route::get('mysharedfiles', [AuthController::class, 'mySharedFiles']);
    Route::get('filessharedwith', [AuthController::class, 'filesSharedWithMe']);
});

