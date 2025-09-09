<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()) {
            return response()->json([
                'message' => 'No autenticado'
            ], 401);
        }

        // Check if user has admin access permission or is admin/super-admin
        if (!$request->user()->hasPermission('admin.access') && !$request->user()->isAdmin()) {
            return response()->json([
                'message' => 'Acceso denegado al panel administrativo'
            ], 403);
        }

        // Check if user account is active
        if (!$request->user()->is_active) {
            return response()->json([
                'message' => 'Tu cuenta está desactivada'
            ], 403);
        }

        return $next($request);
    }
}