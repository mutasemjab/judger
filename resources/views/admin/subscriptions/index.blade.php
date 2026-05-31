@extends('layouts.admin')
@section('title', __('messages.subscriptions'))
@section('page_title', __('messages.subscriptions'))

@section('content')
<div class="page-header">
    <div>
        <h1>{{ __('messages.subscriptions') }}</h1>
        <nav aria-label="breadcrumb"><ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ __('messages.dashboard') }}</a></li>
            <li class="breadcrumb-item active">{{ __('messages.subscriptions') }}</li>
        </ol></nav>
    </div>
</div>

<form method="GET" action="{{ route('admin.subscriptions.index') }}" class="filter-bar">
    <select name="status" class="form-select" style="max-width:160px;">
        <option value="">{{ __('messages.all') }} {{ __('messages.status') }}</option>
        @foreach(['active','inactive','trialing','cancelled','expired'] as $s)
        <option value="{{ $s }}" {{ request('status')===$s?'selected':'' }}>{{ __('messages.'.$s) }}</option>
        @endforeach
    </select>
    <select name="plan_id" class="form-select" style="max-width:160px;">
        <option value="">{{ __('messages.all') }} {{ __('messages.plan_name') }}</option>
        @foreach($plans as $plan)
        <option value="{{ $plan->id }}" {{ request('plan_id')==$plan->id?'selected':'' }}>{{ __('messages.'.$plan->name?->value) }}</option>
        @endforeach
    </select>
    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter me-1"></i>{{ __('messages.filter') }}</button>
    <a href="{{ route('admin.subscriptions.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('messages.reset') }}</a>
</form>

<div class="admin-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title"><i class="fas fa-credit-card"></i> {{ __('messages.subscriptions') }}</h3>
        <span class="badge bg-secondary">{{ $subscriptions->total() }} {{ __('messages.total') }}</span>
    </div>
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('messages.sub_user') }}</th>
                    <th>{{ __('messages.plan_name') }}</th>
                    <th>{{ __('messages.sub_status') }}</th>
                    <th>{{ __('messages.sub_starts') }}</th>
                    <th>{{ __('messages.sub_ends') }}</th>
                    <th>{{ __('messages.created_at') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($subscriptions as $sub)
                <tr>
                    <td class="text-muted-sm">{{ $sub->id }}</td>
                    <td>
                        <div class="fw-600">{{ $sub->user->name ?? '—' }}</div>
                        <div class="text-muted-sm">{{ $sub->user->email ?? '' }}</div>
                    </td>
                    <td><span class="badge-status badge-{{ $sub->plan->name?->value }}">{{ __('messages.'.$sub->plan->name?->value) }}</span></td>
                    <td><span class="badge-status badge-{{ $sub->status?->value }}">{{ __('messages.'.$sub->status?->value) }}</span></td>
                    <td class="text-muted-sm">{{ $sub->starts_at?->format('d M Y') ?? '—' }}</td>
                    <td class="text-muted-sm">{{ $sub->ends_at?->format('d M Y') ?? '—' }}</td>
                    <td class="text-muted-sm">{{ $sub->created_at->format('d M Y') }}</td>
                </tr>
                @empty
                <tr><td colspan="7"><div class="empty-state"><i class="fas fa-credit-card"></i><p>{{ __('messages.no_data') }}</p></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($subscriptions->hasPages())
    <div class="admin-card-footer d-flex justify-content-center">{{ $subscriptions->appends(request()->query())->links() }}</div>
    @endif
</div>
@endsection
