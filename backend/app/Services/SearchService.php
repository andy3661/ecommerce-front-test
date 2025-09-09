<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SearchService
{
    /**
     * Search products with filters and sorting.
     */
    public function searchProducts(array $params): LengthAwarePaginator
    {
        $query = $params['q'] ?? '*';
        $categoryId = $params['category_id'] ?? null;
        $minPrice = $params['min_price'] ?? null;
        $maxPrice = $params['max_price'] ?? null;
        $isFeatured = $params['is_featured'] ?? null;
        $sort = $params['sort'] ?? 'relevance';
        $perPage = $params['per_page'] ?? 20;
        $inStock = $params['in_stock'] ?? null;
        $tags = $params['tags'] ?? [];

        try {
            // Start with Scout search
            $searchQuery = Product::search($query);

            // Apply filters
            $this->applyFilters($searchQuery, [
                'category_id' => $categoryId,
                'min_price' => $minPrice,
                'max_price' => $maxPrice,
                'is_featured' => $isFeatured,
            ]);

            // Apply sorting
            $this->applySorting($searchQuery, $sort);

            // Execute search with pagination
            $products = $searchQuery->paginate($perPage);

            // Load relationships
            $products->load(['categories', 'tags', 'reviews']);

            // Apply additional filters that Scout doesn't support well
            if ($inStock !== null || !empty($tags)) {
                $products = $this->applyAdditionalFilters($products, [
                    'in_stock' => $inStock,
                    'tags' => $tags,
                ], $perPage);
            }

            return $products;

        } catch (\Exception $e) {
            Log::error('Search failed', [
                'query' => $query,
                'params' => $params,
                'error' => $e->getMessage()
            ]);
            
            // Fallback to database search
            return $this->fallbackSearch($params);
        }
    }

    /**
     * Get search suggestions based on query.
     */
    public function getSuggestions(string $query, int $limit = 10): Collection
    {
        $cacheKey = "search_suggestions_{$query}_{$limit}";
        
        return Cache::remember($cacheKey, 300, function () use ($query, $limit) {
            try {
                // Get product suggestions
                $productSuggestions = Product::search($query)
                    ->where('status', 'active')
                    ->take($limit)
                    ->get()
                    ->pluck('name')
                    ->unique()
                    ->take($limit / 2);

                // Get category suggestions
                $categorySuggestions = Category::where('name', 'LIKE', "%{$query}%")
                    ->where('is_active', true)
                    ->limit($limit / 2)
                    ->pluck('name');

                return $productSuggestions->merge($categorySuggestions)
                    ->unique()
                    ->take($limit)
                    ->values();

            } catch (\Exception $e) {
                Log::error('Suggestions failed', [
                    'query' => $query,
                    'error' => $e->getMessage()
                ]);
                
                return collect([]);
            }
        });
    }

    /**
     * Get popular search terms.
     */
    public function getPopularTerms(int $limit = 10, int $days = 30): array
    {
        try {
            $popularTerms = DB::table('search_analytics')
                ->where('date', '>=', now()->subDays($days))
                ->groupBy('query')
                ->orderByDesc(DB::raw('SUM(count)'))
                ->limit($limit)
                ->pluck('query')
                ->toArray();

            // If no data available, return fallback terms
            if (empty($popularTerms)) {
                return [
                    'smartphone',
                    'laptop',
                    'headphones',
                    'camera',
                    'tablet',
                    'watch',
                    'gaming',
                    'accessories',
                ];
            }

            return $popularTerms;
        } catch (\Exception $e) {
            Log::warning('Failed to get popular search terms', ['error' => $e->getMessage()]);
            
            // Return fallback terms on error
            return [
                'smartphone',
                'laptop',
                'headphones',
                'camera',
                'tablet',
            ];
        }
    }

    /**
     * Get search filters for faceted search.
     */
    public function getSearchFilters(string $query = ''): array
    {
        $cacheKey = "search_filters_{$query}";
        
        return Cache::remember($cacheKey, 600, function () use ($query) {
            try {
                $baseQuery = empty($query) ? Product::query() : Product::search($query);
                
                if (empty($query)) {
                    $products = $baseQuery->where('status', 'active')->get();
                } else {
                    $products = $baseQuery->where('status', 'active')->get();
                }

                // Get price range
                $priceRange = [
                    'min' => $products->min('price') ?? 0,
                    'max' => $products->max('price') ?? 0,
                ];

                // Get categories
                $categoryIds = $products->pluck('categories')->flatten()->pluck('id')->unique();
                $categories = Category::whereIn('id', $categoryIds)
                    ->where('is_active', true)
                    ->select('id', 'name', 'slug')
                    ->get();

                // Get brands (if you have a brand field)
                $brands = $products->pluck('brand')->filter()->unique()->values();

                return [
                    'price_range' => $priceRange,
                    'categories' => $categories,
                    'brands' => $brands,
                    'has_featured' => $products->where('is_featured', true)->isNotEmpty(),
                ];

            } catch (\Exception $e) {
                Log::error('Failed to get search filters', [
                    'query' => $query,
                    'error' => $e->getMessage()
                ]);
                
                return [
                    'price_range' => ['min' => 0, 'max' => 0],
                    'categories' => [],
                    'brands' => [],
                    'has_featured' => false,
                ];
            }
        });
    }

    /**
     * Apply filters to search query.
     */
    private function applyFilters($searchQuery, array $filters): void
    {
        if ($filters['category_id']) {
            $searchQuery->where('category_ids', $filters['category_id']);
        }

        if ($filters['min_price'] !== null) {
            $searchQuery->where('price', '>=', $filters['min_price']);
        }

        if ($filters['max_price'] !== null) {
            $searchQuery->where('price', '<=', $filters['max_price']);
        }

        if ($filters['is_featured'] !== null) {
            $searchQuery->where('is_featured', $filters['is_featured']);
        }
    }

    /**
     * Apply sorting to search query.
     */
    private function applySorting($searchQuery, string $sort): void
    {
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
            case 'name_asc':
                $searchQuery->orderBy('name', 'asc');
                break;
            case 'name_desc':
                $searchQuery->orderBy('name', 'desc');
                break;
            default:
                // Relevance is default for Scout
                break;
        }
    }

    /**
     * Apply additional filters that Scout doesn't handle well.
     */
    private function applyAdditionalFilters(LengthAwarePaginator $products, array $filters, int $perPage): LengthAwarePaginator
    {
        $productIds = $products->pluck('id');
        $query = Product::whereIn('id', $productIds);

        if ($filters['in_stock'] !== null && $filters['in_stock']) {
            $query->where(function ($q) {
                $q->where('track_inventory', false)
                  ->orWhere('inventory_quantity', '>', 0);
            });
        }

        if (!empty($filters['tags'])) {
            $query->whereHas('tags', function ($q) use ($filters) {
                $q->whereIn('name', $filters['tags']);
            });
        }

        $filteredProducts = $query->paginate($perPage);
        $filteredProducts->load(['categories', 'tags', 'reviews']);
        
        return $filteredProducts;
    }

    /**
     * Fallback to database search when Scout fails.
     */
    private function fallbackSearch(array $params): LengthAwarePaginator
    {
        $query = $params['q'] ?? '';
        $perPage = $params['per_page'] ?? 20;

        $searchQuery = Product::where('status', 'active');

        if (!empty($query) && $query !== '*') {
            $searchQuery->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                  ->orWhere('description', 'LIKE', "%{$query}%")
                  ->orWhere('short_description', 'LIKE', "%{$query}%")
                  ->orWhere('sku', 'LIKE', "%{$query}%")
                  ->orWhere('barcode', 'LIKE', "%{$query}%");
            });
        }

        // Apply basic filters
        if (isset($params['category_id'])) {
            $searchQuery->whereHas('categories', function ($q) use ($params) {
                $q->where('categories.id', $params['category_id']);
            });
        }

        if (isset($params['min_price'])) {
            $searchQuery->where('price', '>=', $params['min_price']);
        }

        if (isset($params['max_price'])) {
            $searchQuery->where('price', '<=', $params['max_price']);
        }

        if (isset($params['is_featured'])) {
            $searchQuery->where('is_featured', $params['is_featured']);
        }

        // Apply sorting
        $sort = $params['sort'] ?? 'relevance';
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
                $searchQuery->orderBy('name', 'asc');
                break;
        }

        $products = $searchQuery->with(['categories', 'tags', 'reviews'])
                                ->paginate($perPage);

        return $products;
    }

    /**
     * Get search suggestions (alias for getSuggestions).
     */
    public function getSearchSuggestions(string $query, int $limit = 10): Collection
    {
        return $this->getSuggestions($query, $limit);
    }
}