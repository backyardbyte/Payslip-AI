<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:user.view')->only(['index', 'show']);
        $this->middleware('permission:user.create')->only(['create', 'store']);
        $this->middleware('permission:user.update')->only(['edit', 'update']);
        $this->middleware('permission:user.delete')->only(['destroy']);
        $this->middleware('permission:user.activate')->only(['activate', 'deactivate']);
        $this->middleware('permission:user.assign_roles')->only(['assignRole']);
    }

    /**
     * Display a listing of users.
     */
    public function index()
    {
        $users = User::with('role')
            ->paginate(15);

        return Inertia::render('admin/users/Index', [
            'users' => $users,
        ]);
    }

    /**
     * Show the form for creating a new user.
     */
    public function create()
    {
        $roles = Role::where('is_active', true)->get();

        return Inertia::render('admin/users/Create', [
            'roles' => $roles,
        ]);
    }

    /**
     * Store a newly created user.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|string|exists:roles,name',
            'is_active' => 'boolean',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'is_active' => $request->is_active ?? true,
            'email_verified_at' => now(),
        ]);

        // Assign role using the model method
        $user->assignRole($request->role);
        $user->save();

        return response()->json([
            'message' => 'User created successfully.',
            'user' => $user->load('role')
        ]);
    }

    /**
     * Display the specified user.
     */
    public function show(User $user)
    {
        $user->load('role', 'permissions');

        return Inertia::render('admin/users/Show', [
            'user' => $user,
        ]);
    }

    /**
     * Show the form for editing the user.
     */
    public function edit(User $user)
    {
        $roles = Role::where('is_active', true)->get();
        $user->load('role');

        return Inertia::render('admin/users/Edit', [
            'user' => $user,
            'roles' => $roles,
        ]);
    }

    /**
     * Update the specified user.
     */
    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'role' => 'required|string|exists:roles,name',
            'is_active' => 'boolean',
        ]);

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'is_active' => $request->is_active ?? $user->is_active,
        ]);

        // Assign role using the model method
        $user->assignRole($request->role);
        $user->save();

        return response()->json([
            'message' => 'User updated successfully.',
            'user' => $user->load('role')
        ]);
    }

    /**
     * Remove the specified user.
     */
    public function destroy(User $user)
    {
        // Prevent deleting the last super admin
        if ($user->hasRole('super_admin')) {
            $superAdminCount = User::whereHas('role', function ($query) {
                $query->where('name', 'super_admin');
            })->where('is_active', true)->count();

            if ($superAdminCount <= 1) {
                return back()->with('error', 'Cannot delete the last super administrator.');
            }
        }

        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', 'User deleted successfully.');
    }

    /**
     * Activate a user.
     */
    public function activate(User $user)
    {
        $user->update(['is_active' => true]);

        return back()->with('success', 'User activated successfully.');
    }

    /**
     * Deactivate a user.
     */
    public function deactivate(User $user)
    {
        // Prevent deactivating the last super admin
        if ($user->hasRole('super_admin')) {
            $superAdminCount = User::whereHas('role', function ($query) {
                $query->where('name', 'super_admin');
            })->where('is_active', true)->count();

            if ($superAdminCount <= 1) {
                return back()->with('error', 'Cannot deactivate the last super administrator.');
            }
        }

        $user->update(['is_active' => false]);

        return back()->with('success', 'User deactivated successfully.');
    }

    /**
     * Assign a role to a user.
     */
    public function assignRole(Request $request, User $user)
    {
        $request->validate([
            'role_id' => 'required|exists:roles,id',
        ]);

        $user->update(['role_id' => $request->role_id]);
        $user->clearPermissionCache();

        return back()->with('success', 'Role assigned successfully.');
    }
} 