<?php

namespace App\Http\Middleware;

use App\Models\BasicSettings\Basic;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class Deactive
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next, $type = null)
    {
        if (Session::get('secret_login') != 1) {
            if ($type == 'organizer') {
                if (Auth::guard('organizer')->check() && (int) Auth::guard('organizer')->user()->status === 0) {
                    Auth::guard('organizer')->logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                    session()->flash('warning', 'Your account is inactive or pending. Please contact support.');
                    return redirect()->route('organizer.login');
                }
            } elseif ($type == 'customer') {
                if (Auth::guard('customer')->check() && (int) Auth::guard('customer')->user()->status === 0) {
                    Auth::guard('customer')->logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                    return redirect()->route('customer.login');
                }
            } elseif ($type == 'admin') {
                if (Auth::guard('admin')->check() && (int) Auth::guard('admin')->user()->status === 0) {
                    Auth::guard('admin')->logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                    return redirect()->route('admin.login')->with('alert', 'Your administrator account is inactive.');
                }
            }
        }
        return $next($request);
    }
}
