<?php

use App\Http\Controllers\Api\ReportApiController;
use Illuminate\Support\Facades\Route;

Route::get('/report/categories', [ReportApiController::class, 'categories']);
Route::get('/report/municipalities', [ReportApiController::class, 'municipalities']);
Route::post('/report', [ReportApiController::class, 'store']);