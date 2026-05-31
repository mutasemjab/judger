@extends('layouts.admin')
@section('title', __('messages.notifications_menu'))
@section('page_title', __('messages.notifications_menu'))

@section('content')
<div class="page-header">
    <div>
        <h1>{{ __('messages.notifications_menu') }}</h1>
        <nav aria-label="breadcrumb"><ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ __('messages.dashboard') }}</a></li>
            <li class="breadcrumb-item active">{{ __('messages.notifications_menu') }}</li>
        </ol></nav>
    </div>
</div>

<form method="GET" action="{{ route('admin.notifications.index') }}" class="filter-bar">
    <select name="type" class="form-select" style="max-width:200px;">
        <option value="">{{ __('messages.all') }} {{ __('messages.notif_type') }}</option>
        @foreach(['hearing_reminder','analysis_ready','subscription_alert','ai_suggestion','deadline','team_mention'] as $t)
        <option value="{{ $t }}" {{ request('type')===$t?'selected':'' }}>{{ __('messages.'.$t) }}</option>
        @endforeach
    </select>
    <select name="read" class="form-select" style="max-width:160px;">
        <option value="">{{ __('messages.all') }}</option>
        <option value="1" {{ request('read')==='1'?'selected':'' }}>{{ __('messages.notif_read') }}</option>
        <option value="0" {{ request('read')==='0'?'selected':'' }}>{{ __('messages.notif_unread') }}</option>
    </select>
    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter me-1"></i>{{ __('messages.filter') }}</button>
    <a href="{{ route('admin.notifications.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('messages.reset') }}</a>
</form>

<div class="admin-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title"><i class="fas fa-bell"></i> {{ __('messages.notifications_menu') }}</h3>
        <span class="badge bg-secondary">{{ $notifications->total() }} {{ __('messages.total') }}</span>
    </div>
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('messages.notif_title') }}</th>
                    <th>{{ __('messages.notif_user') }}</th>
                    <th>{{ __('messages.notif_type') }}</th>
                    <th>{{ __('messages.status') }}</th>
                    <th>{{ __('messages.created_at') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($notifications as $notif)
                <tr>
                    <td class="text-muted-sm">{{ $notif->id }}</td>
                    <td>
                        <div class="fw-600">{{ $notif->title }}</div>
                        <div class="text-muted-sm">{{ Str::limit($notif->body,60) }}</div>
                    </td>
                    <td class="text-muted-sm">{{ $notif->user->name ?? '—' }}</td>
                    <td><span class="badge-status badge-analyzed">{{ __('messages.'.$notif->type?->value) }}</span></td>
                    <td>
                        @if($notif->read_at)
                            <span class="badge-status badge-analyzed">{{ __('messages.notif_read') }}</span>
                        @else
                            <span class="badge-status badge-warning" style="background:var(--warning-soft);color:var(--warning);">{{ __('messages.notif_unread') }}</span>
                        @endif
                    </td>
                    <td class="text-muted-sm">{{ $notif->created_at->format('d M Y H:i') }}</td>
                </tr>
                @empty
                <tr><td colspan="6"><div class="empty-state"><i class="fas fa-bell"></i><p>{{ __('messages.no_data') }}</p></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($notifications->hasPages())
    <div class="admin-card-footer d-flex justify-content-center">{{ $notifications->appends(request()->query())->links() }}</div>
    @endif
</div>
@endsection
