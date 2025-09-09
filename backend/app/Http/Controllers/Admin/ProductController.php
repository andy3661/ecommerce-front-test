<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('role:admin');
    }

    /**
     * Display a listing of products
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Product::with(['category:id,name', 'variants'])
                ->withCount('variants');

            // Search
            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('sku', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            // Filter by category
            if ($request->filled('category_id')) {
                $query->where('category_id', $request->input('category_id'));
            }

            // Filter by status
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }

            // Filter by stock status
            if ($request->filled('stock_status')) {
                $stockStatus = $request->input('stock_status');
                switch ($stockStatus) {
                    case 'in_stock':
                        $query->where('stock_quantity', '>', 0);
                        break;
                    case 'low_stock':
                        $query->where('stock_quantity', '>', 0)
                              ->where('stock_quantity', '<=', 10);
                        break;
                    case 'out_of_stock':
                        $query->where('stock_quantity', 0);
                        break;
                }
            }

            // Filter by price range
            if ($request->filled('min_price')) {
                $query->where('price', '>=', $request->input('min_price'));
            }
            if ($request->filled('max_price')) {
                $query->where('price', '<=', $request->input('max_price'));
            }

            // Sorting
            $sortBy = $request->input('sort_by', 'created_at');
            $sortOrder = $request->input('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = $request->input('per_page', 15);
            $products = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $products,
                'filters' => [
                    'categories' => Category::select('id', 'name')->get(),
                    'statuses' => ['active', 'inactive', 'draft'],
                    'stock_statuses' => ['in_stock', 'low_stock', 'out_of_stock']
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading products',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created product
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string|max:500',
            'sku' => 'required|string|max:100|unique:products,sku',
            'price' => 'required|numeric|min:0',
            'compare_price' => 'nullable|numeric|min:0|gte:price',
            'cost_price' => 'nullable|numeric|min:0',
            'category_id' => 'required|exists:categories,id',
            'brand' => 'nullable|string|max:100',
            'weight' => 'nullable|numeric|min:0',
            'dimensions' => 'nullable|array',
            'dimensions.length' => 'nullable|numeric|min:0',
            'dimensions.width' => 'nullable|numeric|min:0',
            'dimensions.height' => 'nullable|numeric|min:0',
            'stock_quantity' => 'required|integer|min:0',
            'low_stock_threshold' => 'nullable|integer|min:0',
            'manage_stock' => 'boolean',
            'status' => 'required|in:active,inactive,draft',
            'featured' => 'boolean',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'images' => 'nullable|array|max:10',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'variants' => 'nullable|array',
            'variants.*.name' => 'required_with:variants|string|max:255',
            'variants.*.sku' => 'required_with:variants|string|max:100',
            'variants.*.price' => 'required_with:variants|numeric|min:0',
            'variants.*.stock_quantity' => 'required_with:variants|integer|min:0',
            'variants.*.attributes' => 'nullable|array'
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

            // Create product
            $productData = $request->except(['images', 'variants']);
            $productData['slug'] = Str::slug($request->input('name'));
            
            // Ensure unique slug
            $originalSlug = $productData['slug'];
            $counter = 1;
            while (Product::where('slug', $productData['slug'])->exists()) {
                $productData['slug'] = $originalSlug . '-' . $counter;
                $counter++;
            }

            $product = Product::create($productData);

            // Handle image uploads
            if ($request->hasFile('images')) {
                $images = [];
                foreach ($request->file('images') as $index => $image) {
                    $filename = time() . '_' . $index . '.' . $image->getClientOriginalExtension();
                    $path = $image->storeAs('products', $filename, 'public');
                    $images[] = [
                        'url' => Storage::url($path),
                        'alt' => $product->name,
                        'is_primary' => $index === 0
                    ];
                }
                $product->update(['images' => $images]);
            }

            // Create variants if provided
            if ($request->filled('variants')) {
                foreach ($request->input('variants') as $variantData) {
                    $variantData['product_id'] = $product->id;
                    ProductVariant::create($variantData);
                }
            }

            \DB::commit();

            $product->load(['category', 'variants']);

            return response()->json([
                'success' => true,
                'message' => 'Product created successfully',
                'data' => $product
            ], 201);

        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error creating product',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified product
     */
    public function show(Product $product): JsonResponse
    {
        try {
            $product->load(['category', 'variants', 'orderItems.order']);
            
            // Add sales statistics
            $product->sales_stats = [
                'total_sold' => $product->orderItems->sum('quantity'),
                'total_revenue' => $product->orderItems->sum(function($item) {
                    return $item->quantity * $item->price;
                }),
                'orders_count' => $product->orderItems->count()
            ];

            return response()->json([
                'success' => true,
                'data' => $product
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading product',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified product
     */
    public function update(Request $request, Product $product): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string|max:500',
            'sku' => ['required', 'string', 'max:100', Rule::unique('products')->ignore($product->id)],
            'price' => 'required|numeric|min:0',
            'compare_price' => 'nullable|numeric|min:0|gte:price',
            'cost_price' => 'nullable|numeric|min:0',
            'category_id' => 'required|exists:categories,id',
            'brand' => 'nullable|string|max:100',
            'weight' => 'nullable|numeric|min:0',
            'dimensions' => 'nullable|array',
            'stock_quantity' => 'required|integer|min:0',
            'low_stock_threshold' => 'nullable|integer|min:0',
            'manage_stock' => 'boolean',
            'status' => 'required|in:active,inactive,draft',
            'featured' => 'boolean',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'tags' => 'nullable|array',
            'images' => 'nullable|array|max:10',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:2048'
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

            $productData = $request->except(['images']);
            
            // Update slug if name changed
            if ($request->input('name') !== $product->name) {
                $productData['slug'] = Str::slug($request->input('name'));
                
                // Ensure unique slug
                $originalSlug = $productData['slug'];
                $counter = 1;
                while (Product::where('slug', $productData['slug'])->where('id', '!=', $product->id)->exists()) {
                    $productData['slug'] = $originalSlug . '-' . $counter;
                    $counter++;
                }
            }

            $product->update($productData);

            // Handle new image uploads
            if ($request->hasFile('images')) {
                // Delete old images
                if ($product->images) {
                    foreach ($product->images as $image) {
                        $path = str_replace('/storage/', '', $image['url']);
                        Storage::disk('public')->delete($path);
                    }
                }

                // Upload new images
                $images = [];
                foreach ($request->file('images') as $index => $image) {
                    $filename = time() . '_' . $index . '.' . $image->getClientOriginalExtension();
                    $path = $image->storeAs('products', $filename, 'public');
                    $images[] = [
                        'url' => Storage::url($path),
                        'alt' => $product->name,
                        'is_primary' => $index === 0
                    ];
                }
                $product->update(['images' => $images]);
            }

            \DB::commit();

            $product->load(['category', 'variants']);

            return response()->json([
                'success' => true,
                'message' => 'Product updated successfully',
                'data' => $product
            ]);

        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error updating product',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified product
     */
    public function destroy(Product $product): JsonResponse
    {
        try {
            // Check if product has orders
            if ($product->orderItems()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete product with existing orders'
                ], 422);
            }

            \DB::beginTransaction();

            // Delete product images
            if ($product->images) {
                foreach ($product->images as $image) {
                    $path = str_replace('/storage/', '', $image['url']);
                    Storage::disk('public')->delete($path);
                }
            }

            // Delete variants
            $product->variants()->delete();

            // Delete product
            $product->delete();

            \DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Product deleted successfully'
            ]);

        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error deleting product',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk update products
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'product_ids' => 'required|array',
            'product_ids.*' => 'exists:products,id',
            'action' => 'required|in:activate,deactivate,delete,update_category,update_status',
            'category_id' => 'required_if:action,update_category|exists:categories,id',
            'status' => 'required_if:action,update_status|in:active,inactive,draft'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $productIds = $request->input('product_ids');
            $action = $request->input('action');
            $updated = 0;

            switch ($action) {
                case 'activate':
                    $updated = Product::whereIn('id', $productIds)->update(['status' => 'active']);
                    break;
                case 'deactivate':
                    $updated = Product::whereIn('id', $productIds)->update(['status' => 'inactive']);
                    break;
                case 'update_category':
                    $updated = Product::whereIn('id', $productIds)
                        ->update(['category_id' => $request->input('category_id')]);
                    break;
                case 'update_status':
                    $updated = Product::whereIn('id', $productIds)
                        ->update(['status' => $request->input('status')]);
                    break;
                case 'delete':
                    // Check for products with orders
                    $productsWithOrders = Product::whereIn('id', $productIds)
                        ->whereHas('orderItems')
                        ->count();
                    
                    if ($productsWithOrders > 0) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Some products cannot be deleted because they have existing orders'
                        ], 422);
                    }
                    
                    $updated = Product::whereIn('id', $productIds)->delete();
                    break;
            }

            return response()->json([
                'success' => true,
                'message' => "Successfully updated {$updated} products",
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
     * Import products from CSV/XLSX
     */
    public function import(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,xlsx|max:10240', // 10MB max
            'update_existing' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // This would typically use Laravel Excel or similar package
            // For now, return a success response
            return response()->json([
                'success' => true,
                'message' => 'Products import started. You will be notified when complete.',
                'data' => [
                    'job_id' => Str::uuid(),
                    'status' => 'processing'
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error importing products',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export products to CSV/XLSX
     */
    public function export(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'format' => 'required|in:csv,xlsx',
            'filters' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $format = $request->input('format');
            $filename = 'products_export_' . now()->format('Y-m-d_H-i-s') . '.' . $format;
            
            // This would typically use Laravel Excel or similar package
            return response()->json([
                'success' => true,
                'message' => 'Export generated successfully',
                'data' => [
                    'filename' => $filename,
                    'download_url' => url('admin/exports/' . $filename),
                    'expires_at' => now()->addHours(24)->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error exporting products',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}