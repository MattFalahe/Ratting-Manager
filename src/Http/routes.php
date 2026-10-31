<?php

use Illuminate\Support\Facades\Route;

Route::group([
    'namespace' => 'RattingManager\Http\Controllers',
    'prefix' => 'ratting-manager',
    'middleware' => ['web', 'auth', 'locale'],
], function () {

    // Help is open to everyone with any Ratting Manager permission, including
    // the base view tier, so a new member can read what the plugin does.
    Route::get('/help', [
        'as' => 'ratting-manager.help',
        'uses' => 'HelpController@index',
        'middleware' => 'ratting-manager.tier:view',
    ]);

    Route::group(['middleware' => 'ratting-manager.tier:member'], function () {
        // Whether characters a page showed as in progress have been looked up yet.
        Route::get('/characters/pending', [
            'as' => 'ratting-manager.characters.pending',
            'uses' => 'CharacterLookupController@pending',
        ]);

        Route::get('/', [
            'as' => 'ratting-manager.home',
            'uses' => 'DashboardController@index',
        ]);

        Route::get('/dashboard', [
            'as' => 'ratting-manager.dashboard',
            'uses' => 'DashboardController@index',
        ]);

        Route::get('/my/{list}', [
            'as' => 'ratting-manager.lists.personal',
            'uses' => 'ListController@personal',
        ])->where('list', '[a-z-]+');

        Route::get('/leaderboard', [
            'as' => 'ratting-manager.leaderboard',
            'uses' => 'LeaderboardController@index',
        ]);
    });

    Route::group(['middleware' => 'ratting-manager.tier:director'], function () {
        Route::get('/dashboard/tab/corporation', [
            'as' => 'ratting-manager.dashboard.tab.corporation',
            'uses' => 'DashboardController@corporationTab',
        ]);

        Route::get('/leaderboard/csv', [
            'as' => 'ratting-manager.leaderboard.csv',
            'uses' => 'LeaderboardController@csv',
        ]);

        Route::get('/report', [
            'as' => 'ratting-manager.report',
            'uses' => 'ReportController@index',
        ]);

        Route::get('/report/csv', [
            'as' => 'ratting-manager.report.csv',
            'uses' => 'ReportController@csv',
        ]);

        Route::get('/corporation/{list}', [
            'as' => 'ratting-manager.lists.corporation',
            'uses' => 'ListController@corporation',
        ])->where('list', '[a-z-]+');

        Route::get('/ratters/{characterId}/{list}', [
            'as' => 'ratting-manager.ratters.list',
            'uses' => 'ListController@ratter',
        ])->where(['characterId' => '[0-9]+', 'list' => '[a-z-]+']);

        Route::get('/ratters', [
            'as' => 'ratting-manager.ratters.index',
            'uses' => 'RatterController@index',
        ]);

        Route::get('/ratters/{characterId}', [
            'as' => 'ratting-manager.ratters.show',
            'uses' => 'RatterController@show',
        ])->where('characterId', '[0-9]+');
    });

    Route::group(['middleware' => 'ratting-manager.tier:admin'], function () {
        Route::get('/settings', [
            'as' => 'ratting-manager.settings.index',
            'uses' => 'SettingsController@index',
        ]);

        Route::post('/settings', [
            'as' => 'ratting-manager.settings.update',
            'uses' => 'SettingsController@update',
        ]);

        Route::get('/settings/corporations/{corporationId}', [
            'as' => 'ratting-manager.settings.corporations.show',
            'uses' => 'SettingsController@corporation',
        ])->where('corporationId', '[0-9]+');

        Route::post('/settings/corporations', [
            'as' => 'ratting-manager.settings.corporations.track',
            'uses' => 'SettingsController@track',
        ]);

        Route::post('/settings/corporations/{corporationId}/untrack', [
            'as' => 'ratting-manager.settings.corporations.untrack',
            'uses' => 'SettingsController@untrack',
        ])->where('corporationId', '[0-9]+');

        Route::post('/settings/notifications', [
            'as' => 'ratting-manager.settings.notifications',
            'uses' => 'NotificationSettingsController@update',
        ]);

        Route::post('/settings/webhooks', [
            'as' => 'ratting-manager.settings.webhooks.store',
            'uses' => 'NotificationSettingsController@store',
        ]);

        Route::post('/settings/webhooks/{webhook}', [
            'as' => 'ratting-manager.settings.webhooks.update',
            'uses' => 'NotificationSettingsController@edit',
        ])->where('webhook', '[0-9]+');

        Route::post('/settings/webhooks/{webhook}/delete', [
            'as' => 'ratting-manager.settings.webhooks.destroy',
            'uses' => 'NotificationSettingsController@destroy',
        ])->where('webhook', '[0-9]+');

        Route::post('/settings/webhooks/{webhook}/test', [
            'as' => 'ratting-manager.settings.webhooks.test',
            'uses' => 'NotificationSettingsController@test',
        ])->where('webhook', '[0-9]+');

        // Every tab, the Master Test and the Tick Trace included, is a GET
        // and changes no data. The one POST sends a sample post to a webhook.
        Route::get('/diagnostic', [
            'as' => 'ratting-manager.diagnostic',
            'uses' => 'DiagnosticController@index',
        ]);

        Route::post('/diagnostic/notification-test', [
            'as' => 'ratting-manager.diagnostic.notification-test',
            'uses' => 'DiagnosticController@notificationTest',
        ]);
    });
});
