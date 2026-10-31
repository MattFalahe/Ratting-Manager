<?php

namespace RattingManager\Http\Controllers;

use Illuminate\Http\Request;
use RattingManager\Services\Character\AffiliationResolutionService;
use RattingManager\Services\Character\Players;
use Seat\Web\Http\Controllers\Controller;

/**
 * Lets a page that showed characters as in progress find out when the
 * background lookup has them. Reads the plugin's table only.
 */
class CharacterLookupController extends Controller
{
    public function pending(Request $request, AffiliationResolutionService $resolver)
    {
        // Only characters a page showed this user. Answering for any ID would
        // tell a member whether someone rats for a tracked corporation.
        $shown = array_map('intval', (array) $request->session()->get(Players::SHOWN_PENDING, []));
        $asked = array_map('intval', array_slice(explode(',', (string) $request->query('ids', '')), 0, 500));
        $ids = array_values(array_intersect($asked, $shown));

        return response()->json(['pending' => $ids === [] ? [] : $resolver->stillPending($ids)]);
    }
}
