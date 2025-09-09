<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CartController extends Controller
{
    /**
     * Get cart items for authenticated user or guest session
     */
    public function index(Request $request)
    {
        $cartItems = $this->getCartItems($request);
        
        $cartItems->load(['product:id,name,slug,price,compare_price,images,inventory_quantity']);
        
        $total = $cartItems->sum(function ($item) {
            return $item->quantity * $item->unit_price;
        });
        
        $itemsCount = $cartItems->sum('quantity');
        
        return response()->json([
            'success' => true,
            'data' => [
                'items' => $cartItems,
                'summary' => [
                    'items_count' => $itemsCount,
                    'subtotal' => $total,
                    'total' => $total,
                    'formatted_subtotal' => '$' . number_format($total, 2),
                    'formatted_total' => '$' . number_format($total, 2)
                ]
            ]
        ]);
    }
    
    /**
     * Add item to cart
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1|max:99',
            'variant_options' => 'nullable|array'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $product = Product::findOrFail($request->product_id);
        
        // Check if product is active and in stock
        if (!$product->is_active || $product->inventory_quantity < $request->quantity) {
            return response()->json([
                'success' => false,
                'message' => 'Product is not available or insufficient stock'
            ], 400);
        }
        
        $cartData = [
            'product_id' => $product->id,
            'quantity' => $request->quantity,
            'unit_price' => $product->price,
            'variant_options' => $request->variant_options ?? []
        ];
        
        if (Auth::check()) {
            $cartData['user_id'] = Auth::id();
        } else {
            $sessionId = $request->session()->getId();
            if (!$sessionId) {
                $sessionId = Str::random(40);
                $request->session()->setId($sessionId);
            }
            $cartData['session_id'] = $sessionId;
        }
        
        // Check if item already exists in cart
        $existingItem = Cart::where('product_id', $product->id)
            ->when(Auth::check(), function ($query) {
                return $query->where('user_id', Auth::id());
            }, function ($query) use ($request) {
                return $query->where('session_id', $request->session()->getId());
            })
            ->where('variant_options', json_encode($request->variant_options ?? []))
            ->first();
        
        if ($existingItem) {
            $newQuantity = $existingItem->quantity + $request->quantity;
            
            if ($newQuantity > $product->inventory_quantity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient stock for requested quantity'
                ], 400);
            }
            
            $existingItem->update(['quantity' => $newQuantity]);
            $cartItem = $existingItem;
        } else {
            $cartItem = Cart::create($cartData);
        }
        
        $cartItem->load('product:id,name,slug,price,compare_price,images');
        
        return response()->json([
            'success' => true,
            'message' => 'Item added to cart successfully',
            'data' => $cartItem
        ], 201);
    }
    
    /**
     * Update cart item quantity
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'quantity' => 'required|integer|min:1|max:99'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $cartItem = $this->findCartItem($request, $id);
        
        if (!$cartItem) {
            return response()->json([
                'success' => false,
                'message' => 'Cart item not found'
            ], 404);
        }
        
        // Check stock availability
        if ($cartItem->product->inventory_quantity < $request->quantity) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient stock for requested quantity'
            ], 400);
        }
        
        $cartItem->update(['quantity' => $request->quantity]);
        $cartItem->load('product:id,name,slug,price,compare_price,images');
        
        return response()->json([
            'success' => true,
            'message' => 'Cart item updated successfully',
            'data' => $cartItem
        ]);
    }
    
    /**
     * Remove item from cart
     */
    public function destroy(Request $request, $id)
    {
        $cartItem = $this->findCartItem($request, $id);
        
        if (!$cartItem) {
            return response()->json([
                'success' => false,
                'message' => 'Cart item not found'
            ], 404);
        }
        
        $cartItem->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Item removed from cart successfully'
        ]);
    }
    
    /**
     * Clear all cart items
     */
    public function clear(Request $request)
    {
        $cartItems = $this->getCartItems($request);
        
        foreach ($cartItems as $item) {
            $item->delete();
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Cart cleared successfully'
        ]);
    }
    
    /**
     * Get cart items count
     */
    public function count(Request $request)
    {
        $cartItems = $this->getCartItems($request);
        $count = $cartItems->sum('quantity');
        
        return response()->json([
            'success' => true,
            'data' => [
                'count' => $count
            ]
        ]);
    }
    
    /**
     * Sync guest cart with user cart after login
     */
    public function sync(Request $request)
    {
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'User must be authenticated'
            ], 401);
        }
        
        $sessionId = $request->session()->getId();
        if (!$sessionId) {
            return response()->json([
                'success' => true,
                'message' => 'No guest cart to sync'
            ]);
        }
        
        $guestCartItems = Cart::where('session_id', $sessionId)->get();
        
        foreach ($guestCartItems as $guestItem) {
            $existingUserItem = Cart::where('user_id', Auth::id())
                ->where('product_id', $guestItem->product_id)
                ->where('variant_options', $guestItem->variant_options)
                ->first();
            
            if ($existingUserItem) {
                // Merge quantities
                $newQuantity = $existingUserItem->quantity + $guestItem->quantity;
                $maxQuantity = $guestItem->product->inventory_quantity;
                
                $existingUserItem->update([
                    'quantity' => min($newQuantity, $maxQuantity)
                ]);
            } else {
                // Transfer guest item to user
                $guestItem->update([
                    'user_id' => Auth::id(),
                    'session_id' => null
                ]);
            }
        }
        
        // Clean up any remaining guest items
        Cart::where('session_id', $sessionId)->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Cart synced successfully'
        ]);
    }
    
    /**
     * Get cart items for current user or session
     */
    private function getCartItems(Request $request)
    {
        if (Auth::check()) {
            return Cart::where('user_id', Auth::id())->get();
        } else {
            $sessionId = $request->session()->getId();
            return $sessionId ? Cart::where('session_id', $sessionId)->get() : collect();
        }
    }
    
    /**
     * Find cart item by ID for current user or session
     */
    private function findCartItem(Request $request, $id)
    {
        $query = Cart::where('id', $id);
        
        if (Auth::check()) {
            $query->where('user_id', Auth::id());
        } else {
            $sessionId = $request->session()->getId();
            if ($sessionId) {
                $query->where('session_id', $sessionId);
            } else {
                return null;
            }
        }
        
        return $query->first();
    }
}