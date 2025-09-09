<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function __construct()
    {
        $this->middleware('admin');
    }

    /**
     * Display a listing of roles
     */
    public function index(Request $request): JsonResponse
    {
        $query = Role::with('permissions');

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        // Sort
        $sortBy = $request->get('sort_by', 'name');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        $roles = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'roles' => $roles,
            'permissions' => Permission::getGrouped()
        ]);
    }

    /**
     * Store a newly created role
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:roles,slug',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id'
        ]);

        $role = Role::create($validated);

        if (isset($validated['permissions'])) {
            $role->permissions()->sync($validated['permissions']);
        }

        return response()->json([
            'message' => 'Rol creado exitosamente',
            'role' => $role->load('permissions')
        ], 201);
    }

    /**
     * Display the specified role
     */
    public function show(Role $role): JsonResponse
    {
        return response()->json([
            'role' => $role->load('permissions'),
            'users_count' => $role->users()->count()
        ]);
    }

    /**
     * Update the specified role
     */
    public function update(Request $request, Role $role): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => ['required', 'string', 'max:255', Rule::unique('roles')->ignore($role->id)],
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id'
        ]);

        $role->update($validated);

        if (isset($validated['permissions'])) {
            $role->permissions()->sync($validated['permissions']);
        }

        return response()->json([
            'message' => 'Rol actualizado exitosamente',
            'role' => $role->load('permissions')
        ]);
    }

    /**
     * Remove the specified role
     */
    public function destroy(Role $role): JsonResponse
    {
        // Prevent deletion of system roles
        if (in_array($role->slug, ['super-admin', 'admin', 'customer'])) {
            return response()->json([
                'message' => 'No se puede eliminar un rol del sistema'
            ], 422);
        }

        // Check if role has users
        if ($role->users()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar un rol que tiene usuarios asignados'
            ], 422);
        }

        $role->delete();

        return response()->json([
            'message' => 'Rol eliminado exitosamente'
        ]);
    }

    /**
     * Get all permissions grouped by category
     */
    public function permissions(): JsonResponse
    {
        return response()->json([
            'permissions' => Permission::getGrouped(),
            'groups' => Permission::getGroups()
        ]);
    }

    /**
     * Assign users to role
     */
    public function assignUsers(Request $request, Role $role): JsonResponse
    {
        $validated = $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id'
        ]);

        $role->users()->syncWithoutDetaching($validated['user_ids']);

        return response()->json([
            'message' => 'Usuarios asignados al rol exitosamente'
        ]);
    }

    /**
     * Remove users from role
     */
    public function removeUsers(Request $request, Role $role): JsonResponse
    {
        $validated = $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id'
        ]);

        $role->users()->detach($validated['user_ids']);

        return response()->json([
            'message' => 'Usuarios removidos del rol exitosamente'
        ]);
    }
}