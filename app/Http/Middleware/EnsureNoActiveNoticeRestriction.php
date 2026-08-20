<?php

namespace App\Http\Middleware;

use App\Services\NoticeRestrictionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureNoActiveNoticeRestriction
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        app(NoticeRestrictionService::class)
            ->assertUserMayApply(
                (int) $request->user()->user_id
            );

        return $next($request);
    }
}
