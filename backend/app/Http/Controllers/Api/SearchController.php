<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SearchService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class SearchController extends Controller
{
    protected SearchService $searchService;

    public function __construct(SearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    /**
     * Search products with advanced filters
     */
    public function searchProducts(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'q' => 'nullable|string|max:255',
                'category_id' => 'nullable|integer|exists:categories,id',
                'categories' => 'nullable|array',
                'categories.*' => 'integer|exists:categories,id',
                'min_price' => 'nullable|numeric|min:0',
                'max_price' => 'nullable|numeric|min:0',
                'is_featured' => 'nullable|boolean',
                'in_stock' => 'nullable|boolean',
                'tags' => 'nullable|array',
                'tags.*' => 'string',
                'sort' => 'nullable|string|in:relevance,price_asc,price_desc,name_asc,name_desc,newest,oldest',
                'per_page' => 'nullable|integer|min:1|max:100',
                'page' => 'nullable|integer|min:1'
            ]);

            $results = $this->searchService->searchProducts($validated);

            return response()->json([
                'success' => true,
                'data' => [
                    'products' => $results->items(),
                    'pagination' => [
                        'current_page' => $results->currentPage(),
                        'last_page' => $results->lastPage(),
                        'per_page' => $results->perPage(),
                        'total' => $results->total(),
                        'from' => $results->firstItem(),
                        'to' => $results->lastItem(),
                        'has_more_pages' => $results->hasMorePages()
                    ]
                ],
                'query' => $validated['q'] ?? '',
                'filters_applied' => array_filter($validated, function($value, $key) {
                    return !in_array($key, ['q', 'page', 'per_page']) && $value !== null;
                }, ARRAY_FILTER_USE_BOTH)
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Search error: ' . $e->getMessage(), [
                'request' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Search failed. Please try again.'
            ], 500);
        }
    }

    /**
     * Get search suggestions/autocomplete
     */
    public function suggestions(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'q' => 'required|string|min:2|max:255',
                'limit' => 'nullable|integer|min:1|max:20'
            ]);

            $suggestions = $this->searchService->getSearchSuggestions(
                $validated['q'],
                $validated['limit'] ?? 10
            );

            return response()->json([
                'success' => true,
                'data' => $suggestions,
                'query' => $validated['q']
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Suggestions error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to get suggestions'
            ], 500);
        }
    }

    /**
     * Get search facets for filtering
     */
    public function facets(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'q' => 'nullable|string|max:255',
                'category_id' => 'nullable|integer|exists:categories,id',
                'categories' => 'nullable|array',
                'categories.*' => 'integer|exists:categories,id'
            ]);

            $facets = $this->searchService->getFacets($validated);

            return response()->json([
                'success' => true,
                'data' => $facets
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Facets error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to get facets'
            ], 500);
        }
    }

    /**
     * Get popular search terms
     */
    public function popularSearches(): JsonResponse
    {
        try {
            $popularSearches = $this->searchService->getPopularSearches();

            return response()->json([
                'success' => true,
                'data' => $popularSearches
            ]);

        } catch (\Exception $e) {
            \Log::error('Popular searches error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to get popular searches'
            ], 500);
        }
    }

    /**
     * Log search query for analytics
     */
    public function logSearch(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'query' => 'required|string|max:255',
                'results_count' => 'required|integer|min:0',
                'user_id' => 'nullable|integer|exists:users,id',
                'filters' => 'nullable|array',
                'clicked_product_id' => 'nullable|integer|exists:products,id'
            ]);

            $this->searchService->logSearch($validated);

            return response()->json([
                'success' => true,
                'message' => 'Search logged successfully'
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Log search error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to log search'
            ], 500);
        }
    }

    /**
     * Get search analytics (admin only)
     */
    public function analytics(Request $request): JsonResponse
    {
        try {
            // Check if user has admin permissions
            if (!$request->user() || !$request->user()->hasRole('admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $validated = $request->validate([
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'limit' => 'nullable|integer|min:1|max:100'
            ]);

            $analytics = $this->searchService->getSearchAnalytics($validated);

            return response()->json([
                'success' => true,
                'data' => $analytics
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Analytics error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to get analytics'
            ], 500);
        }
    }

    /**
     * Reindex all products (admin only)
     */
    public function reindex(Request $request): JsonResponse
    {
        try {
            // Check if user has admin permissions
            if (!$request->user() || !$request->user()->hasRole('admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $this->searchService->reindexProducts();

            return response()->json([
                'success' => true,
                'message' => 'Products reindexed successfully'
            ]);

        } catch (\Exception $e) {
            \Log::error('Reindex error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to reindex products'
            ], 500);
        }
    }
}