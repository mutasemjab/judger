@extends('layouts.admin')
@section('title', __('messages.activity_logs'))
@section('page_title', __('messages.activity_logs'))

@section('content')
<div class="page-header">
    <div>
        <h1>{{ __('messages.activity_logs') }}</h1>
        <nav aria-label="breadcrumb"><ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ __('messages.dashboard') }}</a></li>
            <li class="breadcrumb-item active">{{ __('messages.activity_logs') }}</li>
        </ol></nav>
    </div>
</div>

<form method="GET" action="{{ route('admin.activity.index') }}" class="filter-bar">
    <input type="text" name="action" class="form-control" value="{{ request('action') }}" placeholder="{{ __('messages.log_action') }}..." style="max-width:200px;">
    <input type="text" name="user_id" class="form-control" value="{{ request('user_id') }}" placeholder="User ID..." style="max-width:120px;">
    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter me-1"></i>{{ __('messages.filter') }}</button>
    <a href="{{ route('admin.activity.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('messages.reset') }}</a>
</form>

<div class="admin-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title"><i class="fas fa-clock-rotate-left"></i> {{ __('messages.activity_logs') }}</h3>
        <span class="badge bg-secondary">{{ $logs->total() }} {{ __('messages.total') }}</span>
    </div>
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('messages.log_action') }}</th>
                    <th>{{ __('messages.log_user') }}</th>
                    <th>{{ __('messages.log_description') }}</th>
                    <th>{{ __('messages.log_ip') }}</th>
                    <th>{{ __('messages.log_date') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                <tr>
                    <td class="text-muted-sm">{{ $log->id }}</td>
                    <td><span class="fw-600" style="font-size:0.82rem;">{{ $log->action }}</span></td>
                    <td>
                        @if($log->user)
                            <div class="fw-600">{{ $log->user->name }}</div>
                            <div class="text-muted-sm">{{ $log->user->email }}</div>
                        @else
                            <span class="text-muted-sm">System</span>
                        @endif
                    </td>
                    <td class="text-muted-sm">{{ Str::limit($log->description, 80) ?? '—' }}</td>
                    <td class="text-muted-sm">{{ $log->ip_address ?? '—' }}</td>
                    <td class="text-muted-sm">{{ $log->created_at->format('d M Y H:i') }}</td>
                </tr>
                @empty
                <tr><td colspan="6"><div class="empty-state"><i class="fas fa-history"></i><p>{{ __('messages.no_data') }}</p></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($logs->hasPages())
    <div class="admin-card-footer d-flex justify-content-center">{{ $logs->appends(request()->query())->links() }}</div>
    @endif
</div>
@endsection
