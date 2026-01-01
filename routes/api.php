<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\JobDescriptionController;
use App\Http\Controllers\Api\PositionController;
use App\Http\Controllers\Api\ApplicantController;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Logged-in user permissions (menus, buttons)
    Route::get('/permissions', [PermissionController::class, 'myPermissions']);

    // Create User (permission based)
    Route::post(
        '/users',
        [UserController::class, 'store']
    )->middleware('perm:bottom-menu.user-management.add');
    Route::delete(
        '/users/{id}',
        [UserController::class, 'destroy']
    )->middleware('perm:bottom-menu.user-management.delete');
    Route::post(
        '/users/{id}/restore',
        [UserController::class, 'restore']
    )->middleware('perm:bottom-menu.user-management.edit');

    // All permissions (Create/Edit user screen)
    Route::get(
        '/permissions/all',
        [PermissionController::class, 'allPermissions']
    )->middleware('perm:bottom-menu.user-management.view');


    // Job Description
    Route::get(
        '/job-descriptions',
        [JobDescriptionController::class, 'index']
    )->middleware('perm:talent-acquisition.jd-database.view');

    Route::post(
        '/job-descriptions',
        [JobDescriptionController::class, 'store']
    )->middleware('perm:talent-acquisition.jd-database.add');

    Route::get(
        '/job-descriptions/{id}',
        [JobDescriptionController::class, 'show']
    )->middleware('perm:talent-acquisition.jd-database.view');

    Route::put(
        '/job-descriptions/{id}',
        [JobDescriptionController::class, 'update']
    )->middleware('perm:talent-acquisition.jd-database.edit');

    Route::delete(
        '/job-descriptions/{id}',
        [JobDescriptionController::class, 'destroy']
    )->middleware('perm:talent-acquisition.jd-database.delete');


    // Position
    Route::get(
        '/positions',
        [PositionController::class, 'index']
    )->middleware('perm:talent-acquisition.positions.view');
    Route::post(
        '/positions',
        [PositionController::class, 'store']
    )->middleware('perm:talent-acquisition.positions.add');
    Route::get(
        '/positions/{id}',
        [PositionController::class, 'show']
    )->middleware('perm:talent-acquisition.positions.view');
    Route::put(
        '/positions/{id}',
        [PositionController::class, 'update']
    )->middleware('perm:talent-acquisition.positions.edit');
    Route::delete(
        '/positions/{id}',
        [PositionController::class, 'destroy']
    )->middleware('perm:talent-acquisition.positions.delete');


    // Applicant
    Route::get(
        '/applicants',
        [ApplicantController::class, 'index']
    )->middleware('perm:talent-acquisition.applicants.view');

    Route::post(
        '/applicants',
        [ApplicantController::class, 'store']
    )->middleware('perm:talent-acquisition.applicants.add');

    Route::get(
        '/applicants/{id}',
        [ApplicantController::class, 'show']
    )->middleware('perm:talent-acquisition.applicants.view');

    Route::put(
        '/applicants/{id}',
        [ApplicantController::class, 'update']
    )->middleware('perm:talent-acquisition.applicants.edit');

    Route::delete(
        '/applicants/{id}',
        [ApplicantController::class, 'destroy']
    )->middleware('perm:talent-acquisition.applicants.delete');
});
