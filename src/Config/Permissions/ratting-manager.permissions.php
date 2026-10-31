<?php

/**
 * Ratting Manager Permissions
 *
 * 4-tier permission model:
 *   view     - Base access (help page only)
 *   member   - Corp ratters: their own ratting, and rank and hours on the leaderboard
 *   director - Corp management: every member's ISK, hours and ISK per hour
 *   admin    - Full control: settings, diagnostics, the initial import
 *
 * Higher tiers include lower ones through the ratting-manager.tier middleware,
 * so a director does not also need the member permission on their role.
 */
return [
    'view' => [
        'label' => 'ratting-manager::permissions.view_label',
        'description' => 'ratting-manager::permissions.view_description',
        'division' => 'military',
    ],

    'member' => [
        'label' => 'ratting-manager::permissions.member_label',
        'description' => 'ratting-manager::permissions.member_description',
        'division' => 'military',
    ],

    'director' => [
        'label' => 'ratting-manager::permissions.director_label',
        'description' => 'ratting-manager::permissions.director_description',
        'division' => 'military',
    ],

    'admin' => [
        'label' => 'ratting-manager::permissions.admin_label',
        'description' => 'ratting-manager::permissions.admin_description',
        'division' => 'military',
    ],
];
