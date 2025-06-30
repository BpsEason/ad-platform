<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Tenant;
class SetTenant
{
    public function handle(Request $request, Closure $next)
    {
        $tenantId = null;
        if (Auth::check() && Auth::user()->tenant_id) {
            $tenantId = Auth::user()->tenant_id;
        } elseif ($request->hasHeader('X-Tenant-Id')) {
            $tenantId = $request->header('X-Tenant-Id');
            // Optional: Validate if tenant exists
            if (!Tenant::where('id', $tenantId)->exists()) {
                return response()->json(['error' => 'Invalid tenant ID.'], 403);
            }
        }
        if ($tenantId) {
            config(['current_tenant_id' => $tenantId]);
        }
        return $next($request);
    }
}
