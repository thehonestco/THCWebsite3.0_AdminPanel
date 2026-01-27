<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\JobDescriptionController;
use App\Http\Controllers\Api\PositionController;
use App\Http\Controllers\Api\ApplicantController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\OpportunityController;
use App\Http\Controllers\Api\NoteController;
use App\Http\Controllers\Api\BankDetailController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\PublicLeadController;
use App\Http\Controllers\Api\PublicJobController;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/public/lead', [PublicLeadController::class, 'store'])
    ->middleware('throttle:10,1'); // 10 req / minute
Route::get('/public/jobs', [PublicJobController::class, 'index']);
Route::get('/public/jobs/{id}', [PublicJobController::class, 'show']);
Route::post('/public/jobs/{positionId}/apply', [PublicJobController::class, 'apply']
)->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Logged-in user permissions (menus, buttons)
    Route::get('/permissions', [PermissionController::class, 'myPermissions']);

    // Create User (permission based)
    Route::get(
        '/users',
        [UserController::class, 'index']
    )->middleware('perm:bottom-menu.user-management.view');
    Route::post(
        '/users',
        [UserController::class, 'store']
    )->middleware('perm:bottom-menu.user-management.add');
    Route::get(
        '/users/{id}',
        [UserController::class, 'show']
    )->middleware('perm:bottom-menu.user-management.view');
    Route::put(
        '/users/{id}',
        [UserController::class, 'update']
    )->middleware('perm:bottom-menu.user-management.edit');
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

    Route::post('/positions/{id}/applicants', [PositionController::class, 'addApplicants'])
    ->middleware('perm:talent-acquisition.positions.edit');
    Route::put(
        '/positions/{positionId}/applicants/{applicationId}',
        [PositionController::class, 'updateApplicant']
    )->middleware('perm:talent-acquisition.positions.edit');
    Route::delete(
        '/positions/{positionId}/applicants/{applicationId}',
        [PositionController::class, 'removeApplicant']
    )->middleware('perm:talent-acquisition.positions.edit');


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
    Route::post(
        '/applicants/{id}/positions',
        [ApplicantController::class, 'addPositions']
    )->middleware('perm:talent-acquisition.applicants.edit');


    // Leads
    Route::get(
        '/leads',
        [LeadController::class, 'index']
    )->middleware('perm:business.leads.view');

    Route::post(
        '/leads',
        [LeadController::class, 'store']
    )->middleware('perm:business.leads.add');

    Route::get(
        '/leads/{id}',
        [LeadController::class, 'show']
    )->middleware('perm:business.leads.view');

    Route::put(
        '/leads/{id}',
        [LeadController::class, 'update']
    )->middleware('perm:business.leads.edit');

    Route::delete(
        '/leads/{id}',
        [LeadController::class, 'destroy']
    )->middleware('perm:business.leads.delete');

    Route::post(
        '/leads/bulk-upload',
        [LeadController::class, 'bulkUpload']
    )->middleware('perm:business.leads.add');

    Route::post(
        '/leads/{id}/convert',
        [LeadController::class, 'convert']
    )->middleware('perm:business.leads.edit');
    
    Route::get(
        '/clients',
        [LeadController::class, 'clients']
    )->middleware('perm:business.clients.view');


    // Opportunities
    Route::get(
        '/leads/{leadId}/opportunities',
        [OpportunityController::class, 'index']
    )->middleware('perm:business.leads.view');

    Route::post(
        '/leads/{leadId}/opportunities',
        [OpportunityController::class, 'store']
    )->middleware('perm:business.leads.add');

    Route::get(
        '/opportunities/{id}',
        [OpportunityController::class, 'show']
    )->middleware('perm:business.leads.view');

    Route::put(
        '/opportunities/{id}',
        [OpportunityController::class, 'update']
    )->middleware('perm:business.leads.edit');

    Route::delete(
        '/opportunities/{id}',
        [OpportunityController::class, 'destroy']
    )->middleware('perm:business.leads.delete');
    

    // Notes
    Route::get(
        '/opportunities/{opportunityId}/notes',
        [NoteController::class, 'index']
    )->middleware('perm:business.leads.view');

    Route::post(
        '/opportunities/{opportunityId}/notes',
        [NoteController::class, 'store']
    )->middleware('perm:business.leads.add');

    Route::get(
        '/notes/{id}',
        [NoteController::class, 'show']
    )->middleware('perm:business.leads.view');

    Route::put(
        '/notes/{id}',
        [NoteController::class, 'update']
    )->middleware('perm:business.leads.edit');

    Route::delete(
        '/notes/{id}',
        [NoteController::class, 'destroy']
    )->middleware('perm:business.leads.delete');

    // Bank Details
    Route::get(
        '/bank-details',
        [BankDetailController::class, 'index']
    )->middleware('perm:accounts.invoices.view');

    Route::post(
        '/bank-details',
        [BankDetailController::class, 'store']
    )->middleware('perm:accounts.invoices.add');

    Route::get(
        '/bank-details/{id}',
        [BankDetailController::class, 'show']
    )->middleware('perm:accounts.invoices.view');

    Route::put(
        '/bank-details/{id}',
        [BankDetailController::class, 'update']
    )->middleware('perm:accounts.invoices.edit');

    Route::delete(
        '/bank-details/{id}',
        [BankDetailController::class, 'destroy']
    )->middleware('perm:accounts.invoices.delete');

    // Invoice
    Route::get(
        '/invoices',
        [InvoiceController::class, 'index']
    )->middleware('perm:accounts.invoices.view');

    Route::post(
        '/invoices',
        [InvoiceController::class, 'store']
    )->middleware('perm:accounts.invoices.add');

    Route::get(
        '/invoices/{id}',
        [InvoiceController::class, 'show']
    )->middleware('perm:accounts.invoices.view');

    Route::delete(
        '/invoices/{id}',
        [InvoiceController::class, 'destroy']
    )->middleware('perm:accounts.invoices.delete');
});
