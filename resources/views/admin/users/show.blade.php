@extends('layouts.admin')
@section('title', __('messages.user_details'))
@section('page_title', __('messages.user_details'))

@section('content')
@php $locale = app()->getLocale(); @endphp

<div class="page-header">
    <div>
        <h1>{{ __('messages.user_details') }}</h1>
        <nav aria-label="breadcrumb"><ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ __('messages.dashboard') }}</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.users.index') }}">{{ __('messages.users') }}</a></li>
            <li class="breadcrumb-item active">{{ $user->name }}</li>
        </ol></nav>
    </div>
    <div class="d-flex gap-2">
        @if($user->account_status?->value === 'active')
        <form method="POST" action="{{ route('admin.users.suspend', $user) }}">
            @csrf
            <button type="submit" class="btn btn-warning btn-sm" data-confirm="{{ __('messages.confirm_delete') }}">
                <i class="fas fa-ban me-1"></i>{{ __('messages.suspend_user') }}
            </button>
        </form>
        @else
        <form method="POST" action="{{ route('admin.users.activate', $user) }}">
            @csrf
            <button type="submit" class="btn btn-success btn-sm">
                <i class="fas fa-check me-1"></i>{{ __('messages.activate_user') }}
            </button>
        </form>
        @endif
        <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i>{{ __('messages.back') }}
        </a>
    </div>
</div>

<div class="row g-4">
    {{-- Profile card --}}
    <div class="col-12 col-lg-4">
        <div class="admin-card">
            <div class="admin-card-body text-center">
                <div class="user-avatar" style="width:72px;height:72px;font-size:1.6rem;border-radius:18px;margin:0 auto 16px;">
                    {{ strtoupper(substr($user->name,0,1)) }}
                </div>
                <h4 class="fw-600 mb-1">{{ $user->name }}</h4>
                <div class="text-muted-sm mb-3">{{ $user->email }}</div>
                <span class="badge-status badge-{{ $user->account_status?->value }}">{{ __('messages.'.$user->account_status?->value) }}</span>
            </div>
            <div class="admin-card-body" style="border-top:1px solid var(--card-border);padding-top:16px;">
                <ul class="list-unstyled" style="display:flex;flex-direction:column;gap:12px;margin:0;">
                    <li class="d-flex justify-content-between">
                        <span class="text-muted-sm">{{ __('messages.user_type') }}</span>
                        <span class="fw-600">{{ __('messages.'.$user->user_type?->value) }}</span>
                    </li>
                    <li class="d-flex justify-content-between">
                        <span class="text-muted-sm">{{ __('messages.phone') }}</span>
                        <span class="fw-600">{{ $user->phone ?? '—' }}</span>
                    </li>
                    <li class="d-flex justify-content-between">
                        <span class="text-muted-sm">{{ __('messages.email_verified') }}</span>
                        <span class="fw-600">{{ $user->email_verified_at ? __('messages.yes') : __('messages.no') }}</span>
                    </li>
                    <li class="d-flex justify-content-between">
                        <span class="text-muted-sm">{{ __('messages.last_login') }}</span>
                        <span class="fw-600">{{ $user->last_login_at?->diffForHumans() ?? '—' }}</span>
                    </li>
                    <li class="d-flex justify-content-between">
                        <span class="text-muted-sm">{{ __('messages.created_at') }}</span>
                        <span class="fw-600">{{ $user->created_at->format('d M Y') }}</span>
                    </li>
                </ul>
            </div>
        </div>

        {{-- Subscription --}}
        <div class="admin-card mt-4">
            <div class="admin-card-header">
                <h3 class="admin-card-title"><i class="fas fa-credit-card"></i> {{ __('messages.subscription') }}</h3>
            </div>
            <div class="admin-card-body">
                @if($user->subscription?->plan)
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted-sm">{{ __('messages.plan_name') }}</span>
                        <span class="fw-600">{{ __('messages.'.$user->subscription->plan->name?->value) }}</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted-sm">{{ __('messages.sub_status') }}</span>
                        <span class="badge-status badge-{{ $user->subscription->status?->value }}">{{ __('messages.'.$user->subscription->status?->value) }}</span>
                    </div>
                @else
                    <p class="text-muted-sm mb-0">{{ __('messages.no_data') }}</p>
                @endif
            </div>
        </div>
    </div>

    {{-- Stats + recent activity --}}
    <div class="col-12 col-lg-8">
        <div class="row g-4 mb-4">
            <div class="col-6 col-sm-3">
                <div class="stat-card"><div class="stat-icon-wrap bg-warning-soft"><i class="fas fa-briefcase"></i></div>
                    <div class="stat-info"><div class="stat-number">{{ $user->legalCases->count() }}</div><div class="stat-label">{{ __('messages.legal_cases') }}</div></div>
                </div>
            </div>
            <div class="col-6 col-sm-3">
                <div class="stat-card"><div class="stat-icon-wrap bg-info-soft"><i class="fas fa-file-lines"></i></div>
                    <div class="stat-info"><div class="stat-number">{{ $user->legalCases->sum(fn($c)=>$c->documents()->count()) }}</div><div class="stat-label">{{ __('messages.case_documents') }}</div></div>
                </div>
            </div>
            <div class="col-6 col-sm-3">
                <div class="stat-card"><div class="stat-icon-wrap bg-primary-soft"><i class="fas fa-comments"></i></div>
                    <div class="stat-info"><div class="stat-number">{{ $user->conversations()->count() }}</div><div class="stat-label">{{ __('messages.conversations') }}</div></div>
                </div>
            </div>
            <div class="col-6 col-sm-3">
                <div class="stat-card"><div class="stat-icon-wrap bg-success-soft"><i class="fas fa-tasks"></i></div>
                    <div class="stat-info"><div class="stat-number">{{ $user->tasks()->count() }}</div><div class="stat-label">{{ __('messages.settings') }}</div></div>
                </div>
            </div>
        </div>

        {{-- Recent activity --}}
        <div class="admin-card">
            <div class="admin-card-header">
                <h3 class="admin-card-title"><i class="fas fa-clock-rotate-left"></i> {{ __('messages.activity_logs') }}</h3>
            </div>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead><tr><th>{{ __('messages.log_action') }}</th><th>{{ __('messages.log_description') }}</th><th>{{ __('messages.log_ip') }}</th><th>{{ __('messages.log_date') }}</th></tr></thead>
                    <tbody>
                        @forelse($user->activityLogs()->latest()->limit(10)->get() as $log)
                        <tr>
                            <td><span class="fw-600">{{ $log->action }}</span></td>
                            <td class="text-muted-sm">{{ Str::limit($log->description, 60) }}</td>
                            <td class="text-muted-sm">{{ $log->ip_address ?? '—' }}</td>
                            <td class="text-muted-sm">{{ $log->created_at->format('d M Y H:i') }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="4"><div class="empty-state"><i class="fas fa-history"></i><p>{{ __('messages.no_data') }}</p></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
