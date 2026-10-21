<?php

namespace App\Http\Middleware;

use App\Models\Workshop;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiTokenIsValid
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        // 2. Check if token is missing
        if (!$token) {
            return response()->json([
                'message' => 'Unauthorized: API token is missing.'
            ], 401);
        }
        // 3. Check if the token exists in the database
        $workshop = Workshop::where('token', $token)->first();

        if (!$workshop) {
            return response()->json([
                'message' => 'Unauthorized: Invalid API token.'
            ], 401);
        }

        // Optional: Attach the user to the request so controllers can access it
        $request->merge(['workshop' => $workshop]);

        return $next($request);
    }
}
