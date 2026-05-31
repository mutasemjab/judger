@extends('layouts.admin')
@section('title', __('messages.case_documents'))
@section('page_title', __('messages.case_documents'))

@section('content')
<div class="page-header">
    <div>
        <h1>{{ __('messages.case_documents') }}</h1>
        <nav aria-label="breadcrumb"><ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ __('messages.dashboard') }}</a></li>
            <li class="breadcrumb-item active">{{ __('messages.case_documents') }}</li>
        </ol></nav>
    </div>
</div>

<form method="GET" action="{{ route('admin.documents.index') }}" class="filter-bar">
    <select name="status" class="form-select" style="max-width:160px;">
        <option value="">{{ __('messages.all') }} {{ __('messages.status') }}</option>
        @foreach(['uploaded','processing','analyzed','failed'] as $s)
        <option value="{{ $s }}" {{ request('status')===$s?'selected':'' }}>{{ __('messages.'.$s) }}</option>
        @endforeach
    </select>
    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter me-1"></i>{{ __('messages.filter') }}</button>
    <a href="{{ route('admin.documents.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('messages.reset') }}</a>
</form>

<div class="admin-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title"><i class="fas fa-file-lines"></i> {{ __('messages.case_documents') }}</h3>
        <span class="badge bg-secondary">{{ $documents->total() }} {{ __('messages.total') }}</span>
    </div>
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('messages.document_name') }}</th>
                    <th>{{ __('messages.case_owner') }}</th>
                    <th>{{ __('messages.document_type') }}</th>
                    <th>{{ __('messages.document_status') }}</th>
                    <th>{{ __('messages.file_size') }}</th>
                    <th>{{ __('messages.qdrant_points') }}</th>
                    <th>{{ __('messages.processed_at') }}</th>
                    <th>{{ __('messages.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($documents as $doc)
                <tr>
                    <td class="text-muted-sm">{{ $doc->id }}</td>
                    <td>
                        <div class="fw-600">{{ Str::limit($doc->original_name, 40) }}</div>
                        <div class="text-muted-sm">{{ $doc->mime_type }}</div>
                    </td>
                    <td class="text-muted-sm">{{ $doc->user->name ?? '—' }}</td>
                    <td class="text-muted-sm">{{ $doc->document_type ?? '—' }}</td>
                    <td><span class="badge-status badge-{{ $doc->status?->value }}">{{ __('messages.'.$doc->status?->value) }}</span></td>
                    <td class="text-muted-sm">{{ $doc->file_size ? number_format($doc->file_size/1024,1).' KB' : '—' }}</td>
                    <td class="text-muted-sm">{{ $doc->qdrant_points_count }}</td>
                    <td class="text-muted-sm">{{ $doc->processed_at?->format('d M Y') ?? '—' }}</td>
                    <td>
                        <div class="d-flex gap-1">
                            @if($doc->status?->value === 'failed')
                            <form method="POST" action="{{ route('admin.documents.reprocess', $doc) }}">
                                @csrf
                                <button type="submit" class="btn-action btn-action-sync" title="{{ __('messages.reprocess') }}">
                                    <i class="fas fa-rotate"></i>
                                </button>
                            </form>
                            @endif
                            <form method="POST" action="{{ route('admin.documents.destroy', $doc) }}">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-action btn-action-delete"
                                        data-confirm="{{ __('messages.confirm_delete') }}"
                                        title="{{ __('messages.delete') }}">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="9"><div class="empty-state"><i class="fas fa-file-lines"></i><p>{{ __('messages.no_data') }}</p></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($documents->hasPages())
    <div class="admin-card-footer d-flex justify-content-center">{{ $documents->appends(request()->query())->links() }}</div>
    @endif
</div>
@endsection
