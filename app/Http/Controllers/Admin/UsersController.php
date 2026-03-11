<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UsersController extends Controller
{
    public function index(Request $request): View
    {
        $query = User::with('role')->orderBy('name');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->paginate(10)->withQueryString();
        $roles = Role::orderBy('name')->get();

        return view('users', compact('users', 'roles'));
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $request->validate([
            'role_id' => ['nullable', 'exists:roles,id'],
        ]);

        $user->update(['role_id' => $request->input('role_id')]);

        return back()->with('status', 'Role updated for ' . $user->email . '.');
    }
}
