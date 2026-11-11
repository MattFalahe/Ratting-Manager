<?php

// A permission list means any one of them is enough, which is how a director
// or admin sees the member pages without holding the member permission too.
$any = ['ratting-manager.view', 'ratting-manager.member', 'ratting-manager.director', 'ratting-manager.admin'];
$member = ['ratting-manager.member', 'ratting-manager.director', 'ratting-manager.admin'];
$director = ['ratting-manager.director', 'ratting-manager.admin'];

return [
    'ratting-manager' => [
        'name'          => 'Ratting Manager',
        'label'         => 'ratting-manager::menu.ratting_manager',
        'plural'        => true,
        'icon'          => 'fas fa-crosshairs',
        'route_segment' => 'ratting-manager',
        // Any tier opens the menu. SeAT hides the entries a user cannot open,
        // so with only the view permission it holds Help & Documentation alone.
        'permission'    => $any,
        'entries'       => [
            [
                'name'       => 'Dashboard',
                'label'      => 'ratting-manager::menu.dashboard',
                'icon'       => 'fas fa-tachometer-alt',
                'route'      => 'ratting-manager.dashboard',
                'permission' => $member,
            ],
            [
                'name'       => 'Leaderboard',
                'label'      => 'ratting-manager::menu.leaderboard',
                'icon'       => 'fas fa-trophy',
                'route'      => 'ratting-manager.leaderboard',
                'permission' => $member,
            ],
            [
                'name'       => 'Monthly Report',
                'label'      => 'ratting-manager::menu.monthly_report',
                'icon'       => 'fas fa-file-invoice-dollar',
                'route'      => 'ratting-manager.report',
                'permission' => $director,
            ],
            [
                'name'       => 'Ratter Lookup',
                'label'      => 'ratting-manager::menu.ratter_lookup',
                'icon'       => 'fas fa-search',
                'route'      => 'ratting-manager.ratters.index',
                'permission' => $director,
            ],
            [
                'name'       => 'Settings',
                'label'      => 'ratting-manager::menu.settings',
                'icon'       => 'fas fa-cog',
                'route'      => 'ratting-manager.settings.index',
                'permission' => 'ratting-manager.admin',
            ],
            [
                'name'       => 'Help & Documentation',
                'label'      => 'ratting-manager::menu.help',
                'icon'       => 'fas fa-question-circle',
                'route'      => 'ratting-manager.help',
                'permission' => $any,
            ],
        ],
    ],
];
