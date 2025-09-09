<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\UserAddressController;
use App\Http\Controllers\Api\WishlistController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ShippingController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Api\SearchController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public authentication routes
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
});

// Protected authentication routes
Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::put('profile', [AuthController::class, 'updateProfile']);
        Route::put('change-password', [AuthController::class, 'changePassword']);
    });
    
    // Test route to verify authentication
    Route::get('user', function (Request $request) {
        return $request->user();
    });
    
    // Cart routes
    Route::prefix('cart')->group(function () {
        Route::get('/', [CartController::class, 'index']);
        Route::post('/', [CartController::class, 'store']);
        Route::put('/{id}', [CartController::class, 'update']);
        Route::delete('/{id}', [CartController::class, 'destroy']);
        Route::delete('/', [CartController::class, 'clear']);
        Route::get('/count', [CartController::class, 'count']);
        Route::post('/sync', [CartController::class, 'sync']);
    });
    
    // Order routes
    Route::prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'index']);
        Route::post('/', [OrderController::class, 'store']);
        Route::get('/{order}', [OrderController::class, 'show']);
        Route::put('/{order}/cancel', [OrderController::class, 'cancel']);
        Route::get('/{order}/tracking', [OrderController::class, 'tracking']);
    });
    
    // User addresses routes
    Route::prefix('addresses')->group(function () {
        Route::get('/', [UserAddressController::class, 'index']);
        Route::post('/', [UserAddressController::class, 'store']);
        Route::get('/default', [UserAddressController::class, 'getDefault']);
        Route::get('/{address}', [UserAddressController::class, 'show']);
        Route::put('/{address}', [UserAddressController::class, 'update']);
        Route::delete('/{address}', [UserAddressController::class, 'destroy']);
        Route::put('/{address}/set-default', [UserAddressController::class, 'setDefault']);
    });
    
    // Wishlist routes
    Route::prefix('wishlist')->group(function () {
        Route::get('/', [WishlistController::class, 'index']);
        Route::post('/', [WishlistController::class, 'store']);
        Route::delete('/{productId}', [WishlistController::class, 'destroy']);
        Route::post('/toggle', [WishlistController::class, 'toggle']);
        Route::get('/check/{productId}', [WishlistController::class, 'check']);
        Route::delete('/', [WishlistController::class, 'clear']);
        Route::get('/count', [WishlistController::class, 'count']);
    });
    
    // Payment routes
    Route::prefix('payments')->group(function () {
        Route::get('/methods', [PaymentController::class, 'methods']);
        Route::post('/intent', [PaymentController::class, 'createIntent']);
        Route::post('/{payment}/confirm', [PaymentController::class, 'confirm']);
        Route::get('/{payment}/status', [PaymentController::class, 'status']);
    });
    
    // Shipping routes (protected)
    Route::prefix('shipping')->group(function () {
        Route::post('/calculate', [ShippingController::class, 'calculateShipping']);
        Route::post('/label', [ShippingController::class, 'createLabel']);
        Route::get('/track/{trackingNumber}', [ShippingController::class, 'trackShipment']);
        Route::get('/zones', [ShippingController::class, 'getZones']);
        Route::get('/coverage', [ShippingController::class, 'getCoverage']);
    });
});

// Public payment webhook routes (no authentication required)
Route::post('payments/webhook/{provider}', [PaymentController::class, 'webhook']);
Route::get('payments/methods', [PaymentController::class, 'methods']); // Public access to payment methods

// Shipping public routes
Route::get('shipping/methods', [ShippingController::class, 'getMethods']);
Route::post('shipping/webhook/{carrier}', [ShippingController::class, 'handleWebhook']);

