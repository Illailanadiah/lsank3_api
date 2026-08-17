<?php

namespace App\Http\Middleware;

use App\Services\LegalRoleService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLegalDepartment
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $allowed =
            app(LegalRoleService::class)
                ->isLegalUser(
                    $request->user()
                );

        abort_unless(
            $allowed,
            403,
            'Hanya Unit Perundangan boleh mencipta atau mengemas kini rekod kes.'
        );

        return $next($request);
    }
}
