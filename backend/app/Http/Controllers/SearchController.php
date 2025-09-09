<?php

namespace App\Http\Controllers;

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
     * Search products using Scout.
     */
    public function products(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'q' => 'required|string|min:2|max:255',
                'category_id' => 'nullable|integer|exists:categories,id',
                'min_price' => 'nullable|numeric|min:0',
                'max_price' => 'nullable|numeric|min:0',
                'is_featured' => 'nullable|boolean',
                'in_stock' => 'nullable|boolean',
                'tags' => 'nullable|array',
                'tags.*' => 'string|max:50',
                'sort' => 'nullable|string|in:relevance,price_asc,price_desc,newest,oldest,name_asc,name_desc',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            $params = $request->only([
                'q', 'category_id', 'min_price', 'max_price', 
                'is_featured', 'in_stock', 'tags', 'sort', 'per_page'
            ]);

            $products = $this->searchService->searchProducts($params);

            return response()->json([
                'success' => true,
                'data' => [
                    'products' => $products->items(),
                    'pagination' => [
                        'current_page' => $products->currentPage(),
                        'last_page' => $products->lastPage(),
                        'per_page' => $products->perPage(),
                        'total' => $products->total(),
                        'from' => $products->firstItem(),
                        'to' => $products->lastItem(),
                    ],
                    'filters_applied' => $params,
                ],
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Search failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get search suggestions.
     */
    public function suggestions(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'q' => 'required|string|min:1|max:255',
                'limit' => 'nullable|integer|min:1|max:20',
            ]);

            $query = $request->input('q');
            $limit = $request->input('limit', 10);

            $suggestions = $this->searchService->getSuggestions($query, $limit);

            return response()->json([
                'success' => true,
                'data' => [
                    'suggestions' => $suggestions,
                    'query' => $query,
                ],
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Suggestions failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get popular search terms.
     */
    public function popular(): JsonResponse
    {
        try {
            $popularTerms = $this->searchService->getPopularTerms();

            return response()->json([
                'success' => true,
                'data' => [
                    'popular_terms' => $popularTerms,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get popular terms',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get search filters for faceted search.
     */
    public function filters(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'q' => 'nullable|string|max:255',
            ]);

            $query = $request->input('q', '');
            $filters = $this->searchService->getSearchFilters($query);

            return response()->json([
                'success' => true,
                'data' => [
                    'filters' => $filters,
                    'query' => $query,
                ],
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get search filters',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Advanced search with multiple filters.
     */
    public function advanced(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'q' => 'nullable|string|max:255',
                'categories' => 'nullable|array',
                'categories.*' => 'integer|exists:categories,id',
                'tags' => 'nullable|array',
                'tags.*' => 'string|max:50',
                'price_range' => 'nullable|array|size:2',
                'price_range.*' => 'numeric|min:0',
                'attributes' => 'nullable|array',
                'in_stock' => 'nullable|boolean',
                'is_featured' => 'nullable|boolean',
                'sort' => 'nullable|string|in:relevance,price_asc,price_desc,newest,oldest,rating',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            $query = $request->input('q', '*');
            $categories = $request->input('categories', []);
            $tags = $request->input('tags', []);
            $priceRange = $request->input('price_range');
            $attributes = $request->input('attributes', []);
            $inStock = $request->input('in_stock');
            $isFeatured = $request->input('is_featured');
            $sort = $request->input('sort', 'relevance');
            $perPage = $request->input('per_page', 20);

            // Start with Scout search
            $searchQuery = Product::search($query);

            // Apply category filters
            if (!empty($categories)) {
                foreach ($categories as $categoryId) {
                    $searchQuery->where('category_ids', $categoryId);
                }
            }

            // Apply price range filter
            if ($priceRange && count($priceRange) === 2) {
                $searchQuery->where('price', '>=', $priceRange[0])
                           ->where('price', '<=', $priceRange[1]);
            }

            // Apply other filters
            if ($isFeatured !== null) {
                $searchQuery->where('is_featured', $isFeatured);
            }

            // Apply sorting
            switch ($sort) {
                case 'price_asc':
                    $searchQuery->orderBy('price', 'asc');
                    break;
                case 'price_desc':
                    $searchQuery->orderBy('price', 'desc');
                    break;
                case 'newest':
                    $searchQuery->orderBy('created_at', 'desc');
                    break;
                case 'oldest':
                    $searchQuery->orderBy('created_at', 'asc');
                    break;
                default:
                    // Relevance is default
                    break;
            }

            // Execute search
            $products = $searchQuery->paginate($perPage);

            // Load relationships
            $products->load(['categories', 'tags', 'reviews']);

            // Apply additional filters that Scout doesn't support
            if (!empty($tags) || $inStock !== null) {
                $productIds = $products->pluck('id');
                $filteredQuery = Product::whereIn('id', $productIds);

                if (!empty($tags)) {
                    $filteredQuery->whereHas('tags', function ($q) use ($tags) {
                        $q->whereIn('name', $tags);
                    });
                }

                if ($inStock !== null && $inStock) {
                    $filteredQuery->where(function ($q) {
                        $q->where('track_inventory', false)
                          ->orWhere('inventory_quantity', '>', 0);
                    });
                }

                $filteredProducts = $filteredQuery->paginate($perPage);
                $filteredProducts->load(['categories', 'tags', 'reviews']);
                $products = $filteredProducts;
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'products' => $products->items(),
                    'pagination' => [
                        'current_page' => $products->currentPage(),
                        'last_page' => $products->lastPage(),
                        'per_page' => $products->perPage(),
                        'total' => $products->total(),
                        'from' => $products->firstItem(),
                        'to' => $products->lastItem(),
                    ],
                    'filters_applied' => [
                        'query' => $query,
                        'categories' => $categories,
                        'tags' => $tags,
                        'price_range' => $priceRange,
                        'attributes' => $attributes,
                        'in_stock' => $inStock,
                        'is_featured' => $isFeatured,
                        'sort' => $sort,
                    ],
                ],
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Advanced search failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}