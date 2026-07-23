<?php

use Illuminate\Support\Facades\Route;
use Zerp\Timesheet\Http\Controllers\Api\DashboardApiController;
use Zerp\Timesheet\Http\Controllers\Api\TimesheetApiController;

Route::prefix('api')->middleware(['api.json'])->group(function () {
    Route::group(['middleware' => ['auth:sanctum'], 'prefix' => 'timesheet'], function () {
        Route::get('dashboard', [DashboardApiController::class, 'index']);

        Route::apiResource('timesheets', TimesheetApiController::class);
    });
});
