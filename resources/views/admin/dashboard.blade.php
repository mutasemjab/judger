@extends('layouts.admin')
@section('title', __('messages.dashboard'))
@section('page_title', __('messages.dashboard'))

@section('content')
@php $locale = app()->getLocale(); @endphp

<div class="page-header">
    <div>
        <h1>{{ __('messages.dashboard') }}</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item active">{{ __('messages.dashboard') }}</li>
            </ol>
        </nav>
    </div>
</div>

{{-- Stat Cards Row 1 --}}
<div class="row g-4 mb-4">
    <div class="col-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon-wrap bg-primary-soft"><i class="fas fa-users"></i></div>
            <div class="stat-info">
                <div class="stat-number">{{ number_format($stats['total_users']) }}</div>
                <div class="stat-label">{{ __('messages.total_users') }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon-wrap bg-success-soft"><i class="fas fa-user-check"></i></div>
            <div class="stat-info">
                <div class="stat-number">{{ number_format($stats['active_users']) }}</div>
                <div class="stat-label">{{ __('messages.active_users') }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon-wrap bg-warning-soft"><i class="fas fa-briefcase"></i></div>
            <div class="stat-info">
                <div class="stat-number">{{ number_format($stats['total_cases']) }}</div>
                <div class="stat-label">{{ __('messages.total_cases') }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon-wrap bg-info-soft"><i class="fas fa-file-lines"></i></div>
            <div class="stat-info">
                <div class="stat-number">{{ number_format($stats['total_documents']) }}</div>
                <div class="stat-label">{{ __('messages.total_documents') }}</div>
            </div>
        </div>
    </div>
</div>

{{-- Stat Cards Row 2 --}}
<div class="row g-4 mb-4">
    <div class="col-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon-wrap bg-purple-soft"><i class="fas fa-robot"></i></div>
            <div class="stat-info">
                <div class="stat-number">{{ number_format($stats['total_ai_messages']) }}</div>
                <div class="stat-label">{{ __('messages.total_ai_messages') }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon-wrap bg-success-soft"><i class="fas fa-book-open"></i></div>
            <div class="stat-info">
                <div class="stat-number">{{ number_format($stats['knowledge_documents']) }}</div>
                <div class="stat-label">{{ __('messages.knowledge_documents') }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon-wrap bg-orange-soft"><i class="fas fa-credit-card"></i></div>
            <div class="stat-info">
                <div class="stat-number">{{ number_format($stats['active_subscriptions']) }}</div>
                <div class="stat-label">{{ __('messages.active_subscriptions') }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon-wrap bg-danger-soft"><i class="fas fa-triangle-exclamation"></i></div>
            <div class="stat-info">
                <div class="stat-number">{{ number_format($stats['failed_documents']) }}</div>
                <div class="stat-label">{{ __('messages.failed_documents') }}</div>
            </div>
        </div>
    </div>
</div>

{{-- Tables row --}}
<div class="row g-4">

    {{-- Recent Users --}}
    <div class="col-12 col-lg-7">
        <div class="admin-card">
            <div class="admin-card-header">
                <h3 class="admin-card-title"><i class="fas fa-users"></i> {{ __('messages.recent_users') }}</h3>
                <a href="{{ route('admin.users.index') }}" class="btn btn-sm btn-outline-primary">{{ __('messages.view_all') }}</a>
            </div>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>{{ __('messages.name') }}</th>
                            <th>{{ __('messages.user_type') }}</th>
                            <th>{{ __('messages.account_status') }}</th>
                            <th>{{ __('messages.created_at') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentUsers as $user)
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
                            <td class="text-muted-sm">{{ $user->created_at->format('d M Y') }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="5"><div class="empty-state"><i class="fas fa-users"></i><p>{{ __('messages.no_data') }}</p></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- System info + recent cases --}}
    <div class="col-12 col-lg-5">
        <div class="admin-card mb-4">
            <div class="admin-card-header">
                <h3 class="admin-card-title"><i class="fas fa-circle-info"></i> {{ __('messages.system_info') }}</h3>
            </div>
            <div class="admin-card-body">
                <ul class="list-unstyled" style="display:flex;flex-direction:column;gap:14px;margin:0;">
                    <li class="d-flex justify-content-between align-items-center">
                        <span class="text-muted-sm"><i class="fas fa-code-branch me-2 text-primary"></i>{{ __('messages.framework') }}</span>
                        <span class="fw-600">Laravel 9</span>
                    </li>
                    <li class="d-flex justify-content-between align-items-center">
                        <span class="text-muted-sm"><i class="fas fa-globe me-2 text-info"></i>{{ __('messages.language') }}</span>
                        <span class="fw-600">{{ $locale === 'ar' ? 'العربية' : 'English' }}</span>
                    </li>
                    <li class="d-flex justify-content-between align-items-center">
                        <span class="text-muted-sm"><i class="fas fa-calendar me-2 text-success"></i>{{ __('messages.date') }}</span>
                        <span class="fw-600">{{ now()->format('d M Y') }}</span>
                    </li>
                    <li class="d-flex justify-content-between align-items-center">
                        <span class="text-muted-sm"><i class="fas fa-users me-2 text-warning"></i>{{ __('messages.new_users_month') }}</span>
                        <span class="fw-600">{{ $stats['new_users_month'] }}</span>
                    </li>
                    <li class="d-flex justify-content-between align-items-center">
                        <span class="text-muted-sm"><i class="fas fa-briefcase me-2 text-purple"></i>{{ __('messages.new_cases_month') }}</span>
                        <span class="fw-600">{{ $stats['new_cases_month'] }}</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>

</div>
@endsection
