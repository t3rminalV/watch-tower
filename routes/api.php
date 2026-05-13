<?php

use App\Http\Controllers\Api\IngestController;
use App\Http\Middleware\AuthenticateIngestRequest;
use Illuminate\Support\Facades\Route;

Route::middleware(AuthenticateIngestRequest::class)
    ->group(function () {
        Route::post('/', [IngestController::class, 'batch'])->name('api.ingest.batch');
        Route::post('/sync', [IngestController::class, 'sync'])->name('api.ingest.sync');
    });
