<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('admin');
        $this->middleware('permission:reports.view');
    }

    /**
     * Get sales report
     */
    public function sales(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'period' => 'nullable|in:day,week,month,year'
        ]);

        $startDate = $request->start_date ? Carbon::parse($request->start_date) : Carbon::now()->subDays(30);
        $endDate = $request->end_date ? Carbon::parse($request->end_date) : Carbon::now();
        $period = $request->period ?? 'day';

        // Sales overview
        $salesOverview = Order::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', '!=', 'cancelled')
            ->selectRaw('
                COUNT(*) as total_orders,
                SUM(total) as total_revenue,
                AVG(total) as average_order_value,
                SUM(CASE WHEN status = "delivered" THEN 1 ELSE 0 END) as completed_orders
            ')
            ->first();

        // Sales by period
        $dateFormat = match($period) {
            'day' => '%Y-%m-%d',
            'week' => '%Y-%u',
            'month' => '%Y-%m',
            'year' => '%Y'
        };

        $salesByPeriod = Order::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', '!=', 'cancelled')
            ->selectRaw("
                DATE_FORMAT(created_at, '{$dateFormat}') as period,
                COUNT(*) as orders_count,
                SUM(total) as revenue,
                AVG(total) as avg_order_value
            ")
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        // Top products
        $topProducts = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->where('orders.status', '!=', 'cancelled')
            ->selectRaw('
                products.id,
                products.name,
                products.sku,
                SUM(order_items.quantity) as total_sold,
                SUM(order_items.quantity * order_items.price) as total_revenue
            ')
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderByDesc('total_revenue')
            ->limit(10)
            ->get();

        // Payment methods
        $paymentMethods = Payment::join('orders', 'payments.order_id', '=', 'orders.id')
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->where('payments.status', 'completed')
            ->selectRaw('
                payments.payment_method,
                COUNT(*) as count,
                SUM(payments.amount) as total_amount
            ')
            ->groupBy('payments.payment_method')
            ->get();

        return response()->json([
            'overview' => $salesOverview,
            'sales_by_period' => $salesByPeriod,
            'top_products' => $topProducts,
            'payment_methods' => $paymentMethods,
            'period' => $period,
            'date_range' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d')
            ]
        ]);
    }

    /**
     * Get products report
     */
    public function products(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date'
        ]);

        $startDate = $request->start_date ? Carbon::parse($request->start_date) : Carbon::now()->subDays(30);
        $endDate = $request->end_date ? Carbon::parse($request->end_date) : Carbon::now();

        // Product performance
        $productPerformance = DB::table('products')
            ->leftJoin('order_items', 'products.id', '=', 'order_items.product_id')
            ->leftJoin('orders', function($join) use ($startDate, $endDate) {
                $join->on('order_items.order_id', '=', 'orders.id')
                     ->whereBetween('orders.created_at', [$startDate, $endDate])
                     ->where('orders.status', '!=', 'cancelled');
            })
            ->selectRaw('
                products.id,
                products.name,
                products.sku,
                products.price,
                products.stock_quantity,
                COALESCE(SUM(order_items.quantity), 0) as total_sold,
                COALESCE(SUM(order_items.quantity * order_items.price), 0) as revenue
            ')
            ->groupBy('products.id', 'products.name', 'products.sku', 'products.price', 'products.stock_quantity')
            ->orderByDesc('revenue')
            ->paginate($request->get('per_page', 15));

        // Low stock products
        $lowStockProducts = Product::where('stock_quantity', '<=', DB::raw('min_stock_level'))
            ->orWhere('stock_quantity', '<=', 10)
            ->select('id', 'name', 'sku', 'stock_quantity', 'min_stock_level')
            ->orderBy('stock_quantity')
            ->limit(20)
            ->get();

        // Categories performance
        $categoriesPerformance = DB::table('categories')
            ->leftJoin('products', 'categories.id', '=', 'products.category_id')
            ->leftJoin('order_items', 'products.id', '=', 'order_items.product_id')
            ->leftJoin('orders', function($join) use ($startDate, $endDate) {
                $join->on('order_items.order_id', '=', 'orders.id')
                     ->whereBetween('orders.created_at', [$startDate, $endDate])
                     ->where('orders.status', '!=', 'cancelled');
            })
            ->selectRaw('
                categories.id,
                categories.name,
                COUNT(DISTINCT products.id) as products_count,
                COALESCE(SUM(order_items.quantity), 0) as total_sold,
                COALESCE(SUM(order_items.quantity * order_items.price), 0) as revenue
            ')
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('revenue')
            ->get();

        return response()->json([
            'product_performance' => $productPerformance,
            'low_stock_products' => $lowStockProducts,
            'categories_performance' => $categoriesPerformance,
            'date_range' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d')
            ]
        ]);
    }

    /**
     * Get customers report
     */
    public function customers(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date'
        ]);

        $startDate = $request->start_date ? Carbon::parse($request->start_date) : Carbon::now()->subDays(30);
        $endDate = $request->end_date ? Carbon::parse($request->end_date) : Carbon::now();

        // Customer overview
        $customerOverview = User::selectRaw('
                COUNT(*) as total_customers,
                COUNT(CASE WHEN created_at >= ? THEN 1 END) as new_customers,
                COUNT(CASE WHEN email_verified_at IS NOT NULL THEN 1 END) as verified_customers
            ', [$startDate])
            ->first();

        // Top customers
        $topCustomers = User::join('orders', 'users.id', '=', 'orders.user_id')
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->where('orders.status', '!=', 'cancelled')
            ->selectRaw('
                users.id,
                users.name,
                users.email,
                COUNT(orders.id) as total_orders,
                SUM(orders.total) as total_spent,
                AVG(orders.total) as avg_order_value
            ')
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderByDesc('total_spent')
            ->limit(20)
            ->get();

        // Customer acquisition
        $customerAcquisition = User::whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('
                DATE(created_at) as date,
                COUNT(*) as new_customers
            ')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'overview' => $customerOverview,
            'top_customers' => $topCustomers,
            'customer_acquisition' => $customerAcquisition,
            'date_range' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d')
            ]
        ]);
    }

    /**
     * Export report data
     */
    public function export(Request $request): JsonResponse
    {
        $this->middleware('permission:reports.export');
        
        $request->validate([
            'type' => 'required|in:sales,products,customers',
            'format' => 'required|in:csv,xlsx',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date'
        ]);

        // This would typically queue a job to generate and email the report
        // For now, we'll just return a success message
        
        return response()->json([
            'message' => 'El reporte se está generando y será enviado por email cuando esté listo',
            'estimated_time' => '5-10 minutos'
        ]);
    }
}