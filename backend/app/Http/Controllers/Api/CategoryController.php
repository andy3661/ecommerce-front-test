<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    /**
     * Display a listing of categories
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'parent_id' => 'nullable|exists:categories,id',
            'featured' => 'nullable|boolean',
            'with_children' => 'nullable|boolean',
            'with_products_count' => 'nullable|boolean',
            'status' => 'nullable|in:active,inactive,draft'
        ]);

        $query = Category::query()->active();

        // Filter by parent
        if ($request->has('parent_id')) {
            if ($request->parent_id === null || $request->parent_id === 'null') {
                $query->rootCategories();
            } else {
                $query->where('parent_id', $request->parent_id);
            }
        }

        // Filter by featured
        if ($request->filled('featured')) {
            $query->featured();
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Include children if requested
        if ($request->boolean('with_children')) {
            $query->withChildren();
        }

        // Include products count if requested
        if ($request->boolean('with_products_count')) {
            $query->withCount(['activeProducts']);
        }

        $categories = $query->ordered()->get();

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }

    /**
     * Display the specified category
     */
    public function show(Request $request, $identifier): JsonResponse
    {
        $request->validate([
            'with_children' => 'nullable|boolean',
            'with_products' => 'nullable|boolean',
            'with_breadcrumb' => 'nullable|boolean'
        ]);

        $query = Category::query()->active();

        // Include children if requested
        if ($request->boolean('with_children')) {
            $query->withChildren();
        }

        // Include products if requested
        if ($request->boolean('with_products')) {
            $query->with(['activeProducts' => function ($q) {
                $q->limit(12)->orderBy('sort_order')->orderBy('created_at', 'desc');
            }]);
        }

        // Try to find by ID first, then by slug
        if (is_numeric($identifier)) {
            $category = $query->find($identifier);
        } else {
            $category = $query->where('slug', $identifier)->first();
        }

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found'
            ], 404);
        }

        $response = [
            'success' => true,
            'data' => $category
        ];

        // Add breadcrumb if requested
        if ($request->boolean('with_breadcrumb')) {
            $response['breadcrumb'] = $category->getBreadcrumb();
        }

        return response()->json($response);
    }

    /**
     * Get category tree (hierarchical structure)
     */
    public function tree(Request $request): JsonResponse
    {
        $request->validate([
            'max_depth' => 'nullable|integer|min:1|max:5',
            'featured_only' => 'nullable|boolean',
            'with_products_count' => 'nullable|boolean'
        ]);

        $maxDepth = $request->get('max_depth', 3);
        $featuredOnly = $request->boolean('featured_only');
        $withProductsCount = $request->boolean('with_products_count');

        $query = Category::query()
            ->active()
            ->rootCategories();

        if ($featuredOnly) {
            $query->featured();
        }

        if ($withProductsCount) {
            $query->withCount(['activeProducts']);
        }

        $categories = $query->ordered()->get();

        // Load nested children up to max depth
        $this->loadNestedChildren($categories, $maxDepth - 1, $featuredOnly, $withProductsCount);

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }

    /**
     * Get featured categories
     */
    public function featured(Request $request): JsonResponse
    {
        $request->validate([
            'limit' => 'nullable|integer|min:1|max:50',
            'with_children' => 'nullable|boolean',
            'with_products_count' => 'nullable|boolean'
        ]);

        $limit = $request->get('limit', 12);

        $query = Category::query()
            ->active()
            ->featured();

        if ($request->boolean('with_children')) {
            $query->withChildren();
        }

        if ($request->boolean('with_products_count')) {
            $query->withCount(['activeProducts']);
        }

        $categories = $query->ordered()->limit($limit)->get();

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }

    /**
     * Get category breadcrumb
     */
    public function breadcrumb($identifier): JsonResponse
    {
        // Try to find by ID first, then by slug
        if (is_numeric($identifier)) {
            $category = Category::active()->find($identifier);
        } else {
            $category = Category::active()->where('slug', $identifier)->first();
        }

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $category->getBreadcrumb()
        ]);
    }

    /**
     * Search categories
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2|max:255',
            'limit' => 'nullable|integer|min:1|max:50'
        ]);

        $query = $request->q;
        $limit = $request->get('limit', 20);

        $categories = Category::query()
            ->active()
            ->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                  ->orWhere('description', 'LIKE', "%{$query}%");
            })
            ->withCount(['activeProducts'])
            ->ordered()
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $categories,
            'query' => $query,
            'total' => $categories->count()
        ]);
    }

    /**
     * Get categories with their product counts
     */
    public function withProductCounts(Request $request): JsonResponse
    {
        $request->validate([
            'parent_id' => 'nullable|exists:categories,id',
            'min_products' => 'nullable|integer|min:0'
        ]);

        $query = Category::query()
            ->active()
            ->withCount(['activeProducts']);

        if ($request->has('parent_id')) {
            if ($request->parent_id === null || $request->parent_id === 'null') {
                $query->rootCategories();
            } else {
                $query->where('parent_id', $request->parent_id);
            }
        }

        if ($request->filled('min_products')) {
            $query->having('active_products_count', '>=', $request->min_products);
        }

        $categories = $query->ordered()->get();

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }

    /**
     * Load nested children for categories
     */
    private function loadNestedChildren($categories, $depth, $featuredOnly = false, $withProductsCount = false)
    {
        if ($depth <= 0) {
            return;
        }

        foreach ($categories as $category) {
            $childrenQuery = $category->children();

            if ($featuredOnly) {
                $childrenQuery->where('is_featured', true);
            }

            if ($withProductsCount) {
                $childrenQuery->withCount(['activeProducts']);
            }

            $children = $childrenQuery->get();
            $category->setRelation('children', $children);

            if ($children->isNotEmpty()) {
                $this->loadNestedChildren($children, $depth - 1, $featuredOnly, $withProductsCount);
            }
        }
    }
}