<?php

namespace App\Http\Middleware;

use Closure;

class CheckIfEmailVerified
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if(!$request->user()->email_verified) {
            // 如果是AJAX請求 則透過JSON返回
           if ($request->expectsJson()) {
                return response()->json(['msg' => '請先驗證信箱'],400);
           }
           return redirect(route('email_verify_notice'));
        }
        return $next($request);
    }
}
