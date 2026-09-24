<?php

declare(strict_types=1);

namespace Darvis\ApiX\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Let only visitors through whom the `postToX` gate allows.
 *
 * The page posts publicly as the account the keys belong to, so a login alone is not enough:
 * on a site with public registration every customer has one. Without a gate of the host
 * application only the local environment is allowed, the way Horizon and Telescope do it.
 */
class AuthorizeXPage
{
    public const ABILITY = 'postToX';

    /**
     * @param  Closure(Request): SymfonyResponse  $next
     */
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        self::authorize();

        return $next($request);
    }

    /**
     * Abort with 403 unless the gate allows the current visitor, guest or not.
     */
    public static function authorize(): void
    {
        abort_unless(Gate::allows(self::ABILITY), SymfonyResponse::HTTP_FORBIDDEN);
    }
}
