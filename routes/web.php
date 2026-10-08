<?php

use App\Http\Controllers\Auth\FacebookController;
use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\GisMapController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\DispatchController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AssistanceRequestController;


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
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/gis-map', [GisMapController::class, 'index'])->name('gis.map');
    Route::get('/api/incidents/live', [GisMapController::class, 'live'])->name('gis.live');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/api/notifications/unread', [NotificationController::class, 'liveUnread'])->name('notifications.liveUnread');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.markRead');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.markAllRead');
});

// Administrator + BFP Personnel — operational modules
Route::middleware(['auth', 'role:Administrator,Head'])->group(function () {
    Route::get('/incidents', [IncidentController::class, 'index'])->name('incidents.index');
    Route::post('/incidents/{incident}/verify', [IncidentController::class, 'verify'])->name('incidents.verify');
    Route::post('/incidents/{incident}/cancel', [IncidentController::class, 'cancel'])->name('incidents.cancel');
    Route::post('/incidents/{incident}/request-assistance', [AssistanceRequestController::class, 'store'])->name('assistance.request');
    Route::get('/incidents/{incident}/nearby-stations', [IncidentController::class, 'nearbyStations'])->name('incidents.nearbyStations');
     
   

    Route::get('/dispatches', [DispatchController::class, 'index'])->name('dispatches.index');
Route::post('/dispatches', [DispatchController::class, 'store'])->name('dispatches.store');
Route::post('/dispatches/{dispatch}/complete', [DispatchController::class, 'complete'])->name('dispatches.complete');
Route::post('/dispatches/{dispatch}/assign-officer', [DispatchController::class, 'assignOfficer'])->name('dispatches.assignOfficer');

Route::get('/firestations', fn () => view('firestations.index'))->name('firestations.index');
    Route::get('/reports', fn () => view('reports.index'))->name('reports.index');
});

// Administrator only
Route::middleware(['auth', 'role:Administrator'])->group(function () {
    Route::get('/users', fn () => view('users.index'))->name('users.index');
    Route::get('/settings', fn () => view('settings.index'))->name('settings.index');
});

Route::get('/report', [ReportController::class, 'select'])->name('report.select');
Route::get('/report/{category}', [ReportController::class, 'create'])->name('report.create');
Route::post('/report', [ReportController::class, 'store'])->name('report.store');