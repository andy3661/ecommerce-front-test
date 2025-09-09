<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('role:admin');
    }

    /**
     * Display a listing of categories
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Category::withCount('products');

            // Search
            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            // Filter by status
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }

            // Filter by parent category
            if ($request->filled('parent_id')) {
                $query->where('parent_id', $request->input('parent_id'));
            }

            // Show only root categories
            if ($request->boolean('root_only')) {
                $query->whereNull('parent_id');
            }

            // Sorting
            $sortBy = $request->input('sort_by', 'sort_order');
            $sortOrder = $request->input('sort_order', 'asc');
            $query->orderBy($sortBy, $sortOrder);

            // Get tree structure or flat list
            if ($request->boolean('tree')) {
                $categories = $query->whereNull('parent_id')
                    ->with('children.children')
                    ->get();
                
                return response()->json([
                    'success' => true,
                    'data' => $categories
                ]);
            }

            // Pagination for flat list
            $perPage = $request->input('per_page', 15);
            $categories = $query->with('parent:id,name')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $categories,
                'filters' => [
                    'statuses' => ['active', 'inactive'],
                    'parent_categories' => Category::whereNull('parent_id')
                        ->select('id', 'name')
                        ->orderBy('name')
                        ->get()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading categories',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created category
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:categories,id',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'icon' => 'nullable|string|max:100',
            'status' => 'required|in:active,inactive',
            'featured' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Validate parent category depth (max 3 levels)
        if ($request->filled('parent_id')) {
            $parent = Category::find($request->input('parent_id'));
            if ($parent && $parent->getDepth() >= 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'Maximum category depth (3 levels) exceeded'
                ], 422);
            }
        }

        try {
            $categoryData = $request->except(['image']);
            $categoryData['slug'] = Str::slug($request->input('name'));
            
            // Ensure unique slug
            $originalSlug = $categoryData['slug'];
            $counter = 1;
            while (Category::where('slug', $categoryData['slug'])->exists()) {
                $categoryData['slug'] = $originalSlug . '-' . $counter;
                $counter++;
            }

            // Set sort order if not provided
            if (!$request->filled('sort_order')) {
                $maxOrder = Category::where('parent_id', $request->input('parent_id'))
                    ->max('sort_order') ?? 0;
                $categoryData['sort_order'] = $maxOrder + 1;
            }

            $category = Category::create($categoryData);

            // Handle image upload
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $filename = time() . '.' . $image->getClientOriginalExtension();
                $path = $image->storeAs('categories', $filename, 'public');
                $category->update(['image' => Storage::url($path)]);
            }

            $category->load('parent:id,name');

            return response()->json([
                'success' => true,
                'message' => 'Category created successfully',
                'data' => $category
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating category',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified category
     */
    public function show(Category $category): JsonResponse
    {
        try {
            $category->load([
                'parent:id,name',
                'children' => function($query) {
                    $query->withCount('products');
                },
                'products' => function($query) {
                    $query->select('id', 'name', 'sku', 'price', 'stock_quantity', 'status')
                          ->limit(10);
                }
            ]);
            
            // Add statistics
            $category->stats = [
                'total_products' => $category->products()->count(),
                'active_products' => $category->products()->where('status', 'active')->count(),
                'total_children' => $category->children()->count(),
                'depth' => $category->getDepth()
            ];

            return response()->json([
                'success' => true,
                'data' => $category
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading category',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified category
     */
    public function update(Request $request, Category $category): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'parent_id' => [
                'nullable',
                'exists:categories,id',
                function ($attribute, $value, $fail) use ($category) {
                    // Prevent setting self as parent
                    if ($value == $category->id) {
                        $fail('Category cannot be its own parent.');
                    }
                    // Prevent setting descendant as parent
                    if ($value && $category->isAncestorOf(Category::find($value))) {
                        $fail('Cannot set descendant category as parent.');
                    }
                }
            ],
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'icon' => 'nullable|string|max:100',
            'status' => 'required|in:active,inactive',
            'featured' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Validate parent category depth
        if ($request->filled('parent_id')) {
            $parent = Category::find($request->input('parent_id'));
            if ($parent && $parent->getDepth() >= 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'Maximum category depth (3 levels) exceeded'
                ], 422);
            }
        }

        try {
            $categoryData = $request->except(['image']);
            
            // Update slug if name changed
            if ($request->input('name') !== $category->name) {
                $categoryData['slug'] = Str::slug($request->input('name'));
                
                // Ensure unique slug
                $originalSlug = $categoryData['slug'];
                $counter = 1;
                while (Category::where('slug', $categoryData['slug'])->where('id', '!=', $category->id)->exists()) {
                    $categoryData['slug'] = $originalSlug . '-' . $counter;
                    $counter++;
                }
            }

            $category->update($categoryData);

            // Handle image upload
            if ($request->hasFile('image')) {
                // Delete old image
                if ($category->image) {
                    $oldPath = str_replace('/storage/', '', $category->image);
                    Storage::disk('public')->delete($oldPath);
                }

                // Upload new image
                $image = $request->file('image');
                $filename = time() . '.' . $image->getClientOriginalExtension();
                $path = $image->storeAs('categories', $filename, 'public');
                $category->update(['image' => Storage::url($path)]);
            }

            $category->load('parent:id,name');

            return response()->json([
                'success' => true,
                'message' => 'Category updated successfully',
                'data' => $category
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating category',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified category
     */
    public function destroy(Category $category): JsonResponse
    {
        try {
            // Check if category has products
            if ($category->products()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete category with existing products'
                ], 422);
            }

            // Check if category has children
            if ($category->children()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete category with subcategories'
                ], 422);
            }

            // Delete category image
            if ($category->image) {
                $path = str_replace('/storage/', '', $category->image);
                Storage::disk('public')->delete($path);
            }

            $category->delete();

            return response()->json([
                'success' => true,
                'message' => 'Category deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting category',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reorder categories
     */
    public function reorder(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'categories' => 'required|array',
            'categories.*.id' => 'required|exists:categories,id',
            'categories.*.sort_order' => 'required|integer|min:0',
            'parent_id' => 'nullable|exists:categories,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            \DB::beginTransaction();

            foreach ($request->input('categories') as $categoryData) {
                Category::where('id', $categoryData['id'])
                    ->update([
                        'sort_order' => $categoryData['sort_order'],
                        'parent_id' => $request->input('parent_id')
                    ]);
            }

            \DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Categories reordered successfully'
            ]);

        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error reordering categories',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk update categories
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'category_ids' => 'required|array',
            'category_ids.*' => 'exists:categories,id',
            'action' => 'required|in:activate,deactivate,delete,move',
            'parent_id' => 'required_if:action,move|nullable|exists:categories,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $categoryIds = $request->input('category_ids');
            $action = $request->input('action');
            $updated = 0;

            switch ($action) {
                case 'activate':
                    $updated = Category::whereIn('id', $categoryIds)
                        ->update(['status' => 'active']);
                    break;
                case 'deactivate':
                    $updated = Category::whereIn('id', $categoryIds)
                        ->update(['status' => 'inactive']);
                    break;
                case 'move':
                    $parentId = $request->input('parent_id');
                    
                    // Validate depth for each category
                    if ($parentId) {
                        $parent = Category::find($parentId);
                        if ($parent && $parent->getDepth() >= 2) {
                            return response()->json([
                                'success' => false,
                                'message' => 'Target parent would exceed maximum depth'
                            ], 422);
                        }
                    }
                    
                    $updated = Category::whereIn('id', $categoryIds)
                        ->update(['parent_id' => $parentId]);
                    break;
                case 'delete':
                    // Check for categories with products or children
                    $categoriesWithProducts = Category::whereIn('id', $categoryIds)
                        ->whereHas('products')
                        ->count();
                    
                    $categoriesWithChildren = Category::whereIn('id', $categoryIds)
                        ->whereHas('children')
                        ->count();
                    
                    if ($categoriesWithProducts > 0 || $categoriesWithChildren > 0) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Some categories cannot be deleted because they have products or subcategories'
                        ], 422);
                    }
                    
                    $updated = Category::whereIn('id', $categoryIds)->delete();
                    break;
            }

            return response()->json([
                'success' => true,
                'message' => "Successfully updated {$updated} categories",
                'data' => ['updated_count' => $updated]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error performing bulk update',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get category tree for select options
     */
    public function tree(): JsonResponse
    {
        try {
            $categories = Category::whereNull('parent_id')
                ->with('children.children')
                ->orderBy('sort_order')
                ->get();

            $tree = $this->buildCategoryTree($categories);

            return response()->json([
                'success' => true,
                'data' => $tree
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading category tree',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Build category tree for select options
     */
    private function buildCategoryTree($categories, $prefix = '')
    {
        $tree = [];
        
        foreach ($categories as $category) {
            $tree[] = [
                'id' => $category->id,
                'name' => $prefix . $category->name,
                'value' => $category->id,
                'label' => $prefix . $category->name
            ];
            
            if ($category->children->isNotEmpty()) {
                $tree = array_merge($tree, $this->buildCategoryTree($category->children, $prefix . '-- '));
            }
        }
        
        return $tree;
    }
}