<?php

namespace App\Http\Middleware;

use App\Constants\AppConstants;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class ActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if($user !== null && $user->status !== AppConstants:: STATUS_ACTIVE){
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        if($user?->password_status === 0 && ! $request->routeIs('password.*')){
            return redirect()->route('password.edit');
        }
        return $next($request);
    }
}
