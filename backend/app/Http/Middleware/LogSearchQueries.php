<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogSearchQueries
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only log successful search requests
        if ($response->getStatusCode() === 200 && $request->has('q')) {
            $this->logSearchQuery($request);
        }

        return $response;
    }

    /**
     * Log the search query for analytics.
     */
    private function logSearchQuery(Request $request): void
    {
        try {
            $query = trim($request->input('q'));
            
            if (empty($query) || strlen($query) < 2) {
                return;
            }

            // Normalize the query
            $normalizedQuery = strtolower($query);

            // Insert or update search analytics
            DB::table('search_analytics')->updateOrInsert(
                [
                    'query' => $normalizedQuery,
                    'date' => now()->format('Y-m-d'),
                ],
                [
                    'count' => DB::raw('count + 1'),
                    'last_searched_at' => now(),
                    'updated_at' => now(),
                ]
            );

            // Also log individual search events for detailed analytics
            DB::table('search_logs')->insert([
                'query' => $query,
                'normalized_query' => $normalizedQuery,
                'user_id' => auth()->id(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'filters' => json_encode($request->except(['q', '_token'])),
                'results_count' => $this->extractResultsCount($request),
                'created_at' => now(),
            ]);

        } catch (\Exception $e) {
            // Log the error but don't break the response
            Log::warning('Failed to log search query', [
                'query' => $request->input('q'),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Extract results count from the request context.
     */
    private function extractResultsCount(Request $request): ?int
    {
        // This would need to be implemented based on how you want to track results
        // For now, return null as we don't have access to the response data here
        return null;
    }
}