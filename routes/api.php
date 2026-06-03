<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => ['status' => 'ok']);

Route::get('/user', fn (Request $request) => $request->user())
    ->middleware('auth:sanctum');
