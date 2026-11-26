<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class EnsurePermission {
    public function handle(Request $request, Closure $next, string $permission) {
        $user=$request->user();
        $permissions=explode(',',$permission);
        abort_unless($user && ($user->role==='owner' || collect($permissions)->contains(fn($item)=>$user->canAccess($item))), 403);
        return $next($request);
    }
}
