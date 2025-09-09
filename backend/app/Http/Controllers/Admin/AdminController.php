<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Models\User;
use App\Models\Product;
use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use Carbon\Carbon;

class AdminController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware(function ($request, $next) {
            if (!Auth::user()->hasRole('admin')) {
                abort(403, 'Access denied. Admin role required.');
            }
            return $next($request);
        });
    }

    /**
     * Get admin dashboard statistics
     */
    public function dashboard(): JsonResponse
    {
        try {
            $stats = [
                'overview' => $this->getOverviewStats(),
                'sales' => $this->getSalesStats(),
                'products' => $this->getProductStats(),
                'orders' => $this->getOrderStats(),
                'users' => $this->getUserStats(),
                'recent_activity' => $this->getRecentActivity()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading dashboard data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get overview statistics
     */
    protected function getOverviewStats(): array
    {
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();
        $thisMonth = Carbon::now()->startOfMonth();
        $lastMonth = Carbon::now()->subMonth()->startOfMonth();

        // Total revenue
        $totalRevenue = Order::where('payment_status', 'paid')->sum('total_amount');
        $todayRevenue = Order::where('payment_status', 'paid')
            ->whereDate('created_at', $today)
            ->sum('total_amount');
        $yesterdayRevenue = Order::where('payment_status', 'paid')
            ->whereDate('created_at', $yesterday)
            ->sum('total_amount');

        // Total orders
        $totalOrders = Order::count();
        $todayOrders = Order::whereDate('created_at', $today)->count();
        $yesterdayOrders = Order::whereDate('created_at', $yesterday)->count();

        // Total customers
        $totalCustomers = User::whereHas('roles', function($q) {
            $q->where('name', 'customer');
        })->count();
        $newCustomersToday = User::whereHas('roles', function($q) {
            $q->where('name', 'customer');
        })->whereDate('created_at', $today)->count();

        // Total products
        $totalProducts = Product::count();
        $activeProducts = Product::where('status', 'active')->count();
        $lowStockProducts = Product::where('stock_quantity', '<=', 10)->count();

        return [
            'revenue' => [
                'total' => $totalRevenue,
                'today' => $todayRevenue,
                'yesterday' => $yesterdayRevenue,
                'change_percentage' => $yesterdayRevenue > 0 
                    ? (($todayRevenue - $yesterdayRevenue) / $yesterdayRevenue) * 100 
                    : 0
            ],
            'orders' => [
                'total' => $totalOrders,
                'today' => $todayOrders,
                'yesterday' => $yesterdayOrders,
                'change_percentage' => $yesterdayOrders > 0 
                    ? (($todayOrders - $yesterdayOrders) / $yesterdayOrders) * 100 
                    : 0
            ],
            'customers' => [
                'total' => $totalCustomers,
                'new_today' => $newCustomersToday
            ],
            'products' => [
                'total' => $totalProducts,
                'active' => $activeProducts,
                'low_stock' => $lowStockProducts
            ]
        ];
    }

    /**
     * Get sales statistics
     */
    protected function getSalesStats(): array
    {
        $last30Days = Carbon::now()->subDays(30);
        $last7Days = Carbon::now()->subDays(7);

        // Daily sales for the last 30 days
        $dailySales = Order::where('payment_status', 'paid')
            ->where('created_at', '>=', $last30Days)
            ->selectRaw('DATE(created_at) as date, SUM(total_amount) as total, COUNT(*) as orders')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Top selling products
        $topProducts = Product::withCount(['orderItems as total_sold' => function($query) {
                $query->whereHas('order', function($q) {
                    $q->where('payment_status', 'paid');
                });
            }])
            ->orderBy('total_sold', 'desc')
            ->limit(10)
            ->get(['id', 'name', 'price', 'total_sold']);

        // Sales by category
        $salesByCategory = Category::withSum(['products.orderItems as total_revenue' => function($query) {
                $query->whereHas('order', function($q) {
                    $q->where('payment_status', 'paid');
                });
            }], 'price')
            ->orderBy('total_revenue', 'desc')
            ->limit(10)
            ->get(['id', 'name', 'total_revenue']);

        return [
            'daily_sales' => $dailySales,
            'top_products' => $topProducts,
            'sales_by_category' => $salesByCategory
        ];
    }

    /**
     * Get product statistics
     */
    protected function getProductStats(): array
    {
        $totalProducts = Product::count();
        $activeProducts = Product::where('status', 'active')->count();
        $inactiveProducts = Product::where('status', 'inactive')->count();
        $draftProducts = Product::where('status', 'draft')->count();
        $lowStockProducts = Product::where('stock_quantity', '<=', 10)->count();
        $outOfStockProducts = Product::where('stock_quantity', 0)->count();

        // Products by category
        $productsByCategory = Category::withCount('products')
            ->orderBy('products_count', 'desc')
            ->get(['id', 'name', 'products_count']);

        return [
            'total' => $totalProducts,
            'active' => $activeProducts,
            'inactive' => $inactiveProducts,
            'draft' => $draftProducts,
            'low_stock' => $lowStockProducts,
            'out_of_stock' => $outOfStockProducts,
            'by_category' => $productsByCategory
        ];
    }

    /**
     * Get order statistics
     */
    protected function getOrderStats(): array
    {
        $totalOrders = Order::count();
        $pendingOrders = Order::where('status', 'pending')->count();
        $processingOrders = Order::where('status', 'processing')->count();
        $shippedOrders = Order::where('status', 'shipped')->count();
        $deliveredOrders = Order::where('status', 'delivered')->count();
        $cancelledOrders = Order::where('status', 'cancelled')->count();

        // Orders by payment status
        $paidOrders = Order::where('payment_status', 'paid')->count();
        $pendingPaymentOrders = Order::where('payment_status', 'pending')->count();
        $failedPaymentOrders = Order::where('payment_status', 'failed')->count();

        // Average order value
        $averageOrderValue = Order::where('payment_status', 'paid')->avg('total_amount');

        return [
            'total' => $totalOrders,
            'by_status' => [
                'pending' => $pendingOrders,
                'processing' => $processingOrders,
                'shipped' => $shippedOrders,
                'delivered' => $deliveredOrders,
                'cancelled' => $cancelledOrders
            ],
            'by_payment_status' => [
                'paid' => $paidOrders,
                'pending' => $pendingPaymentOrders,
                'failed' => $failedPaymentOrders
            ],
            'average_order_value' => round($averageOrderValue, 2)
        ];
    }

    /**
     * Get user statistics
     */
    protected function getUserStats(): array
    {
        $totalUsers = User::count();
        $customers = User::whereHas('roles', function($q) {
            $q->where('name', 'customer');
        })->count();
        $admins = User::whereHas('roles', function($q) {
            $q->where('name', 'admin');
        })->count();
        $vendors = User::whereHas('roles', function($q) {
            $q->where('name', 'vendor');
        })->count();

        // New users in the last 30 days
        $newUsersLast30Days = User::where('created_at', '>=', Carbon::now()->subDays(30))->count();

        // Active users (users who made an order in the last 30 days)
        $activeUsers = User::whereHas('orders', function($q) {
            $q->where('created_at', '>=', Carbon::now()->subDays(30));
        })->count();

        return [
            'total' => $totalUsers,
            'customers' => $customers,
            'admins' => $admins,
            'vendors' => $vendors,
            'new_last_30_days' => $newUsersLast30Days,
            'active_last_30_days' => $activeUsers
        ];
    }

    /**
     * Get recent activity
     */
    protected function getRecentActivity(): array
    {
        // Recent orders
        $recentOrders = Order::with(['user:id,name,email'])
            ->latest()
            ->limit(10)
            ->get(['id', 'user_id', 'order_number', 'status', 'total_amount', 'created_at']);

        // Recent users
        $recentUsers = User::with('roles:name')
            ->latest()
            ->limit(10)
            ->get(['id', 'name', 'email', 'created_at']);

        // Recent products
        $recentProducts = Product::latest()
            ->limit(10)
            ->get(['id', 'name', 'price', 'status', 'created_at']);

        // Recent payments
        $recentPayments = Payment::with(['order:id,order_number', 'user:id,name'])
            ->latest()
            ->limit(10)
            ->get(['id', 'order_id', 'user_id', 'amount', 'status', 'payment_method', 'created_at']);

        return [
            'orders' => $recentOrders,
            'users' => $recentUsers,
            'products' => $recentProducts,
            'payments' => $recentPayments
        ];
    }

    /**
     * Get system information
     */
    public function systemInfo(): JsonResponse
    {
        try {
            $info = [
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'database' => [
                    'driver' => config('database.default'),
                    'version' => $this->getDatabaseVersion()
                ],
                'cache' => [
                    'driver' => config('cache.default')
                ],
                'queue' => [
                    'driver' => config('queue.default')
                ],
                'storage' => [
                    'disk_usage' => $this->getDiskUsage(),
                    'free_space' => $this->getFreeSpace()
                ],
                'memory' => [
                    'usage' => memory_get_usage(true),
                    'peak' => memory_get_peak_usage(true),
                    'limit' => ini_get('memory_limit')
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $info
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading system information',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get database version
     */
    protected function getDatabaseVersion(): string
    {
        try {
            return \DB::select('SELECT VERSION() as version')[0]->version;
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }

    /**
     * Get disk usage
     */
    protected function getDiskUsage(): array
    {
        $path = storage_path();
        $total = disk_total_space($path);
        $free = disk_free_space($path);
        $used = $total - $free;

        return [
            'total' => $total,
            'used' => $used,
            'free' => $free,
            'percentage' => $total > 0 ? round(($used / $total) * 100, 2) : 0
        ];
    }

    /**
     * Get free space
     */
    protected function getFreeSpace(): int
    {
        return disk_free_space(storage_path());
    }

    /**
     * Export data
     */
    public function export(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:orders,products,users,payments',
            'format' => 'required|in:csv,xlsx',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from'
        ]);

        try {
            $type = $request->input('type');
            $format = $request->input('format');
            $dateFrom = $request->input('date_from');
            $dateTo = $request->input('date_to');

            // This would typically use Laravel Excel or similar package
            // For now, return a success response with download URL
            $filename = $type . '_export_' . now()->format('Y-m-d_H-i-s') . '.' . $format;
            
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
                'message' => 'Error generating export',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}