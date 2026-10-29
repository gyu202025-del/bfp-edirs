<?php

use App\Http\Controllers\Auth\FacebookController;
use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\GisMapController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AssistanceRequestController;
use App\Http\Controllers\AnalyticsReportController;
use App\Http\Controllers\UserManagementController;


Route::get('/', fn () => redirect('/login'));

// Guest routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.submit');

    Route::get('/auth/facebook/redirect', [FacebookController::class, 'redirect'])->name('facebook.redirect');
    Route::get('/auth/facebook/callback', [FacebookController::class, 'callback'])->name('facebook.callback');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

// Authenticated routes, shared by all roles
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('role:Administrator,Head,Staff')->name('dashboard');
    Route::get('/gis-map', [GisMapController::class, 'index'])->middleware('role:Administrator,Head,Staff')->name('gis.map');
    Route::get('/api/incidents/live', [GisMapController::class, 'live'])->middleware('role:Administrator,Head,Staff')->name('gis.live');

    Route::get('/notifications', [NotificationController::class, 'index'])->middleware('role:Administrator,Head,Staff')->name('notifications.index');
    Route::get('/api/notifications/unread', [NotificationController::class, 'liveUnread'])->middleware('role:Administrator,Head,Staff')->name('notifications.liveUnread');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->middleware('role:Administrator,Head,Staff')->name('notifications.markRead');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->middleware('role:Administrator,Head,Staff')->name('notifications.markAllRead');
});

// Administrator + Head + Staff — operational modules
Route::middleware(['auth', 'role:Administrator,Head,Staff'])->group(function () {
    // Incidents
    Route::get('/incidents', [IncidentController::class, 'index'])->name('incidents.index');
    Route::post('/incidents/{incident}/verify', [IncidentController::class, 'verify'])->name('incidents.verify');
    Route::post('/incidents/{incident}/acknowledge', [IncidentController::class, 'acknowledge'])->name('incidents.acknowledge');
    Route::post('/incidents/{incident}/cancel', [IncidentController::class, 'cancel'])->name('incidents.cancel');
    Route::post('/incidents/{incident}/resolve', [IncidentController::class, 'resolve'])->name('incidents.resolve');
    Route::post('/incidents/{incident}/request-assistance', [AssistanceRequestController::class, 'store'])->name('assistance.request');
    Route::get('/incidents/{incident}/nearby-stations', [IncidentController::class, 'nearbyStations'])->name('incidents.nearbyStations');

    // Fire stations + reports
    Route::get('/firestations', function () {
        abort_unless(auth()->user()->isAdministrator() || auth()->user()->hasDepartment('BFP'), 403);
        return view('firestations.index');
    })->name('firestations.index');
    Route::get('/reports', [AnalyticsReportController::class, 'index'])->name('reports.index');
});

// System administrator — user management
Route::middleware(['auth', 'role:Administrator'])->group(function () {
    Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
    Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
    Route::put('/users/{user}', [UserManagementController::class, 'update'])->name('users.update');
    Route::post('/users/{user}/toggle-status', [UserManagementController::class, 'toggleStatus'])->name('users.toggle');
    Route::post('/users/{user}/reset-password', [UserManagementController::class, 'resetPassword'])->name('users.resetPassword');
    Route::delete('/users/{user}', [UserManagementController::class, 'destroy'])->name('users.destroy');
    Route::post('/users/{id}/restore', [UserManagementController::class, 'restore'])->name('users.restore');
    Route::get('/settings', fn () => view('settings.index'))->name('settings.index');
});

// Public citizen reporting
Route::get('/report', [ReportController::class, 'select'])->name('report.select');
Route::get('/report/{category}', [ReportController::class, 'create'])->name('report.create');
Route::post('/report', [ReportController::class, 'store'])->name('report.store');