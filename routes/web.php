<?php

use App\Http\Controllers\DanceVideoController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DanceVideoController::class, 'index'])->name('home');
Route::get('/videos/novo', [DanceVideoController::class, 'create'])->name('videos.create');
Route::post('/videos', [DanceVideoController::class, 'store'])->name('videos.store');
Route::get('/videos/{video}/editar', [DanceVideoController::class, 'edit'])->name('videos.edit');
Route::put('/videos/{video}', [DanceVideoController::class, 'update'])->name('videos.update');
Route::delete('/videos/{video}', [DanceVideoController::class, 'destroy'])->name('videos.destroy');
Route::get('/videos/{video}', [DanceVideoController::class, 'show'])->name('videos.show');
Route::get('/videos/{video}/baixar', [DanceVideoController::class, 'download'])->name('videos.download');