// Admin routes
Route::prefix('admin')->middleware(['auth:sanctum', 'admin'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [AdminController::class, 'dashboard']);
    Route::get('/system-info', [AdminController::class, 'systemInfo'])->middleware('permission:admin.system');
    Route::post('/export', [AdminController::class, 'export']);
    
    // Products management
    Route::middleware('permission:products.view')->group(function () {
        Route::get('/products', [AdminProductController::class, 'index']);
        Route::get('/products/{product}', [AdminProductController::class, 'show']);
    });
    Route::post('/products', [AdminProductController::class, 'store'])->middleware('permission:products.create');
    Route::put('/products/{product}', [AdminProductController::class, 'update'])->middleware('permission:products.edit');
    Route::delete('/products/{product}', [AdminProductController::class, 'destroy'])->middleware('permission:products.delete');
    Route::post('/products/bulk-update', [AdminProductController::class, 'bulkUpdate'])->middleware('permission:products.edit');
    Route::post('/products/import', [AdminProductController::class, 'import'])->middleware('permission:products.import');
    Route::post('/products/export', [AdminProductController::class, 'export'])->middleware('permission:products.export');
    
    // Categories management
    Route::middleware('permission:categories.view')->group(function () {
        Route::get('/categories', [AdminCategoryController::class, 'index']);
        Route::get('/categories/{category}', [AdminCategoryController::class, 'show']);
        Route::get('/categories/tree', [AdminCategoryController::class, 'tree']);
    });
    Route::post('/categories', [AdminCategoryController::class, 'store'])->middleware('permission:categories.create');
    Route::put('/categories/{category}', [AdminCategoryController::class, 'update'])->middleware('permission:categories.edit');
    Route::delete('/categories/{category}', [AdminCategoryController::class, 'destroy'])->middleware('permission:categories.delete');
    Route::post('/categories/reorder', [AdminCategoryController::class, 'reorder'])->middleware('permission:categories.edit');
    Route::post('/categories/bulk-update', [AdminCategoryController::class, 'bulkUpdate'])->middleware('permission:categories.edit');
    
    // Orders management
    Route::middleware('permission:orders.view')->group(function () {
        Route::get('/orders', [AdminOrderController::class, 'index']);
        Route::get('/orders/{order}', [AdminOrderController::class, 'show']);
        Route::get('/orders/analytics', [AdminOrderController::class, 'analytics']);
    });
    Route::put('/orders/{order}/status', [AdminOrderController::class, 'updateStatus'])->middleware('permission:orders.status');
    Route::put('/orders/{order}/items', [AdminOrderController::class, 'updateItems'])->middleware('permission:orders.edit');
    Route::post('/orders/{order}/notes', [AdminOrderController::class, 'addNotes'])->middleware('permission:orders.edit');
    Route::post('/orders/bulk-update', [AdminOrderController::class, 'bulkUpdate'])->middleware('permission:orders.edit');
    
    // Users management
    Route::middleware('permission:users.view')->group(function () {
        Route::get('/users', [AdminUserController::class, 'index']);
        Route::get('/users/{user}', [AdminUserController::class, 'show']);
        Route::get('/users/analytics', [AdminUserController::class, 'analytics']);
    });
    Route::post('/users', [AdminUserController::class, 'store'])->middleware('permission:users.create');
    Route::put('/users/{user}', [AdminUserController::class, 'update'])->middleware('permission:users.edit');
    Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->middleware('permission:users.delete');
    Route::post('/users/bulk-update', [AdminUserController::class, 'bulkUpdate'])->middleware('permission:users.edit');
    Route::get('/users/export', [AdminUserController::class, 'export'])->middleware('permission:users.export');
    
    // Roles management
    Route::middleware('permission:admin.roles')->group(function () {
        Route::get('/roles', [RoleController::class, 'index']);
        Route::post('/roles', [RoleController::class, 'store']);
        Route::get('/roles/{role}', [RoleController::class, 'show']);
        Route::put('/roles/{role}', [RoleController::class, 'update']);
        Route::delete('/roles/{role}', [RoleController::class, 'destroy']);
        Route::get('/roles/permissions/all', [RoleController::class, 'permissions']);
        Route::post('/roles/{role}/users', [RoleController::class, 'assignUsers']);
        Route::delete('/roles/{role}/users', [RoleController::class, 'removeUsers']);
    });
    
    // Permissions management
    Route::middleware('permission:admin.permissions')->group(function () {
        Route::get('/permissions', [PermissionController::class, 'index']);
        Route::post('/permissions', [PermissionController::class, 'store']);
        Route::get('/permissions/{permission}', [PermissionController::class, 'show']);
        Route::put('/permissions/{permission}', [PermissionController::class, 'update']);
        Route::delete('/permissions/{permission}', [PermissionController::class, 'destroy']);
        Route::get('/permissions/groups', [PermissionController::class, 'groups']);
        Route::post('/permissions/bulk-update', [PermissionController::class, 'bulkUpdate']);
    });
    
    // Reports
    Route::middleware('permission:reports.view')->group(function () {
        Route::get('/reports/sales', [ReportController::class, 'sales']);
        Route::get('/reports/products', [ReportController::class, 'products']);
        Route::get('/reports/customers', [ReportController::class, 'customers']);
        Route::post('/reports/export', [ReportController::class, 'export'])->middleware('permission:reports.export');
    });
});

// Public product and category routes
Route::prefix('products')->group(function () {
    Route::get('/', [ProductController::class, 'index']);
    Route::get('/featured', [ProductController::class, 'featured']);
    Route::get('/search', [ProductController::class, 'search']);
    Route::get('/filters', [ProductController::class, 'filters']);
    Route::get('/{identifier}', [ProductController::class, 'show']);
});

Route::prefix('categories')->group(function () {
    Route::get('/', [CategoryController::class, 'index']);
    Route::get('/tree', [CategoryController::class, 'tree']);
    Route::get('/featured', [CategoryController::class, 'featured']);
    Route::get('/search', [CategoryController::class, 'search']);
    Route::get('/with-product-counts', [CategoryController::class, 'withProductCounts']);
    Route::get('/{identifier}', [CategoryController::class, 'show']);
    Route::get('/{identifier}/breadcrumb', [CategoryController::class, 'breadcrumb']);
});

// Search routes
Route::prefix('search')->group(function () {
    Route::get('products', [SearchController::class, 'searchProducts']);
    Route::get('suggestions', [SearchController::class, 'suggestions']);
    Route::get('popular', [SearchController::class, 'popularSearches']);
    Route::get('facets', [SearchController::class, 'facets']);
    Route::post('log', [SearchController::class, 'logSearch']);
    
    // Admin only routes
    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::get('analytics', [SearchController::class, 'analytics']);
        Route::post('reindex', [SearchController::class, 'reindex']);
    });
});

// Health check route
Route::get('health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now(),
        'version' => '1.0.0'
    ]);
});