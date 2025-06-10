<?php

namespace App\Http\Middleware;

use App\Traits\BaseApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class VerifyPassword
{
    use BaseApiResponse;
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (!$user || !password_verify($request->input('password'), $user->password)) {
            return $this->failed(null, 'Password Required', 'Please Input Password or Retry',Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}