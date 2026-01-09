<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Scraping Dashboard
Route::get('/scraping', function () {
    return view('scraping');
})->name('scraping.dashboard');

require __DIR__.'/settings.php';
