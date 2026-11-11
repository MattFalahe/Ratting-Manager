<?php

/*
|--------------------------------------------------------------------------
| Ratting Manager - Configuration
|--------------------------------------------------------------------------
|
| Everything an operator changes lives on the Settings page. These are the
| few values that only matter to an install, not to a corporation.
|
*/

return [

    // Public ESI, for names of characters SeAT holds no token for.
    'esi_base_url' => env('RATTING_MANAGER_ESI_URL', 'https://esi.evetech.net/latest'),

    // The longest dashboard and leaderboard figures are kept. They are worked
    // out again after every import anyway, since that is the only time they
    // change, so this only matters when imports stop.
    'cache_seconds' => 3600,

];
