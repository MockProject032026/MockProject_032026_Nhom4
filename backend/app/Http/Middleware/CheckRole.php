<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * Kiểm tra id_role của user có nằm trong danh sách role được phép không.
     * Sử dụng: middleware('checkRole:1,2') => chỉ cho phép Admin (1) và Compliance (2)
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles  Danh sách id_role được phép (truyền qua tham số middleware)
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // Kiểm tra id_role của user có nằm trong danh sách role được phép
        if (!in_array((string) $user->id_role, $roles, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden. You do not have permission to access this resource.',
            ], 403);
        }

        return $next($request);
    }
}
