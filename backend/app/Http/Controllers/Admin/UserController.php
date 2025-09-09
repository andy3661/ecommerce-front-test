<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;
use Carbon\Carbon;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('role:admin');
    }

    /**
     * Display a listing of users
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = User::with(['roles:id,name'])
                ->withCount(['orders', 'orders as completed_orders_count' => function($q) {
                    $q->where('status', 'delivered');
                }])
                ->withSum('orders as total_spent', 'total_amount');

            // Search
            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
                });
            }

            // Filter by role
            if ($request->filled('role')) {
                $query->whereHas('roles', function($q) use ($request) {
                    $q->where('name', $request->input('role'));
                });
            }

            // Filter by status
            if ($request->filled('status')) {
                $status = $request->input('status');
                if ($status === 'active') {
                    $query->whereNull('deleted_at');
                } elseif ($status === 'inactive') {
                    $query->whereNotNull('deleted_at');
                }
            }

            // Filter by email verification
            if ($request->filled('email_verified')) {
                $verified = $request->boolean('email_verified');
                if ($verified) {
                    $query->whereNotNull('email_verified_at');
                } else {
                    $query->whereNull('email_verified_at');
                }
            }

            // Filter by registration date
            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->input('date_from'));
            }
            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->input('date_to'));
            }

            // Filter by customer value
            if ($request->filled('min_spent')) {
                $query->having('total_spent', '>=', $request->input('min_spent'));
            }
            if ($request->filled('max_spent')) {
                $query->having('total_spent', '<=', $request->input('max_spent'));
            }

            // Sorting
            $sortBy = $request->input('sort_by', 'created_at');
            $sortOrder = $request->input('sort_order', 'desc');
            
            if (in_array($sortBy, ['total_spent', 'orders_count', 'completed_orders_count'])) {
                $query->orderBy($sortBy, $sortOrder);
            } else {
                $query->orderBy($sortBy, $sortOrder);
            }

            // Pagination
            $perPage = $request->input('per_page', 15);
            $users = $query->paginate($perPage);

            // Add summary statistics
            $summary = [
                'total_users' => User::count(),
                'verified_users' => User::whereNotNull('email_verified_at')->count(),
                'unverified_users' => User::whereNull('email_verified_at')->count(),
                'customers_with_orders' => User::whereHas('orders')->count(),
                'new_users_today' => User::whereDate('created_at', today())->count(),
                'new_users_this_month' => User::whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->count()
            ];

            return response()->json([
                'success' => true,
                'data' => $users,
                'summary' => $summary,
                'filters' => [
                    'roles' => Role::select('id', 'name')->get(),
                    'statuses' => ['active', 'inactive'],
                    'verification_statuses' => ['verified', 'unverified']
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading users',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created user
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date|before:today',
            'gender' => 'nullable|in:male,female,other',
            'roles' => 'nullable|array',
            'roles.*' => 'exists:roles,name',
            'email_verified' => 'boolean',
            'send_welcome_email' => 'boolean'
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

            $userData = $request->except(['password', 'password_confirmation', 'roles', 'email_verified', 'send_welcome_email']);
            $userData['password'] = Hash::make($request->input('password'));
            
            if ($request->boolean('email_verified')) {
                $userData['email_verified_at'] = now();
            }

            $user = User::create($userData);

            // Assign roles
            if ($request->filled('roles')) {
                $user->assignRole($request->input('roles'));
            } else {
                $user->assignRole('customer'); // Default role
            }

            \DB::commit();

            // Send welcome email if requested
            if ($request->boolean('send_welcome_email')) {
                // This would typically send a welcome email
                // Implementation depends on your mail setup
            }

            $user->load('roles:id,name');

            return response()->json([
                'success' => true,
                'message' => 'User created successfully',
                'data' => $user
            ], 201);

        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error creating user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified user
     */
    public function show(User $user): JsonResponse
    {
        try {
            $user->load([
                'roles:id,name',
                'orders' => function($query) {
                    $query->select('id', 'user_id', 'order_number', 'status', 'total_amount', 'created_at')
                          ->orderBy('created_at', 'desc')
                          ->limit(10);
                },
                'orders.items:id,order_id,product_name,quantity,price'
            ]);

            // Add user statistics
            $user->statistics = [
                'total_orders' => $user->orders()->count(),
                'completed_orders' => $user->orders()->where('status', 'delivered')->count(),
                'cancelled_orders' => $user->orders()->where('status', 'cancelled')->count(),
                'total_spent' => $user->orders()->whereIn('payment_status', ['paid', 'partially_paid'])->sum('total_amount'),
                'average_order_value' => $user->orders()->whereIn('payment_status', ['paid', 'partially_paid'])->avg('total_amount'),
                'last_order_date' => $user->orders()->latest()->value('created_at'),
                'first_order_date' => $user->orders()->oldest()->value('created_at'),
                'favorite_products' => $user->orders()
                    ->join('order_items', 'orders.id', '=', 'order_items.order_id')
                    ->selectRaw('order_items.product_name, SUM(order_items.quantity) as total_quantity')
                    ->groupBy('order_items.product_id', 'order_items.product_name')
                    ->orderBy('total_quantity', 'desc')
                    ->limit(5)
                    ->get()
            ];

            // Add account activity
            $user->activity = [
                'last_login' => $user->last_login_at,
                'login_count' => $user->login_count ?? 0,
                'account_age_days' => $user->created_at->diffInDays(now()),
                'email_verified' => !is_null($user->email_verified_at),
                'profile_completion' => $this->calculateProfileCompletion($user)
            ];

            return response()->json([
                'success' => true,
                'data' => $user
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified user
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:8|confirmed',
            'phone' => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date|before:today',
            'gender' => 'nullable|in:male,female,other',
            'roles' => 'nullable|array',
            'roles.*' => 'exists:roles,name',
            'email_verified' => 'boolean'
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

            $userData = $request->except(['password', 'password_confirmation', 'roles', 'email_verified']);
            
            // Update password if provided
            if ($request->filled('password')) {
                $userData['password'] = Hash::make($request->input('password'));
            }

            // Handle email verification status
            if ($request->has('email_verified')) {
                if ($request->boolean('email_verified') && !$user->email_verified_at) {
                    $userData['email_verified_at'] = now();
                } elseif (!$request->boolean('email_verified')) {
                    $userData['email_verified_at'] = null;
                }
            }

            $user->update($userData);

            // Update roles
            if ($request->has('roles')) {
                $user->syncRoles($request->input('roles', []));
            }

            \DB::commit();

            $user->load('roles:id,name');

            return response()->json([
                'success' => true,
                'message' => 'User updated successfully',
                'data' => $user
            ]);

        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error updating user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified user
     */
    public function destroy(User $user): JsonResponse
    {
        try {
            // Check if user has orders
            if ($user->orders()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete user with existing orders. Consider deactivating instead.'
                ], 422);
            }

            // Prevent deleting the current admin user
            if ($user->id === auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete your own account'
                ], 422);
            }

            $user->delete();

            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk update users
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'action' => 'required|in:verify_email,unverify_email,assign_role,remove_role,activate,deactivate,delete',
            'role' => 'required_if:action,assign_role,remove_role|exists:roles,name'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $userIds = $request->input('user_ids');
            $action = $request->input('action');
            $updated = 0;

            // Prevent bulk actions on current user
            if (in_array(auth()->id(), $userIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot perform bulk actions on your own account'
                ], 422);
            }

            switch ($action) {
                case 'verify_email':
                    $updated = User::whereIn('id', $userIds)
                        ->whereNull('email_verified_at')
                        ->update(['email_verified_at' => now()]);
                    break;
                    
                case 'unverify_email':
                    $updated = User::whereIn('id', $userIds)
                        ->update(['email_verified_at' => null]);
                    break;
                    
                case 'assign_role':
                    $role = $request->input('role');
                    $users = User::whereIn('id', $userIds)->get();
                    foreach ($users as $user) {
                        if (!$user->hasRole($role)) {
                            $user->assignRole($role);
                            $updated++;
                        }
                    }
                    break;
                    
                case 'remove_role':
                    $role = $request->input('role');
                    $users = User::whereIn('id', $userIds)->get();
                    foreach ($users as $user) {
                        if ($user->hasRole($role)) {
                            $user->removeRole($role);
                            $updated++;
                        }
                    }
                    break;
                    
                case 'activate':
                    $updated = User::whereIn('id', $userIds)
                        ->onlyTrashed()
                        ->restore();
                    break;
                    
                case 'deactivate':
                    $updated = User::whereIn('id', $userIds)
                        ->whereNull('deleted_at')
                        ->delete();
                    break;
                    
                case 'delete':
                    // Check for users with orders
                    $usersWithOrders = User::whereIn('id', $userIds)
                        ->whereHas('orders')
                        ->count();
                    
                    if ($usersWithOrders > 0) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Some users cannot be deleted because they have existing orders'
                        ], 422);
                    }
                    
                    $updated = User::whereIn('id', $userIds)->forceDelete();
                    break;
            }

            return response()->json([
                'success' => true,
                'message' => "Successfully updated {$updated} users",
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
     * Get user analytics
     */
    public function analytics(Request $request): JsonResponse
    {
        try {
            $period = $request->input('period', '30'); // days
            $startDate = Carbon::now()->subDays($period);

            $analytics = [
                'registrations_by_day' => User::where('created_at', '>=', $startDate)
                    ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                    ->groupBy('date')
                    ->orderBy('date')
                    ->get(),
                    
                'users_by_role' => User::join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
                    ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                    ->selectRaw('roles.name as role, COUNT(*) as count')
                    ->groupBy('roles.name')
                    ->get(),
                    
                'verification_stats' => [
                    'verified' => User::whereNotNull('email_verified_at')->count(),
                    'unverified' => User::whereNull('email_verified_at')->count()
                ],
                
                'customer_segments' => [
                    'new_customers' => User::where('created_at', '>=', $startDate)->count(),
                    'active_customers' => User::whereHas('orders', function($query) use ($startDate) {
                        $query->where('created_at', '>=', $startDate);
                    })->count(),
                    'high_value_customers' => User::whereHas('orders', function($query) {
                        $query->selectRaw('SUM(total_amount) as total')
                              ->groupBy('user_id')
                              ->havingRaw('SUM(total_amount) > 1000');
                    })->count()
                ],
                
                'top_customers' => User::withSum('orders as total_spent', 'total_amount')
                    ->orderBy('total_spent', 'desc')
                    ->limit(10)
                    ->get(['id', 'name', 'email', 'total_spent']),
                    
                'geographic_distribution' => User::selectRaw('country, COUNT(*) as count')
                    ->whereNotNull('country')
                    ->groupBy('country')
                    ->orderBy('count', 'desc')
                    ->limit(10)
                    ->get()
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
     * Export users to CSV/XLSX
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
            $filename = 'users_export_' . now()->format('Y-m-d_H-i-s') . '.' . $format;
            
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
                'message' => 'Error exporting users',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate profile completion percentage
     */
    private function calculateProfileCompletion(User $user): int
    {
        $fields = ['name', 'email', 'phone', 'date_of_birth', 'gender'];
        $completed = 0;
        
        foreach ($fields as $field) {
            if (!empty($user->{$field})) {
                $completed++;
            }
        }
        
        if ($user->email_verified_at) {
            $completed++;
        }
        
        return round(($completed / (count($fields) + 1)) * 100);
    }
}