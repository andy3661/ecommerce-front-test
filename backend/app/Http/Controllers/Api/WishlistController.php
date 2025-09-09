<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Wishlist;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class WishlistController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Get user's wishlist
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $wishlistItems = Wishlist::with(['product' => function ($query) {
                $query->with(['images', 'category', 'reviews'])
                      ->where('is_active', true);
            }])
            ->where('user_id', $user->id)
            ->latest()
            ->get()
            ->filter(function ($item) {
                return $item->product !== null;
            })
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'added_at' => $item->created_at->format('Y-m-d H:i:s'),
                    'product' => [
                        'id' => $item->product->id,
                        'name' => $item->product->name,
                        'slug' => $item->product->slug,
                        'price' => $item->product->price,
                        'sale_price' => $item->product->sale_price,
                        'formatted_price' => $item->product->formatted_price,
                        'formatted_sale_price' => $item->product->formatted_sale_price,
                        'discount_percentage' => $item->product->discount_percentage,
                        'is_on_sale' => $item->product->is_on_sale,
                        'stock_quantity' => $item->product->stock_quantity,
                        'is_in_stock' => $item->product->is_in_stock,
                        'average_rating' => $item->product->average_rating,
                        'reviews_count' => $item->product->reviews_count,
                        'image' => $item->product->featured_image,
                        'category' => $item->product->category ? [
                            'id' => $item->product->category->id,
                            'name' => $item->product->category->name,
                            'slug' => $item->product->category->slug
                        ] : null
                    ]
                ];
            })
            ->values();

            return response()->json([
                'success' => true,
                'data' => [
                    'items' => $wishlistItems,
                    'count' => $wishlistItems->count()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving wishlist',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Add product to wishlist
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'product_id' => 'required|integer|exists:products,id'
            ]);

            $user = Auth::user();
            $productId = $request->product_id;

            // Check if product exists and is active
            $product = Product::where('id', $productId)
                             ->where('is_active', true)
                             ->first();

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found or not available'
                ], 404);
            }

            // Check if already in wishlist
            $existingItem = Wishlist::where('user_id', $user->id)
                                  ->where('product_id', $productId)
                                  ->first();

            if ($existingItem) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product is already in your wishlist'
                ], 409);
            }

            // Add to wishlist
            $wishlistItem = Wishlist::create([
                'user_id' => $user->id,
                'product_id' => $productId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Product added to wishlist successfully',
                'data' => [
                    'id' => $wishlistItem->id,
                    'product_id' => $productId,
                    'added_at' => $wishlistItem->created_at->format('Y-m-d H:i:s')
                ]
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error adding product to wishlist',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Remove product from wishlist
     */
    public function destroy(Request $request, $productId): JsonResponse
    {
        try {
            $user = Auth::user();

            $wishlistItem = Wishlist::where('user_id', $user->id)
                                  ->where('product_id', $productId)
                                  ->first();

            if (!$wishlistItem) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found in wishlist'
                ], 404);
            }

            $wishlistItem->delete();

            return response()->json([
                'success' => true,
                'message' => 'Product removed from wishlist successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error removing product from wishlist',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Toggle product in wishlist
     */
    public function toggle(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'product_id' => 'required|integer|exists:products,id'
            ]);

            $user = Auth::user();
            $productId = $request->product_id;

            // Check if product exists and is active
            $product = Product::where('id', $productId)
                             ->where('is_active', true)
                             ->first();

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found or not available'
                ], 404);
            }

            $wishlistItem = Wishlist::where('user_id', $user->id)
                                  ->where('product_id', $productId)
                                  ->first();

            if ($wishlistItem) {
                // Remove from wishlist
                $wishlistItem->delete();
                $action = 'removed';
                $inWishlist = false;
            } else {
                // Add to wishlist
                Wishlist::create([
                    'user_id' => $user->id,
                    'product_id' => $productId
                ]);
                $action = 'added';
                $inWishlist = true;
            }

            return response()->json([
                'success' => true,
                'message' => "Product {$action} " . ($action === 'added' ? 'to' : 'from') . ' wishlist successfully',
                'data' => [
                    'product_id' => $productId,
                    'in_wishlist' => $inWishlist,
                    'action' => $action
                ]
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error toggling product in wishlist',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Check if product is in wishlist
     */
    public function check(Request $request, $productId): JsonResponse
    {
        try {
            $user = Auth::user();

            $inWishlist = Wishlist::where('user_id', $user->id)
                                ->where('product_id', $productId)
                                ->exists();

            return response()->json([
                'success' => true,
                'data' => [
                    'product_id' => (int) $productId,
                    'in_wishlist' => $inWishlist
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error checking wishlist status',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Clear entire wishlist
     */
    public function clear(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            $deletedCount = Wishlist::where('user_id', $user->id)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Wishlist cleared successfully',
                'data' => [
                    'deleted_count' => $deletedCount
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error clearing wishlist',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get wishlist count
     */
    public function count(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            $count = Wishlist::where('user_id', $user->id)
                           ->whereHas('product', function ($query) {
                               $query->where('is_active', true);
                           })
                           ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'count' => $count
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error getting wishlist count',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}