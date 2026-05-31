@extends('layouts.admin')
@section('title', __('messages.knowledge_base'))
@section('page_title', __('messages.knowledge_base'))

@section('content')
<div class="page-header">
    <div>
        <h1>{{ __('messages.knowledge_base') }}</h1>
        <nav aria-label="breadcrumb"><ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ __('messages.dashboard') }}</a></li>
            <li class="breadcrumb-item active">{{ __('messages.knowledge_base') }}</li>
        </ol></nav>
    </div>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#uploadModal">
        <i class="fas fa-upload me-1"></i>{{ __('messages.upload_document') }}
    </button>
</div>

<form method="GET" action="{{ route('admin.knowledge.index') }}" class="filter-bar">
    <select name="status" class="form-select" style="max-width:160px;">
        <option value="">{{ __('messages.all') }} {{ __('messages.status') }}</option>
        @foreach(['uploaded','processing','processed','failed'] as $s)
        <option value="{{ $s }}" {{ request('status')===$s?'selected':'' }}>{{ __('messages.'.$s) }}</option>
        @endforeach
    </select>
    <input type="text" name="category" class="form-control" value="{{ request('category') }}" placeholder="{{ __('messages.document_category') }}..." style="max-width:180px;">
    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter me-1"></i>{{ __('messages.filter') }}</button>
    <a href="{{ route('admin.knowledge.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('messages.reset') }}</a>
</form>

<div class="admin-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title"><i class="fas fa-book-open"></i> {{ __('messages.knowledge_base') }}</h3>
        <span class="badge bg-secondary">{{ $documents->total() }} {{ __('messages.total') }}</span>
    </div>
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('messages.document_title') }}</th>
                    <th>{{ __('messages.document_category') }}</th>
                    <th>{{ __('messages.document_status') }}</th>
                    <th>{{ __('messages.qdrant_points') }}</th>
                    <th>{{ __('messages.uploaded_by') }}</th>
                    <th>{{ __('messages.processed_at') }}</th>
                    <th>{{ __('messages.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($documents as $doc)
                <tr>
                    <td class="text-muted-sm">{{ $doc->id }}</td>
                    <td>
                        <div class="fw-600">{{ $doc->title }}</div>
                        <div class="text-muted-sm">{{ $doc->original_name }}</div>
                    </td>
                    <td class="text-muted-sm">{{ $doc->category ?? '—' }}</td>
                    <td>
                        @php $st = $doc->status?->value; @endphp
                        @if($st === 'processed')
                            <span class="badge-status badge-analyzed">{{ __('messages.indexed') }}</span>
                        @else
                            <span class="badge-status badge-{{ $st }}">{{ __('messages.'.$st) }}</span>
                        @endif
                    </td>
                    <td class="text-muted-sm">{{ number_format($doc->qdrant_points_count) }}</td>
                    <td class="text-muted-sm">{{ $doc->uploadedBy->name ?? '—' }}</td>
                    <td class="text-muted-sm">{{ $doc->processed_at?->format('d M Y') ?? '—' }}</td>
                    <td>
                        <div class="d-flex gap-1">
                            <form method="POST" action="{{ route('admin.knowledge.reprocess', $doc) }}">
                                @csrf
                                <button type="submit" class="btn-action btn-action-sync" title="{{ __('messages.reprocess_doc') }}">
                                    <i class="fas fa-rotate"></i>
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.knowledge.destroy', $doc) }}">
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
                <tr><td colspan="8"><div class="empty-state"><i class="fas fa-book-open"></i><p>{{ __('messages.no_data') }}</p></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($documents->hasPages())
    <div class="admin-card-footer d-flex justify-content-center">{{ $documents->appends(request()->query())->links() }}</div>
    @endif
</div>

{{-- Upload Modal --}}
<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius:16px;">
            <div class="modal-header" style="border-bottom:1px solid var(--card-border);">
                <h5 class="modal-title fw-600"><i class="fas fa-upload me-2 text-primary"></i>{{ __('messages.upload_document') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('admin.knowledge.store') }}" enctype="multipart/form-data" class="admin-form">
                @csrf
                <div class="modal-body" style="display:flex;flex-direction:column;gap:16px;">
                    <div>
                        <label class="form-label">{{ __('messages.document_title') }} *</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div>
                        <label class="form-label">{{ __('messages.document_category') }}</label>
                        <input type="text" name="category" class="form-control" placeholder="civil_law, procedure, general...">
                    </div>
                    <div>
                        <label class="form-label">{{ __('messages.upload_document') }} * (PDF, DOCX, TXT — max 50MB)</label>
                        <input type="file" name="file" class="form-control" accept=".pdf,.docx,.txt,.doc" required>
                    </div>
                </div>
                <div class="modal-footer" style="border-top:1px solid var(--card-border);">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">{{ __('messages.cancel') }}</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-upload me-1"></i>{{ __('messages.save') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
