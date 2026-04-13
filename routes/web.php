<?php

use App\Http\Controllers\ServerProvisionCallbackController;
use App\Http\Controllers\ServerProvisionScriptController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::view('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware('signed')->get('servers/{server}/provision-script', ServerProvisionScriptController::class)
    ->name('servers.provision-script');

Route::middleware('signed')->post('servers/{server}/provision-callback', ServerProvisionCallbackController::class)
    ->name('servers.provision-callback');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::view('dashboard', 'dashboard')->name('dashboard');
        Route::livewire('servers', 'pages::servers.index')->name('servers.index');
        Route::livewire('servers/{server}', 'pages::servers.show')->name('servers.show');
    });

Route::middleware(['auth'])->group(function () {
    Route::livewire('invitations/{invitation}/accept', 'pages::teams.accept-invitation')->name('invitations.accept');
});

require __DIR__.'/settings.php';
