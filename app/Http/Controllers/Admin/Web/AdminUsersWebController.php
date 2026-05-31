<?php

namespace App\Http\Controllers\Admin\Web;

use App\Enums\AccountStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class AdminUsersWebController extends Controller
{
    public function index(Request $request)
    {
        $users = User::with('subscription.plan', 'roles')
            ->when($request->search, fn($q) => $q->where(fn($q2) =>
                $q2->where('name', 'LIKE', '%'.$request->search.'%')
                   ->orWhere('email', 'LIKE', '%'.$request->search.'%')
            ))
            ->when($request->status, fn($q, $v) => $q->where('account_status', $v))
            ->when($request->user_type, fn($q, $v) => $q->where('user_type', $v))
            ->latest()
            ->paginate(20);

        return view('admin.users.index', compact('users'));
    }

    public function show(User $user)
    {
        $user->load('subscription.plan', 'legalCases');
        return view('admin.users.show', compact('user'));
    }

    public function suspend(User $user)
    {
        if ($user->isAdmin()) {
            return back()->with('error', 'Cannot suspend admin users.');
        }
        $user->update(['account_status' => AccountStatus::Suspended->value]);
        return back()->with('success', __('messages.updated_success'));
    }

    public function activate(User $user)
    {
        $user->update(['account_status' => AccountStatus::Active->value]);
        return back()->with('success', __('messages.updated_success'));
    }
}
