<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class OrderController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('role:admin');
    }

    /**
     * Display a listing of orders
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Order::with(['user:id,name,email', 'items.product:id,name,sku'])
                ->withCount('items');

            // Search
            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function($q) use ($search) {
                    $q->where('order_number', 'like', "%{$search}%")
                      ->orWhere('customer_email', 'like', "%{$search}%")
                      ->orWhere('customer_name', 'like', "%{$search}%")
                      ->orWhere('customer_phone', 'like', "%{$search}%")
                      ->orWhereHas('user', function($userQuery) use ($search) {
                          $userQuery->where('name', 'like', "%{$search}%")
                                   ->orWhere('email', 'like', "%{$search}%");
                      });
                });
            }

            // Filter by status
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }

            // Filter by payment status
            if ($request->filled('payment_status')) {
                $query->where('payment_status', $request->input('payment_status'));
            }

            // Filter by shipping status
            if ($request->filled('shipping_status')) {
                $query->where('shipping_status', $request->input('shipping_status'));
            }

            // Filter by date range
            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->input('date_from'));
            }
            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->input('date_to'));
            }

            // Filter by amount range
            if ($request->filled('min_amount')) {
                $query->where('total_amount', '>=', $request->input('min_amount'));
            }
            if ($request->filled('max_amount')) {
                $query->where('total_amount', '<=', $request->input('max_amount'));
            }

            // Filter by customer type
            if ($request->filled('customer_type')) {
                $customerType = $request->input('customer_type');
                if ($customerType === 'registered') {
                    $query->whereNotNull('user_id');
                } elseif ($customerType === 'guest') {
                    $query->whereNull('user_id');
                }
            }

            // Sorting
            $sortBy = $request->input('sort_by', 'created_at');
            $sortOrder = $request->input('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = $request->input('per_page', 15);
            $orders = $query->paginate($perPage);

            // Add summary statistics
            $summary = [
                'total_orders' => Order::count(),
                'pending_orders' => Order::where('status', 'pending')->count(),
                'processing_orders' => Order::where('status', 'processing')->count(),
                'shipped_orders' => Order::where('status', 'shipped')->count(),
                'delivered_orders' => Order::where('status', 'delivered')->count(),
                'cancelled_orders' => Order::where('status', 'cancelled')->count(),
                'total_revenue' => Order::whereIn('payment_status', ['paid', 'partially_paid'])->sum('total_amount'),
                'today_orders' => Order::whereDate('created_at', today())->count(),
                'today_revenue' => Order::whereDate('created_at', today())
                    ->whereIn('payment_status', ['paid', 'partially_paid'])
                    ->sum('total_amount')
            ];

            return response()->json([
                'success' => true,
                'data' => $orders,
                'summary' => $summary,
                'filters' => [
                    'statuses' => ['pending', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'],
                    'payment_statuses' => ['pending', 'paid', 'failed', 'cancelled', 'refunded', 'partially_paid'],
                    'shipping_statuses' => ['pending', 'processing', 'shipped', 'delivered', 'returned'],
                    'customer_types' => ['all', 'registered', 'guest']
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading orders',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified order
     */
    public function show(Order $order): JsonResponse
    {
        try {
            $order->load([
                'user:id,name,email,phone',
                'items.product:id,name,sku,price,images',
                'items.productVariant:id,name,sku,price,attributes',
                'payments',
                'shippingLabels',
                'statusHistory' => function($query) {
                    $query->orderBy('created_at', 'desc');
                }
            ]);

            // Add calculated fields
            $order->items_summary = [
                'total_items' => $order->items->sum('quantity'),
                'unique_products' => $order->items->count(),
                'total_weight' => $order->items->sum(function($item) {
                    return $item->quantity * ($item->product->weight ?? 0);
                })
            ];

            $order->payment_summary = [
                'total_paid' => $order->payments->where('status', 'completed')->sum('amount'),
                'total_refunded' => $order->payments->where('type', 'refund')->sum('amount'),
                'pending_amount' => $order->total_amount - $order->payments->where('status', 'completed')->sum('amount')
            ];

            return response()->json([
                'success' => true,
                'data' => $order
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update order status
     */
    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,processing,shipped,delivered,cancelled,refunded',
            'notes' => 'nullable|string|max:1000',
            'notify_customer' => 'boolean',
            'tracking_number' => 'nullable|string|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $oldStatus = $order->status;
            $newStatus = $request->input('status');

            // Validate status transition
            if (!$this->isValidStatusTransition($oldStatus, $newStatus)) {
                return response()->json([
                    'success' => false,
                    'message' => "Invalid status transition from {$oldStatus} to {$newStatus}"
                ], 422);
            }

            \DB::beginTransaction();

            // Update order status
            $order->update([
                'status' => $newStatus,
                'tracking_number' => $request->input('tracking_number', $order->tracking_number)
            ]);

            // Add status history
            $order->statusHistory()->create([
                'status' => $newStatus,
                'notes' => $request->input('notes'),
                'changed_by' => auth()->id(),
                'changed_at' => now()
            ]);

            // Handle specific status changes
            switch ($newStatus) {
                case 'shipped':
                    $order->update(['shipped_at' => now()]);
                    break;
                case 'delivered':
                    $order->update(['delivered_at' => now()]);
                    break;
                case 'cancelled':
                    $order->update(['cancelled_at' => now()]);
                    // Handle inventory restoration if needed
                    $this->restoreInventory($order);
                    break;
            }

            // Send notification to customer if requested
            if ($request->boolean('notify_customer')) {
                $this->notifyCustomer($order, $newStatus, $request->input('notes'));
            }

            \DB::commit();

            $order->load('statusHistory');

            return response()->json([
                'success' => true,
                'message' => 'Order status updated successfully',
                'data' => $order
            ]);

        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error updating order status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update order items
     */
    public function updateItems(Request $request, Order $order): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'items' => 'required|array',
            'items.*.id' => 'nullable|exists:order_items,id',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.product_variant_id' => 'nullable|exists:product_variants,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if order can be modified
        if (in_array($order->status, ['shipped', 'delivered', 'cancelled', 'refunded'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot modify items for orders with status: ' . $order->status
            ], 422);
        }

        try {
            \DB::beginTransaction();

            // Remove existing items
            $order->items()->delete();

            $subtotal = 0;
            $totalTax = 0;

            // Add new items
            foreach ($request->input('items') as $itemData) {
                $product = Product::find($itemData['product_id']);
                $itemTotal = $itemData['quantity'] * $itemData['price'];
                $itemTax = $itemTotal * ($order->tax_rate / 100);

                $order->items()->create([
                    'product_id' => $itemData['product_id'],
                    'product_variant_id' => $itemData['product_variant_id'] ?? null,
                    'quantity' => $itemData['quantity'],
                    'price' => $itemData['price'],
                    'total' => $itemTotal,
                    'product_name' => $product->name,
                    'product_sku' => $product->sku
                ]);

                $subtotal += $itemTotal;
                $totalTax += $itemTax;
            }

            // Recalculate order totals
            $order->update([
                'subtotal' => $subtotal,
                'tax_amount' => $totalTax,
                'total_amount' => $subtotal + $totalTax + $order->shipping_cost
            ]);

            \DB::commit();

            $order->load('items.product');

            return response()->json([
                'success' => true,
                'message' => 'Order items updated successfully',
                'data' => $order
            ]);

        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error updating order items',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add notes to order
     */
    public function addNotes(Request $request, Order $order): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'notes' => 'required|string|max:1000',
            'type' => 'required|in:internal,customer'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $notesField = $request->input('type') === 'internal' ? 'internal_notes' : 'customer_notes';
            $currentNotes = $order->{$notesField} ?? '';
            $newNote = "[" . now()->format('Y-m-d H:i:s') . "] " . $request->input('notes');
            $updatedNotes = $currentNotes ? $currentNotes . "\n" . $newNote : $newNote;

            $order->update([$notesField => $updatedNotes]);

            return response()->json([
                'success' => true,
                'message' => 'Notes added successfully',
                'data' => $order
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error adding notes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk update orders
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'order_ids' => 'required|array',
            'order_ids.*' => 'exists:orders,id',
            'action' => 'required|in:update_status,cancel,export',
            'status' => 'required_if:action,update_status|in:pending,processing,shipped,delivered,cancelled',
            'notify_customers' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $orderIds = $request->input('order_ids');
            $action = $request->input('action');
            $updated = 0;

            switch ($action) {
                case 'update_status':
                    $status = $request->input('status');
                    $orders = Order::whereIn('id', $orderIds)->get();
                    
                    foreach ($orders as $order) {
                        if ($this->isValidStatusTransition($order->status, $status)) {
                            $order->update(['status' => $status]);
                            
                            // Add status history
                            $order->statusHistory()->create([
                                'status' => $status,
                                'notes' => 'Bulk status update',
                                'changed_by' => auth()->id(),
                                'changed_at' => now()
                            ]);
                            
                            if ($request->boolean('notify_customers')) {
                                $this->notifyCustomer($order, $status);
                            }
                            
                            $updated++;
                        }
                    }
                    break;
                    
                case 'cancel':
                    $orders = Order::whereIn('id', $orderIds)
                        ->whereNotIn('status', ['shipped', 'delivered', 'cancelled'])
                        ->get();
                    
                    foreach ($orders as $order) {
                        $order->update([
                            'status' => 'cancelled',
                            'cancelled_at' => now()
                        ]);
                        
                        $this->restoreInventory($order);
                        
                        if ($request->boolean('notify_customers')) {
                            $this->notifyCustomer($order, 'cancelled');
                        }
                        
                        $updated++;
                    }
                    break;
                    
                case 'export':
                    // This would typically generate a CSV/Excel file
                    return response()->json([
                        'success' => true,
                        'message' => 'Export started. Download link will be sent to your email.',
                        'data' => ['export_id' => uniqid()]
                    ]);
            }

            return response()->json([
                'success' => true,
                'message' => "Successfully updated {$updated} orders",
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
     * Get order analytics
     */
    public function analytics(Request $request): JsonResponse
    {
        try {
            $period = $request->input('period', '30'); // days
            $startDate = Carbon::now()->subDays($period);

            $analytics = [
                'orders_by_status' => Order::where('created_at', '>=', $startDate)
                    ->selectRaw('status, COUNT(*) as count')
                    ->groupBy('status')
                    ->pluck('count', 'status'),
                    
                'revenue_by_day' => Order::where('created_at', '>=', $startDate)
                    ->whereIn('payment_status', ['paid', 'partially_paid'])
                    ->selectRaw('DATE(created_at) as date, SUM(total_amount) as revenue')
                    ->groupBy('date')
                    ->orderBy('date')
                    ->get(),
                    
                'orders_by_day' => Order::where('created_at', '>=', $startDate)
                    ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                    ->groupBy('date')
                    ->orderBy('date')
                    ->get(),
                    
                'top_products' => OrderItem::whereHas('order', function($query) use ($startDate) {
                        $query->where('created_at', '>=', $startDate);
                    })
                    ->selectRaw('product_id, product_name, SUM(quantity) as total_sold, SUM(total) as revenue')
                    ->groupBy('product_id', 'product_name')
                    ->orderBy('total_sold', 'desc')
                    ->limit(10)
                    ->get(),
                    
                'average_order_value' => Order::where('created_at', '>=', $startDate)
                    ->avg('total_amount'),
                    
                'customer_types' => [
                    'registered' => Order::where('created_at', '>=', $startDate)
                        ->whereNotNull('user_id')
                        ->count(),
                    'guest' => Order::where('created_at', '>=', $startDate)
                        ->whereNull('user_id')
                        ->count()
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $analytics
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if status transition is valid
     */
    private function isValidStatusTransition($from, $to): bool
    {
        $validTransitions = [
            'pending' => ['processing', 'cancelled'],
            'processing' => ['shipped', 'cancelled'],
            'shipped' => ['delivered'],
            'delivered' => ['refunded'],
            'cancelled' => [],
            'refunded' => []
        ];

        return in_array($to, $validTransitions[$from] ?? []);
    }

    /**
     * Restore inventory for cancelled order
     */
    private function restoreInventory(Order $order): void
    {
        foreach ($order->items as $item) {
            if ($item->product && $item->product->manage_stock) {
                $item->product->increment('stock_quantity', $item->quantity);
            }
        }
    }

    /**
     * Notify customer about order status change
     */
    private function notifyCustomer(Order $order, string $status, string $notes = null): void
    {
        // This would typically send an email notification
        // Implementation depends on your mail setup
    }
}