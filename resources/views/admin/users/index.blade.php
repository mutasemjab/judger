@extends('layouts.admin')
@section('title', __('messages.users'))
@section('page_title', __('messages.users'))

@section('content')
@php $locale = app()->getLocale(); @endphp

<div class="page-header">
    <div>
        <h1>{{ __('messages.users') }}</h1>
        <nav aria-label="breadcrumb"><ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ __('messages.dashboard') }}</a></li>
            <li class="breadcrumb-item active">{{ __('messages.users') }}</li>
        </ol></nav>
    </div>
</div>

{{-- Filter Bar --}}
<form method="GET" action="{{ route('admin.users.index') }}" class="filter-bar">
    <input type="text" name="search" class="form-control" value="{{ request('search') }}"
           placeholder="{{ __('messages.search') }}..." style="max-width:220px;">
    <select name="status" class="form-select" style="max-width:160px;">
        <option value="">{{ __('messages.all') }} {{ __('messages.status') }}</option>
        <option value="active"    {{ request('status')==='active'?'selected':'' }}>{{ __('messages.active') }}</option>
        <option value="suspended" {{ request('status')==='suspended'?'selected':'' }}>{{ __('messages.suspended') }}</option>
        <option value="blocked"   {{ request('status')==='blocked'?'selected':'' }}>{{ __('messages.blocked') }}</option>
    </select>
    <select name="user_type" class="form-select" style="max-width:180px;">
        <option value="">{{ __('messages.all') }} {{ __('messages.user_type') }}</option>
        <option value="lawyer"      {{ request('user_type')==='lawyer'?'selected':'' }}>{{ __('messages.lawyer') }}</option>
        <option value="individual"  {{ request('user_type')==='individual'?'selected':'' }}>{{ __('messages.individual') }}</option>
        <option value="law_firm"    {{ request('user_type')==='law_firm'?'selected':'' }}>{{ __('messages.law_firm') }}</option>
        <option value="law_student" {{ request('user_type')==='law_student'?'selected':'' }}>{{ __('messages.law_student') }}</option>
    </select>
    <button type="submit" class="btn btn-primary btn-sm">
        <i class="fas fa-filter me-1"></i>{{ __('messages.filter') }}
    </button>
    <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('messages.reset') }}</a>
</form>

<div class="admin-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title"><i class="fas fa-users"></i> {{ __('messages.users') }}</h3>
        <span class="badge bg-secondary">{{ $users->total() }} {{ __('messages.total') }}</span>
    </div>
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('messages.name') }}</th>
                    <th>{{ __('messages.user_type') }}</th>
                    <th>{{ __('messages.account_status') }}</th>
                    <th>{{ __('messages.subscription') }}</th>
                    <th>{{ __('messages.last_login') }}</th>
                    <th>{{ __('messages.created_at') }}</th>
                    <th>{{ __('messages.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                <tr>
                    <td class="text-muted-sm">{{ $user->id }}</td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="user-avatar-sm">{{ strtoupper(substr($user->name,0,1)) }}</div>
                            <div>
                                <div class="fw-600">{{ $user->name }}</div>
                                <div class="text-muted-sm">{{ $user->email }}</div>
                            </div>
                        </div>
                    </td>
                    <td><span class="badge-status badge-{{ $user->user_type?->value }}">{{ __('messages.'.$user->user_type?->value) }}</span></td>
                    <td><span class="badge-status badge-{{ $user->account_status?->value }}">{{ __('messages.'.$user->account_status?->value) }}</span></td>
                    <td>
                        @if($user->subscription?->plan)
                            <span class="badge-status badge-{{ $user->subscription->plan->name?->value }}">
                                {{ __('messages.'.$user->subscription->plan->name?->value) }}
                            </span>
                        @else
                            <span class="text-muted-sm">—</span>
                        @endif
                    </td>
                    <td class="text-muted-sm">{{ $user->last_login_at?->diffForHumans() ?? '—' }}</td>
                    <td class="text-muted-sm">{{ $user->created_at->format('d M Y') }}</td>
                    <td>
                        <div class="d-flex gap-1">
                            <a href="{{ route('admin.users.show', $user) }}" class="btn-action btn-action-view" title="{{ __('messages.details') }}">
                                <i class="fas fa-eye"></i>
                            </a>
                            @if($user->account_status?->value === 'active')
                            <form method="POST" action="{{ route('admin.users.suspend', $user) }}" style="display:inline;">
                                @csrf
                                <button type="submit" class="btn-action btn-action-edit"
                                        data-confirm="{{ __('messages.confirm_delete') }}"
                                        title="{{ __('messages.suspend_user') }}">
                                    <i class="fas fa-ban"></i>
                                </button>
                            </form>
                            @else
                            <form method="POST" action="{{ route('admin.users.activate', $user) }}" style="display:inline;">
                                @csrf
                                <button type="submit" class="btn-action btn-action-toggle" title="{{ __('messages.activate_user') }}">
                                    <i class="fas fa-check"></i>
                                </button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8"><div class="empty-state"><i class="fas fa-users"></i><p>{{ __('messages.no_data') }}</p></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($users->hasPages())
    <div class="admin-card-footer d-flex justify-content-center">
        {{ $users->appends(request()->query())->links() }}
    </div>
    @endif
</div>
@endsection
