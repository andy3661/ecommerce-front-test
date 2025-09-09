<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\Database\Eloquent\Builder;

class ProductController extends Controller
{
    /**
     * Display a listing of products with filters
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'category_id' => 'nullable|exists:categories,id',
            'category_slug' => 'nullable|exists:categories,slug',
            'featured' => 'nullable|boolean',
            'status' => 'nullable|in:active,inactive,draft',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0',
            'search' => 'nullable|string|max:255',
            'sort_by' => 'nullable|in:name,price,created_at,popularity,rating',
            'sort_order' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1'
        ]);

        $query = Product::query()
            ->with(['categories', 'reviews' => function ($q) {
                $q->where('is_approved', true);
            }])
            ->active()
            ->published();

        // Apply filters
        if ($request->filled('category_id')) {
            $query->byCategory($request->category_id);
        }

        if ($request->filled('category_slug')) {
            $category = Category::where('slug', $request->category_slug)->first();
            if ($category) {
                $query->byCategory($category->id);
            }
        }

        if ($request->filled('featured')) {
            $query->featured();
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('min_price') || $request->filled('max_price')) {
            $query->priceRange($request->min_price, $request->max_price);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%")
                  ->orWhere('short_description', 'LIKE', "%{$search}%")
                  ->orWhere('sku', 'LIKE', "%{$search}%");
            });
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        switch ($sortBy) {
            case 'name':
                $query->orderBy('name', $sortOrder);
                break;
            case 'price':
                $query->orderBy('price', $sortOrder);
                break;
            case 'popularity':
                $query->withCount('orderItems')
                      ->orderBy('order_items_count', $sortOrder);
                break;
            case 'rating':
                $query->withAvg('approvedReviews', 'rating')
                      ->orderBy('approved_reviews_avg_rating', $sortOrder);
                break;
            default:
                $query->orderBy('created_at', $sortOrder);
        }

        $perPage = $request->get('per_page', 15);
        $products = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $products->items(),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'from' => $products->firstItem(),
                'to' => $products->lastItem()
            ],
            'filters' => [
                'category_id' => $request->category_id,
                'category_slug' => $request->category_slug,
                'featured' => $request->boolean('featured'),
                'min_price' => $request->min_price,
                'max_price' => $request->max_price,
                'search' => $request->search,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder
            ]
        ]);
    }

    /**
     * Display the specified product
     */
    public function show(Request $request, $identifier): JsonResponse
    {
        $query = Product::query()
            ->with([
                'categories',
                'approvedReviews.user:id,name',
                'approvedReviews' => function ($q) {
                    $q->orderBy('created_at', 'desc');
                }
            ])
            ->withCount('approvedReviews')
            ->withAvg('approvedReviews', 'rating');

        // Try to find by ID first, then by slug
        if (is_numeric($identifier)) {
            $product = $query->find($identifier);
        } else {
            $product = $query->where('slug', $identifier)->first();
        }

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ], 404);
        }

        // Check if product is active and published
        if ($product->status !== 'active' || !$product->published_at || $product->published_at > now()) {
            return response()->json([
                'success' => false,
                'message' => 'Product not available'
            ], 404);
        }

        // Get related products from same categories
        $relatedProducts = Product::query()
            ->whereHas('categories', function ($q) use ($product) {
                $q->whereIn('categories.id', $product->categories->pluck('id'));
            })
            ->where('id', '!=', $product->id)
            ->active()
            ->published()
            ->inStock()
            ->limit(8)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $product,
            'related_products' => $relatedProducts
        ]);
    }

    /**
     * Get featured products
     */
    public function featured(Request $request): JsonResponse
    {
        $request->validate([
            'limit' => 'nullable|integer|min:1|max:50'
        ]);

        $limit = $request->get('limit', 12);

        $products = Product::query()
            ->with('categories')
            ->active()
            ->published()
            ->featured()
            ->inStock()
            ->orderBy('sort_order')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    /**
     * Get product filters for category
     */
    public function filters(Request $request): JsonResponse
    {
        $request->validate([
            'category_id' => 'nullable|exists:categories,id',
            'category_slug' => 'nullable|exists:categories,slug'
        ]);

        $query = Product::query()->active()->published();

        if ($request->filled('category_id')) {
            $query->byCategory($request->category_id);
        }

        if ($request->filled('category_slug')) {
            $category = Category::where('slug', $request->category_slug)->first();
            if ($category) {
                $query->byCategory($category->id);
            }
        }

        // Get price range
        $priceRange = [
            'min' => $query->min('price') ?? 0,
            'max' => $query->max('price') ?? 0
        ];

        // Get available attributes (if using variants)
        $attributes = $query->whereNotNull('attributes')
            ->pluck('attributes')
            ->filter()
            ->flatten(1)
            ->unique()
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'price_range' => $priceRange,
                'attributes' => $attributes,
                'total_products' => $query->count()
            ]
        ]);
    }

    /**
     * Search products
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2|max:255',
            'category_id' => 'nullable|exists:categories,id',
            'limit' => 'nullable|integer|min:1|max:50'
        ]);

        $query = $request->q;
        $limit = $request->get('limit', 20);

        $products = Product::query()
            ->with('categories')
            ->active()
            ->published()
            ->where(function (Builder $q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                  ->orWhere('description', 'LIKE', "%{$query}%")
                  ->orWhere('short_description', 'LIKE', "%{$query}%")
                  ->orWhere('sku', 'LIKE', "%{$query}%");
            })
            ->when($request->filled('category_id'), function ($q) use ($request) {
                $q->byCategory($request->category_id);
            })
            ->orderByRaw("CASE 
                WHEN name LIKE ? THEN 1 
                WHEN short_description LIKE ? THEN 2 
                WHEN description LIKE ? THEN 3 
                WHEN sku LIKE ? THEN 4 
                ELSE 5 END", [
                "%{$query}%", "%{$query}%", "%{$query}%", "%{$query}%"
            ])
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $products,
            'query' => $query,
            'total' => $products->count()
        ]);
    }
}