<?php

return [
    'period' => 'Period',
    'period_this_month' => 'This month',
    'period_last_month' => 'Last month',
    'period_30d' => 'Last 30 days',
    'period_90d' => 'Last 90 days',
    'period_12m' => 'Last 12 months',
    'period_months' => 'Month',
    'period_custom' => 'Custom range',
    'period_from' => 'From',
    'period_to' => 'to',
    'period_range' => ':from to :to',
    'period_week' => 'Week',
    'period_month' => 'Month',

    'corporation' => 'Corporation',
    'all_corporations' => 'All corporations',
    'character' => 'Character',
    'all_characters' => 'All my characters',
    'apply' => 'Show',

    'size_small' => 'Frigates and destroyers',
    'size_medium' => 'Cruisers and battlecruisers',
    'size_large' => 'Battleships',
    'size_capital' => 'Capitals',
    'size_unknown' => 'Size not known',

    'rank_regular' => 'Regular',
    'rank_clone' => 'Clone soldier',
    'rank_commander' => 'Commander',
    'rank_officer' => 'Officer',

    'system_not_given' => 'System not given',
    'eve_time' => 'EVE time',
    'no_data' => 'Nothing in this period yet.',
    'worked_out' => 'est.',
    'worked_out_title' => 'Worked out from the corporation\'s cut, because the character\'s own wallet journal is not on SeAT.',

    'coverage_full' => 'Tracked',
    'coverage_partial' => 'Partly tracked',
    'coverage_none' => 'Untracked',
    'coverage_title' => ':percent% of the ticks the corporation journal saw are also in the character\'s own journal.',

    'not_configured_title' => 'No corporation is set up yet',
    'not_configured_admin' => 'Pick the corporations to track on the Settings page, then run the initial import.',
    'not_configured_member' => 'An admin has to pick the corporations to track before anything shows here.',
    'open_settings' => 'Open Settings',

    // Characters SeAT does not know, while the background lookup runs
    'character_pending' => 'Character info in progress',
    'characters_pending_notice' => 'Looking up :count character(s) in the background. This page refreshes when they are in.',
    'characters_ready_notice' => 'Character info is in. Refresh the page to see it.',

    // A player none of whose characters is in the corporations shown now
    'left' => 'Ex-member',
    'left_on' => 'Ex-member, left :date',
    'left_by' => 'Ex-member, gone by :date',
    'left_title' => 'None of this player\'s characters is in these corporations now. Their ratting still counts for the corporation they were in at the time.',
    'left_title_exact' => 'Left at :time EVE, by EVE\'s corporation history. Their ratting still counts for the corporation they were in at the time.',
    'left_title_seen' => 'Gone by :time EVE. No corporation history has answered yet, so this is when the move was first known of, not when it happened.',
    'left_last_tick' => 'Last tick for these corporations: :time EVE.',

    // A player SeAT holds no live token for
    'not_authed' => 'Not authed with SeAT',
    'not_authed_never' => 'None of this player\'s characters was ever added to SeAT. They still count: their time and rats come from the corporation journal, with their income worked out from the corporation\'s cut, and they are watched for leaving all the same.',
    'not_authed_lost' => 'This player had tokens on SeAT and has none left, lost or revoked. They still count: their time and rats come from the corporation journal, with their income worked out from the corporation\'s cut, and they are watched for leaving all the same.',

    // Who may open what
    'no_corporation_to_read' => 'None of your characters is in a tracked corporation now, so there is no corporation here for you to read.',
    'closed_out' => 'These pages are only open to players with a character in a tracked corporation.',

    'last_import' => 'Last import',
    'never' => 'never',
];
