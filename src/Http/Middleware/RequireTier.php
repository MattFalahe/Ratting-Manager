<?php

namespace RattingManager\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use RattingManager\Support\Access;

class RequireTier
{
    public function handle(Request $request, Closure $next, string $tier)
    {
        if (!Access::atLeast($tier, $request->user())) {
            abort(403);
        }

        // Help is the view tier's and stays open to anyone holding a permission.
        if ($tier !== 'view' && Access::closedOut($request->user())) {
            abort(403, trans('ratting-manager::common.closed_out'));
        }

        return $next($request);
    }
}
