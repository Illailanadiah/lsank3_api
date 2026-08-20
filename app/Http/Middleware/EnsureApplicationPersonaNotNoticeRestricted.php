<?php

namespace App\Http\Middleware;

use App\Models\LsankApplication;
use App\Services\NoticeRestrictionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApplicationPersonaNotNoticeRestricted
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $routeValue =
            $request->route('application')
            ?? $request->route('applicationId')
            ?? $request->route('id');

        $application =
            $routeValue instanceof LsankApplication
                ? $routeValue
                : LsankApplication::query()
                    ->where(
                        'application_id',
                        (int) $routeValue
                    )
                    ->firstOrFail();

        app(NoticeRestrictionService::class)
            ->assertApplicationMayProceed(
                $application
            );

        return $next($request);
    }
}
