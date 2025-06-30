<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Exceptions\UnauthorizedException;
class PermissionMiddleware
{
    public function handle(Request $request, Closure $next, $permission, $guard = null)
    {
        $authGuard = Auth::guard($guard);
        if ($authGuard->guest()) {
            throw UnauthorizedException::notLoggedIn();
        }
        $permissions = is_array($permission) ? $permission : explode('|', $permission);
        foreach ($permissions as $permission) {
            if ($authGuard->user()->can($permission)) {
                return $next($request);
            }
        }
        throw UnauthorizedException::forPermissions($permissions);
    }
}
