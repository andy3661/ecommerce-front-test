<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserAddress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class UserAddressController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }
    
    /**
     * Get user's addresses
     */
    public function index()
    {
        $addresses = UserAddress::where('user_id', Auth::id())
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
        
        return response()->json([
            'success' => true,
            'data' => $addresses
        ]);
    }
    
    /**
     * Get specific address
     */
    public function show($id)
    {
        $address = UserAddress::where('user_id', Auth::id())
            ->findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => $address
        ]);
    }
    
    /**
     * Create new address
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:home,work,other',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'company' => 'nullable|string|max:255',
            'address_line_1' => 'required|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'required|string|max:255',
            'state' => 'required|string|max:255',
            'postal_code' => 'required|string|max:20',
            'country' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'is_default' => 'boolean'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $addressData = $request->all();
        $addressData['user_id'] = Auth::id();
        
        // If this is set as default, remove default from other addresses
        if ($request->get('is_default', false)) {
            UserAddress::where('user_id', Auth::id())
                ->update(['is_default' => false]);
        }
        
        // If this is the first address, make it default
        $existingAddressesCount = UserAddress::where('user_id', Auth::id())->count();
        if ($existingAddressesCount === 0) {
            $addressData['is_default'] = true;
        }
        
        $address = UserAddress::create($addressData);
        
        return response()->json([
            'success' => true,
            'message' => 'Address created successfully',
            'data' => $address
        ], 201);
    }
    
    /**
     * Update address
     */
    public function update(Request $request, $id)
    {
        $address = UserAddress::where('user_id', Auth::id())
            ->findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:home,work,other',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'company' => 'nullable|string|max:255',
            'address_line_1' => 'required|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'required|string|max:255',
            'state' => 'required|string|max:255',
            'postal_code' => 'required|string|max:20',
            'country' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'is_default' => 'boolean'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // If this is set as default, remove default from other addresses
        if ($request->get('is_default', false)) {
            UserAddress::where('user_id', Auth::id())
                ->where('id', '!=', $id)
                ->update(['is_default' => false]);
        }
        
        $address->update($request->all());
        
        return response()->json([
            'success' => true,
            'message' => 'Address updated successfully',
            'data' => $address
        ]);
    }
    
    /**
     * Delete address
     */
    public function destroy($id)
    {
        $address = UserAddress::where('user_id', Auth::id())
            ->findOrFail($id);
        
        $wasDefault = $address->is_default;
        $address->delete();
        
        // If deleted address was default, make another address default
        if ($wasDefault) {
            $newDefaultAddress = UserAddress::where('user_id', Auth::id())
                ->orderBy('created_at', 'desc')
                ->first();
            
            if ($newDefaultAddress) {
                $newDefaultAddress->update(['is_default' => true]);
            }
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Address deleted successfully'
        ]);
    }
    
    /**
     * Set address as default
     */
    public function setDefault($id)
    {
        $address = UserAddress::where('user_id', Auth::id())
            ->findOrFail($id);
        
        // Remove default from all other addresses
        UserAddress::where('user_id', Auth::id())
            ->where('id', '!=', $id)
            ->update(['is_default' => false]);
        
        // Set this address as default
        $address->update(['is_default' => true]);
        
        return response()->json([
            'success' => true,
            'message' => 'Default address updated successfully',
            'data' => $address
        ]);
    }
    
    /**
     * Get default address
     */
    public function getDefault()
    {
        $address = UserAddress::where('user_id', Auth::id())
            ->where('is_default', true)
            ->first();
        
        if (!$address) {
            return response()->json([
                'success' => false,
                'message' => 'No default address found'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => $address
        ]);
    }
}