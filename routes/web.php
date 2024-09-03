<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\User\fetchCountriesController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
 */

Route::get('/', function () {
    return view('welcome');
});

Route::get('/upload', function () {
    return view('file-upload');
});

Route::post('/fileupload', [fetchCountriesController::class, 'uploadfile'])->name('file.upload');



Route::get('/reset-password', function () {
    return view('password-reset');
});

