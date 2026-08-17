<?php

namespace App\Http\Middleware;

use App\Models\LsankApplication;
use App\Services\LegalEscalationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApplicationNotUnderLegal
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $routeValue =
            $request->route('application')
            ?? $request->route(
                'applicationId'
            )
            ?? $request->route('id');

        $application =
            $routeValue
                instanceof LsankApplication
                ? $routeValue
                : LsankApplication::query()
                    ->where(
                        'application_id',
                        (int) $routeValue
                    )
                    ->firstOrFail();

        app(LegalEscalationService::class)
            ->assertNotUnderLegal(
                (int) $application->user_id
            );

        return $next($request);
    }
}
