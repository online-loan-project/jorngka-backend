<?php

namespace App\Http\Middleware;

use App\Constants\ConstUserRole;
use App\Traits\BaseApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminAccessMiddleware
{

    use BaseApiResponse;
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        //check if user is admin or not
        if (auth()->user()->role == ConstUserRole::ADMIN) {
            return $next($request);
        }
        return $this->failed(null, 'Unauthenticated','Sorry! Only Admin can access!', 403);
    }
}
