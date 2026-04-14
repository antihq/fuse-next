<?php

use App\Http\Controllers\ServerFullProvisionScriptController;
use App\Http\Controllers\ServerProvisionCallbackController;
use App\Http\Controllers\ServerProvisionScriptController;
use App\Http\Controllers\SiteDeployCallbackController;
use App\Http\Controllers\SiteDeployScriptController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::view('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware('signed')->get('servers/{server}/provision-script', ServerProvisionScriptController::class)
    ->name('servers.provision-script');

Route::middleware('signed')->get('servers/{server}/full-provision-script', ServerFullProvisionScriptController::class)
    ->name('servers.full-provision-script');

Route::middleware('signed')->post('servers/{server}/provision-callback', ServerProvisionCallbackController::class)
    ->name('servers.provision-callback');

Route::middleware('signed')->get('sites/{site}/deploy-script', SiteDeployScriptController::class)
    ->name('sites.deploy-script');

Route::middleware('signed')->post('sites/{site}/deploy-callback', SiteDeployCallbackController::class)
    ->name('sites.deploy-callback');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::view('dashboard', 'dashboard')->name('dashboard');
        Route::livewire('servers', 'pages::servers.index')->name('servers.index');
        Route::livewire('servers/{server}', 'pages::servers.show')->name('servers.show');
        Route::livewire('servers/{server}/sites', 'pages::sites.index')->name('sites.index');
        Route::livewire('servers/{server}/sites/create', 'pages::sites.create')->name('sites.create');
        Route::livewire('servers/{server}/sites/{site}', 'pages::sites.show')->name('sites.show');
    });

Route::middleware(['auth'])->group(function () {
    Route::livewire('invitations/{invitation}/accept', 'pages::teams.accept-invitation')->name('invitations.accept');
});

require __DIR__.'/settings.php';
