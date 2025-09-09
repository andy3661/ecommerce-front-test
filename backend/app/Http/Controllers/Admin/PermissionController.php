<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class PermissionController extends Controller
{
    public function __construct()
    {
        $this->middleware('admin');
        $this->middleware('permission:admin.permissions');
    }

    /**
     * Display a listing of permissions
     */
    public function index(Request $request): JsonResponse
    {
        $query = Permission::with('roles');

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        // Filter by group
        if ($request->filled('group')) {
            $query->where('group', $request->group);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        // Sort
        $sortBy = $request->get('sort_by', 'group');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder)->orderBy('name', 'asc');

        if ($request->get('grouped', false)) {
            $permissions = $query->get()->groupBy('group');
        } else {
            $permissions = $query->paginate($request->get('per_page', 15));
        }

        return response()->json([
            'permissions' => $permissions,
            'groups' => Permission::getGroups()
        ]);
    }

    /**
     * Store a newly created permission
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:permissions,slug',
            'description' => 'nullable|string',
            'group' => 'required|string|max:255',
            'is_active' => 'boolean'
        ]);

        $permission = Permission::create($validated);

        return response()->json([
            'message' => 'Permiso creado exitosamente',
            'permission' => $permission
        ], 201);
    }

    /**
     * Display the specified permission
     */
    public function show(Permission $permission): JsonResponse
    {
        return response()->json([
            'permission' => $permission->load('roles'),
            'roles_count' => $permission->roles()->count(),
            'users_count' => $permission->users()->count()
        ]);
    }

    /**
     * Update the specified permission
     */
    public function update(Request $request, Permission $permission): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => ['required', 'string', 'max:255', Rule::unique('permissions')->ignore($permission->id)],
            'description' => 'nullable|string',
            'group' => 'required|string|max:255',
            'is_active' => 'boolean'
        ]);

        $permission->update($validated);

        return response()->json([
            'message' => 'Permiso actualizado exitosamente',
            'permission' => $permission
        ]);
    }

    /**
     * Remove the specified permission
     */
    public function destroy(Permission $permission): JsonResponse
    {
        // Check if permission is assigned to roles
        if ($permission->roles()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar un permiso que está asignado a roles'
            ], 422);
        }

        // Check if permission is assigned to users
        if ($permission->users()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar un permiso que está asignado a usuarios'
            ], 422);
        }

        $permission->delete();

        return response()->json([
            'message' => 'Permiso eliminado exitosamente'
        ]);
    }

    /**
     * Get permission groups
     */
    public function groups(): JsonResponse
    {
        return response()->json([
            'groups' => Permission::getGroups()
        ]);
    }

    /**
     * Bulk update permissions
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'permissions' => 'required|array',
            'permissions.*.id' => 'required|exists:permissions,id',
            'permissions.*.is_active' => 'boolean',
            'permissions.*.group' => 'string|max:255'
        ]);

        foreach ($validated['permissions'] as $permissionData) {
            $permission = Permission::find($permissionData['id']);
            $permission->update(array_filter($permissionData, function($key) {
                return $key !== 'id';
            }, ARRAY_FILTER_USE_KEY));
        }

        return response()->json([
            'message' => 'Permisos actualizados exitosamente'
        ]);
    }
}