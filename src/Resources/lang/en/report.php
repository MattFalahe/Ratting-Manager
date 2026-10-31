<?php

return [
    'title' => 'Monthly Report',
    'intro' => 'One calendar month, for the corporation\'s books. Ratting counts for the corporation a character was in at the time, so a past month still lists everyone who ratted for it, including people who have left since.',
    'change' => ':change% on the month before',
    'tax_share' => ':share% of ISK ratted',
    'received' => 'Pilots received',
    'received_split' => ':bounties bounties, :ess ESS',
    'ex_members' => ':count since left',
    'members' => 'Who ratted',
    'members_note' => 'Biggest first by ISK ratted. Ex-members are marked with the day they left; hover for the exact time.',
    'top_systems' => 'Top systems',
    'total' => 'Total',

    // Column headings of the CSV download
    'csv' => [
        'rank' => 'Rank',
        'player' => 'Player',
        'characters' => 'Characters',
        'ratted' => 'ISK ratted',
        'corp_tax' => 'Corporation tax (ISK)',
        'received' => 'Pilots received (ISK)',
        'bounties' => 'Bounties received (ISK)',
        'ess' => 'ESS received (ISK)',
        'hours' => 'Hours',
        'isk_per_hour' => 'ISK per hour',
        'rats' => 'Rats',
        'active_days' => 'Active days',
        'left' => 'Left (EVE time)',
        'not_authed' => 'Not authed with SeAT',
    ],
];
