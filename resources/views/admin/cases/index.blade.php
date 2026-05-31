@extends('layouts.admin')
@section('title', __('messages.legal_cases'))
@section('page_title', __('messages.legal_cases'))

@section('content')
<div class="page-header">
    <div>
        <h1>{{ __('messages.legal_cases') }}</h1>
        <nav aria-label="breadcrumb"><ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ __('messages.dashboard') }}</a></li>
            <li class="breadcrumb-item active">{{ __('messages.legal_cases') }}</li>
        </ol></nav>
    </div>
</div>

<form method="GET" action="{{ route('admin.cases.index') }}" class="filter-bar">
    <input type="text" name="search" class="form-control" value="{{ request('search') }}" placeholder="{{ __('messages.search') }}..." style="max-width:220px;">
    <select name="status" class="form-select" style="max-width:160px;">
        <option value="">{{ __('messages.all') }} {{ __('messages.status') }}</option>
        @foreach(['active','pending','closed','archived'] as $s)
        <option value="{{ $s }}" {{ request('status')===$s?'selected':'' }}>{{ __('messages.'.$s) }}</option>
        @endforeach
    </select>
    <select name="priority" class="form-select" style="max-width:160px;">
        <option value="">{{ __('messages.all') }} {{ __('messages.case_priority') }}</option>
        @foreach(['low','medium','high','urgent'] as $p)
        <option value="{{ $p }}" {{ request('priority')===$p?'selected':'' }}>{{ __('messages.'.$p) }}</option>
        @endforeach
    </select>
    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter me-1"></i>{{ __('messages.filter') }}</button>
    <a href="{{ route('admin.cases.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('messages.reset') }}</a>
</form>

<div class="admin-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title"><i class="fas fa-briefcase"></i> {{ __('messages.legal_cases') }}</h3>
        <span class="badge bg-secondary">{{ $cases->total() }} {{ __('messages.total') }}</span>
    </div>
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('messages.case_title') }}</th>
                    <th>{{ __('messages.case_owner') }}</th>
                    <th>{{ __('messages.status') }}</th>
                    <th>{{ __('messages.case_priority') }}</th>
                    <th>{{ __('messages.documents_count') }}</th>
                    <th>{{ __('messages.next_hearing') }}</th>
                    <th>{{ __('messages.created_at') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($cases as $case)
                <tr>
                    <td class="text-muted-sm">{{ $case->id }}</td>
                    <td>
                        <div class="fw-600">{{ Str::limit($case->title, 50) }}</div>
                        @if($case->case_number)<div class="text-muted-sm">{{ $case->case_number }}</div>@endif
                    </td>
                    <td>
                        <div class="fw-600">{{ $case->user->name ?? '—' }}</div>
                        <div class="text-muted-sm">{{ $case->user->email ?? '' }}</div>
                    </td>
                    <td><span class="badge-status badge-{{ $case->status?->value }}">{{ __('messages.'.$case->status?->value) }}</span></td>
                    <td><span class="badge-status badge-{{ $case->priority?->value }}">{{ __('messages.'.$case->priority?->value) }}</span></td>
                    <td class="text-muted-sm">{{ $case->documents_count ?? 0 }}</td>
                    <td class="text-muted-sm">{{ $case->next_hearing_at?->format('d M Y') ?? '—' }}</td>
                    <td class="text-muted-sm">{{ $case->created_at->format('d M Y') }}</td>
                </tr>
                @empty
                <tr><td colspan="8"><div class="empty-state"><i class="fas fa-briefcase"></i><p>{{ __('messages.no_data') }}</p></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($cases->hasPages())
    <div class="admin-card-footer d-flex justify-content-center">{{ $cases->appends(request()->query())->links() }}</div>
    @endif
</div>
@endsection
