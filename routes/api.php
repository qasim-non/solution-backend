<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\CustomerController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:api')->group(function () {
    Route::post('/request', [CustomerController::class, 'request']);

    Route::post('/message', [CustomerController::class, 'message']);

    Route::get('/contact', [CustomerController::class, 'contact']);

    // Public: the customer request form needs the list of system types.
    Route::get('/system-types', [AdminController::class, 'systemTypes']);

    // GET (Details) -> show the number of projects and devs in the team.


    // POST (creat_dev) -> Create all new data for dev in the system.


    // GET (cities) -> return all cities in the system.

    Route::post('/login', [AdminController::class, 'login'])->middleware('throttle:login');
});


Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {

    Route::get('/dashboard', [AdminController::class, 'dashboardInfo']);

    Route::get('/requests', [AdminController::class, 'returnAllRequests']);

    Route::get('/request/social-media-account/{request}', [AdminController::class, 'returnSocialMediaAccount']);

    Route::get('/messages', [AdminController::class, 'returnAllMessages']);

    Route::patch('/request/{request}/complete', [AdminController::class, 'requestComplete']);

    Route::patch('/request/{request}/revert', [AdminController::class, 'requestRevert']);

    // GET (develpoers) -> get all devs with pagination , filters and search by name only (also this should return the dashboard data) use some other web to store files


    // GET (develpoer/{id}) -> get all details of the dev


    // PATCH (dev/{id}/status) -> change the status of the dev (accept, reject, or in review...)


    // POST (dev/{id}/note) -> To create new notes about the devopler


    // GET (notifcatoin)

    // PATCH (notifcatoin/read)

});
