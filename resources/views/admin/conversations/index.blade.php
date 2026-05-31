@extends('layouts.admin')
@section('title', __('messages.conversations'))
@section('page_title', __('messages.conversations'))

@section('content')
<div class="page-header">
    <div>
        <h1>{{ __('messages.conversations') }}</h1>
        <nav aria-label="breadcrumb"><ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ __('messages.dashboard') }}</a></li>
            <li class="breadcrumb-item active">{{ __('messages.conversations') }}</li>
        </ol></nav>
    </div>
</div>

<form method="GET" action="{{ route('admin.conversations.index') }}" class="filter-bar">
    <select name="type" class="form-select" style="max-width:160px;">
        <option value="">{{ __('messages.all') }}</option>
        <option value="general" {{ request('type')==='general'?'selected':'' }}>General</option>
        <option value="case" {{ request('type')==='case'?'selected':'' }}>Case</option>
    </select>
    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter me-1"></i>{{ __('messages.filter') }}</button>
    <a href="{{ route('admin.conversations.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('messages.reset') }}</a>
</form>

<div class="admin-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title"><i class="fas fa-comments"></i> {{ __('messages.conversations') }}</h3>
        <span class="badge bg-secondary">{{ $conversations->total() }} {{ __('messages.total') }}</span>
    </div>
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('messages.name') }}</th>
                    <th>{{ __('messages.tool_user') }}</th>
                    <th>{{ __('messages.status') }}</th>
                    <th>{{ __('messages.total_ai_messages') }}</th>
                    <th>{{ __('messages.created_at') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($conversations as $conv)
                <tr>
                    <td class="text-muted-sm">{{ $conv->id }}</td>
                    <td>
                        <div class="fw-600">{{ $conv->title ?? '(' . $conv->type?->value . ')' }}</div>
                        @if($conv->legalCase)<div class="text-muted-sm"><i class="fas fa-briefcase me-1"></i>{{ Str::limit($conv->legalCase->title,40) }}</div>@endif
                    </td>
                    <td class="text-muted-sm">{{ $conv->user->name ?? '—' }}</td>
                    <td><span class="badge-status badge-analyzed">{{ $conv->type?->value }}</span></td>
                    <td class="text-muted-sm">{{ $conv->messages_count }}</td>
                    <td class="text-muted-sm">{{ $conv->created_at->format('d M Y') }}</td>
                </tr>
                @empty
                <tr><td colspan="6"><div class="empty-state"><i class="fas fa-comments"></i><p>{{ __('messages.no_data') }}</p></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($conversations->hasPages())
    <div class="admin-card-footer d-flex justify-content-center">{{ $conversations->appends(request()->query())->links() }}</div>
    @endif
</div>
@endsection
