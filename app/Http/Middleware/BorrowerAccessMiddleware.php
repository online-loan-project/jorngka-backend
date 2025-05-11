<?php

namespace App\Http\Middleware;

use App\Constants\ConstUserRole;
use App\Traits\BaseApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BorrowerAccessMiddleware
{
    use BaseApiResponse;
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        //check if user not verified with phone number or not
        if (auth()->user()->phone_verified_at == null) {
            return $this->failed(null,"verify phone number",'Sorry! You need to verify your phone number first!',403);
        }

        //check if user is borrower or not
        if (auth()->user()->role == ConstUserRole::BORROWER) {
            return $next($request);
        }
        return $this->failed(null,'Unauthenticated','Sorry! Only Borrower can access!', 403);
    }
}
