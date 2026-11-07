<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceTokenController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\RequestController;
use App\Http\Controllers\RequestSocialMediaController;
use App\Http\Controllers\SystemTypeController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:api')->group(function () {
    Route::post('/request', [CustomerController::class, 'request']);

    Route::post('/message', [CustomerController::class, 'message']);

    Route::get('/contact', [CustomerController::class, 'contact']);

    // Public: the customer request form needs the list of system types.
    Route::get('/system-types', [SystemTypeController::class, 'systemTypes']);

    // GET (Details) -> show the number of projects and devs in the team.

    // POST (creat_dev) -> Create all new data for dev in the system.

    // GET (cities) -> return all cities in the system.

    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
});

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'dashboardInfo']);

    Route::get('/requests', [RequestController::class, 'returnAllRequests']);

    Route::get('/request/social-media-account/{request}', [RequestSocialMediaController::class, 'returnSocialMediaAccount']);

    Route::get('/messages', [MessageController::class, 'returnAllMessages']);

    // Admin-only: only authenticated admins may register a push device.
    Route::post('/device-tokens', [DeviceTokenController::class, 'store'])
        ->middleware('throttle:device-tokens');

    Route::delete('/device-tokens', [DeviceTokenController::class, 'destroy'])
        ->middleware('throttle:device-tokens');

    Route::get('/notifications', [NotificationController::class, 'index']);

    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);

    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);

    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);

    Route::patch('/request/{request}/complete', [RequestController::class, 'requestComplete']);

    Route::patch('/request/{request}/revert', [RequestController::class, 'requestRevert']);

    // GET (develpoers) -> get all devs with pagination , filters and search by name only (also this should return the dashboard data) use some other web to store files

    // GET (develpoer/{id}) -> get all details of the dev

    // PATCH (dev/{id}/status) -> change the status of the dev (accept, reject, or in review...)

    // POST (dev/{id}/note) -> To create new notes about the devopler

    // GET (notifcatoin)

    // PATCH (notifcatoin/read)

    // (logout)
});
