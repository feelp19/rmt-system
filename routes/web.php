<?php

use Illuminate\Support\Facades\Route;

// Frontend is served by Nuxt; Laravel is API-only.
Route::get('/', fn () => ['service' => 'rmt-system api', 'frontend' => 'nuxt']);
