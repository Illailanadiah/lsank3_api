<?php

namespace App\Http\Middleware;

use App\Services\LegalEscalationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureNotUnderLegal
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        app(LegalEscalationService::class)
            ->assertNotUnderLegal(
                (int) $request
                    ->user()
                    ->user_id
            );

        return $next($request);
    }
}
