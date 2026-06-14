@extends('layouts.admin')
@section('title', __('messages.knowledge_base'))
@section('page_title', __('messages.knowledge_base'))

@php
    $locale = app()->getLocale();
    $maxUploadMb = (int) ceil(\App\Models\KnowledgeDocument::MAX_UPLOAD_SIZE_KB / 1024);
    $issuesCount = ($stats['failed'] ?? 0) + ($stats['cancelled'] ?? 0);
    $sourceLabel = match($aiConfig['source'] ?? null) {
        'config' => $locale === 'ar' ? 'إعدادات التطبيق' : 'Application config',
        'env_file' => '.env file',
        'server_env' => $locale === 'ar' ? 'بيئة الخادم' : 'Server environment',
        'runtime_file' => $locale === 'ar' ? 'ملف التشغيل المحلي' : 'Local runtime file',
        default => $locale === 'ar' ? 'غير متوفر' : 'Not configured',
    };
@endphp

@section('content')
<style>
/* ── KB Page Layout ─────────────────────────────────────────── */
.kb-page { display:flex; flex-direction:column; gap:1.5rem; }

/* ── Header ─────────────────────────────────────────────────── */
.kb-header {
    display:flex; align-items:center; justify-content:space-between;
    flex-wrap:wrap; gap:1rem;
}
.kb-header h1 { font-size:1.35rem; font-weight:700; margin:0; }
.kb-stats-row {
    display:flex; align-items:center; flex-wrap:wrap; gap:.5rem; margin-top:.4rem;
}
.kb-stat-chip {
    display:inline-flex; align-items:center; gap:.35rem;
    padding:.2rem .65rem; border-radius:2rem; font-size:.78rem; font-weight:600;
    background:var(--bs-light,#f8f9fa); border:1px solid rgba(0,0,0,.07);
}
.kb-stat-chip .kc-num { font-size:.95rem; }
.kb-stat-chip.kc-indexed  { background:#e8f5e9; border-color:#a5d6a7; color:#2e7d32; }
.kb-stat-chip.kc-process  { background:#e3f2fd; border-color:#90caf9; color:#1565c0; }
.kb-stat-chip.kc-pending  { background:#fff8e1; border-color:#ffe082; color:#f57f17; }
.kb-stat-chip.kc-issues   { background:#fce4ec; border-color:#f48fb1; color:#b71c1c; }

/* ── Upload Panel ────────────────────────────────────────────── */
.kb-upload-panel {
    background:#fff; border:1px solid rgba(0,0,0,.08); border-radius:.75rem;
    overflow:hidden; box-shadow:0 1px 4px rgba(0,0,0,.06);
}
.kb-upload-panel-header {
    display:flex; align-items:center; justify-content:space-between;
    padding:.85rem 1.25rem; border-bottom:1px solid rgba(0,0,0,.07);
    background:#fafafa;
}
.kb-upload-panel-header h3 {
    font-size:.95rem; font-weight:700; margin:0; display:flex; align-items:center; gap:.5rem;
}
.kb-upload-panel-header h3 i { color:var(--bs-primary,#0d6efd); }
.kb-upload-panel-body {
    display:grid; grid-template-columns:1fr 340px; gap:0;
}
@media(max-width:860px){ .kb-upload-panel-body { grid-template-columns:1fr; } }

.kb-upload-left { padding:1.25rem; border-right:1px solid rgba(0,0,0,.07); }
@media(max-width:860px){ .kb-upload-left { border-right:none; border-bottom:1px solid rgba(0,0,0,.07); } }

/* Dropzone */
.kb-dropzone {
    display:flex; flex-direction:column; align-items:center; justify-content:center;
    gap:.35rem; padding:1.5rem 1rem; border:2px dashed rgba(13,110,253,.25);
    border-radius:.6rem; cursor:pointer; background:#f8fbff; transition:.2s;
    text-align:center;
}
.kb-dropzone:hover, .kb-dropzone.is-dragging {
    border-color:var(--bs-primary,#0d6efd); background:#eef4ff;
}
.kb-dropzone i { font-size:2rem; color:var(--bs-primary,#0d6efd); opacity:.7; }
.kb-dropzone p  { margin:0; font-size:.875rem; color:#555; }
.kb-dropzone p strong { color:var(--bs-primary,#0d6efd); cursor:pointer; }
.kb-dropzone small { font-size:.75rem; color:#888; }

/* Controls row */
.kb-upload-controls {
    display:flex; align-items:center; flex-wrap:wrap; gap:.5rem; margin-top:.9rem;
}
.kb-upload-controls .form-control { flex:1; min-width:140px; font-size:.85rem; }
.kb-upload-action-btns { display:flex; flex-wrap:wrap; gap:.4rem; }

/* Progress */
.kb-progress { margin-top:.85rem; }
.kb-progress-track {
    height:5px; background:#e9ecef; border-radius:99px; overflow:hidden;
}
.kb-progress-track span {
    display:block; height:100%; background:var(--bs-primary,#0d6efd);
    border-radius:99px; width:0; transition:width .3s;
}
.kb-progress-meta {
    display:flex; justify-content:space-between; font-size:.75rem;
    color:#888; margin-top:.3rem;
}

/* Queue panel */
.kb-queue { display:flex; flex-direction:column; min-height:200px; }
.kb-queue-head {
    display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap;
    gap:.4rem; padding:.75rem 1rem; border-bottom:1px solid rgba(0,0,0,.07);
    background:#fafafa; font-size:.8rem;
}
.kb-queue-body { flex:1; overflow-y:auto; max-height:260px; }
.kb-queue-empty {
    display:flex; flex-direction:column; align-items:center; justify-content:center;
    height:140px; color:#aaa; gap:.4rem;
}
.kb-queue-empty i { font-size:1.6rem; }
.kb-queue-empty p { font-size:.8rem; margin:0; }

/* Queue items */
.knowledge-batch-item {
    display:flex; align-items:flex-start; justify-content:space-between;
    gap:.6rem; padding:.6rem 1rem; border-bottom:1px solid rgba(0,0,0,.05);
    font-size:.8rem; transition:.15s;
}
.knowledge-batch-item:last-child { border-bottom:none; }
.knowledge-batch-item-name { font-weight:600; word-break:break-all; }
.knowledge-batch-item-meta { color:#888; font-size:.73rem; margin-top:.1rem; }
.knowledge-batch-item-progress { color:#555; margin-top:.15rem; }
.knowledge-batch-item-message { color:#dc3545; margin-top:.15rem; }
.knowledge-batch-item-time { color:#888; font-size:.72rem; margin-top:.1rem; }
.knowledge-batch-item-actions { display:flex; flex-wrap:wrap; gap:.25rem; margin-top:.3rem; }
.knowledge-inline-action {
    display:inline-flex; align-items:center; gap:.25rem;
    border:none; border-radius:.3rem; padding:.2rem .45rem;
    font-size:.71rem; cursor:pointer; transition:.15s;
}
.knowledge-inline-action i { font-size:.68rem; }
.knowledge-inline-action-primary { background:#e7f0ff; color:#0d6efd; }
.knowledge-inline-action-primary:hover { background:#c9ddff; }
.knowledge-inline-action-warning { background:#fff3cd; color:#856404; }
.knowledge-inline-action-warning:hover { background:#ffe69c; }
.knowledge-inline-action-danger  { background:#fce8e8; color:#dc3545; }
.knowledge-inline-action-danger:hover  { background:#f8c2c2; }
.knowledge-inline-action-muted   { background:#f1f3f5; color:#6c757d; }
.knowledge-inline-action-muted:hover   { background:#dee2e6; }
.knowledge-batch-item-side {
    display:flex; flex-direction:column; align-items:flex-end; gap:.25rem; white-space:nowrap;
}

/* ── Library Section ─────────────────────────────────────────── */
.kb-library-card {
    background:#fff; border:1px solid rgba(0,0,0,.08); border-radius:.75rem;
    overflow:hidden; box-shadow:0 1px 4px rgba(0,0,0,.06);
}
.kb-library-toolbar {
    display:flex; align-items:center; flex-wrap:wrap; gap:.6rem;
    padding:.75rem 1.25rem; border-bottom:1px solid rgba(0,0,0,.07);
    background:#fafafa;
}
.kb-library-toolbar form { display:flex; align-items:center; flex-wrap:wrap; gap:.4rem; flex:1; }
.kb-toolbar-actions { display:flex; align-items:center; gap:.4rem; flex-wrap:wrap; }
.kb-visible-count { font-size:.78rem; color:#888; }

/* Table */
.kb-table { width:100%; border-collapse:collapse; font-size:.85rem; }
.kb-table thead tr { background:#f8f9fa; }
.kb-table th {
    padding:.65rem 1rem; font-weight:600; font-size:.78rem; color:#555;
    border-bottom:2px solid rgba(0,0,0,.07); text-align:left; white-space:nowrap;
}
.kb-table td {
    padding:.7rem 1rem; border-bottom:1px solid rgba(0,0,0,.05); vertical-align:middle;
}
.kb-table tbody tr:last-child td { border-bottom:none; }
.kb-table tbody tr:hover td { background:rgba(0,0,0,.018); }
.kb-doc-name { font-weight:600; margin-bottom:.1rem; }
.kb-doc-filename { font-size:.75rem; color:#888; }
.kb-chunk-count { font-weight:600; }
.kb-chunk-sub { font-size:.73rem; color:#888; }
.table-status-note { font-size:.72rem; color:#666; margin-top:.15rem; }
.table-status-note.text-danger { color:#dc3545 !important; }

/* Actions */
.knowledge-table-actions { display:flex; flex-wrap:wrap; gap:.3rem; }
.knowledge-table-actions .btn { display:inline-flex; align-items:center; gap:.3rem; white-space:nowrap; }
.knowledge-table-actions .btn i { font-size:.7rem; }

/* Status badges */
.kb-badge {
    display:inline-flex; align-items:center; gap:.25rem;
    padding:.2rem .55rem; border-radius:2rem; font-size:.73rem; font-weight:600;
}
.kb-badge-uploaded   { background:#f1f3f5; color:#495057; }
.kb-badge-processing { background:#e3f2fd; color:#1565c0; }
.kb-badge-processed  { background:#e8f5e9; color:#2e7d32; }
.kb-badge-failed     { background:#fce4ec; color:#b71c1c; }
.kb-badge-cancelled  { background:#fff3e0; color:#e65100; }
.kb-badge-pending    { background:#f1f3f5; color:#666; }

/* Empty state */
.kb-empty {
    padding:3rem 1rem; text-align:center; color:#aaa;
}
.kb-empty i { font-size:2.5rem; display:block; margin-bottom:.6rem; }
.kb-empty p { margin:0; font-size:.875rem; }

/* Pagination */
.kb-pagination { padding:.75rem 1.25rem; border-top:1px solid rgba(0,0,0,.07); }
</style>

<div class="kb-page">

{{-- ── Header ──────────────────────────────────────────────────── --}}
<div class="kb-header">
    <div>
        <h1><i class="fas fa-brain me-2" style="color:var(--bs-primary);font-size:1.1rem;"></i>{{ __('messages.knowledge_base') }}</h1>
        <div class="kb-stats-row">
            <span class="kb-stat-chip">
                <i class="fas fa-database" style="color:#888;font-size:.7rem;"></i>
                <span>{{ __('messages.total') }}: </span><span class="kc-num">{{ number_format($stats['total']) }}</span>
            </span>
            <span class="kb-stat-chip kc-indexed">
                <i class="fas fa-check-circle" style="font-size:.7rem;"></i>
                <span>{{ __('messages.indexed_documents') }}: </span><span class="kc-num">{{ number_format($stats['processed']) }}</span>
            </span>
            <span class="kb-stat-chip kc-process">
                <i class="fas fa-spinner fa-spin" style="font-size:.7rem;"></i>
                <span>{{ __('messages.processing') }}: </span><span class="kc-num">{{ number_format($stats['processing']) }}</span>
            </span>
            <span class="kb-stat-chip kc-pending">
                <i class="fas fa-clock" style="font-size:.7rem;"></i>
                <span>{{ __('messages.uploaded') }}: </span><span class="kc-num">{{ number_format($stats['uploaded']) }}</span>
            </span>
            @if($issuesCount > 0)
            <span class="kb-stat-chip kc-issues">
                <i class="fas fa-triangle-exclamation" style="font-size:.7rem;"></i>
                <span>{{ __('messages.issues') }}: </span><span class="kc-num">{{ number_format($issuesCount) }}</span>
            </span>
            @endif
            <span class="kb-stat-chip" style="font-size:.72rem;color:#888;">
                <i class="fas fa-server" style="font-size:.65rem;"></i>{{ $vectorIndex['label'] }}
            </span>
        </div>
    </div>
    <button class="btn btn-primary btn-sm" type="button" id="openBatchUploader">
        <i class="fas fa-upload me-1"></i>{{ __('messages.upload_files') }}
    </button>
</div>

<div class="kb-upload-panel">
    <div class="kb-upload-panel-header">
        <h3><i class="fas fa-key"></i> {{ $locale === 'ar' ? 'إعدادات OpenAI للتضمين' : 'OpenAI Embedding Settings' }}</h3>
        <div style="font-size:.78rem;color:#888;">
            {{ !empty($aiConfig['configured'])
                ? (($locale === 'ar' ? 'المصدر الحالي للمفتاح: ' : 'Current key source: ') . $sourceLabel)
                : ($locale === 'ar'
                    ? 'لن تنجح التضمينات حتى يتم توفير مفتاح OpenAI صالح.'
                    : 'Embeddings will keep failing until a valid OpenAI key is available.') }}
        </div>
    </div>
    <div class="p-3">
        <form action="{{ route('admin.knowledge.openai-config') }}" method="POST" class="row g-2 align-items-end">
            @csrf
            <div class="col-lg-5">
                <label for="openaiApiKey" class="form-label fw-semibold mb-1">
                    {{ $locale === 'ar' ? 'مفتاح OpenAI API' : 'OpenAI API Key' }}
                </label>
                <input
                    type="password"
                    id="openaiApiKey"
                    name="openai_api_key"
                    class="form-control @error('openai_api_key') is-invalid @enderror"
                    placeholder="sk-proj-..."
                    autocomplete="off"
                >
                @error('openai_api_key')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-lg-4">
                <label for="openaiOrganization" class="form-label fw-semibold mb-1">
                    {{ $locale === 'ar' ? 'المؤسسة (اختياري)' : 'Organization (Optional)' }}
                </label>
                <input
                    type="text"
                    id="openaiOrganization"
                    name="openai_organization"
                    class="form-control @error('openai_organization') is-invalid @enderror"
                    placeholder="{{ $locale === 'ar' ? 'اتركه فارغاً غالباً' : 'Usually leave this empty' }}"
                >
                @error('openai_organization')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-lg-3 d-grid">
                <button type="submit" class="btn btn-outline-primary">
                    <i class="fas fa-floppy-disk me-1"></i>{{ $locale === 'ar' ? 'حفظ المفتاح لهذا الخادم' : 'Save Key For This Server' }}
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ── Upload Panel ─────────────────────────────────────────────── --}}
<div class="kb-upload-panel" id="knowledgeBatchUploader">
    <div class="kb-upload-panel-header">
        <h3><i class="fas fa-layer-group"></i> {{ __('messages.batch_uploader') }}</h3>
        <div style="display:flex;align-items:center;gap:.5rem;font-size:.78rem;color:#888;">
            <span id="knowledgeBatchSelectedCount">0 {{ __('messages.files') }}</span>
            <span>·</span>
            <span id="knowledgeReadyCounter">{{ __('messages.ready_to_upload') }}: 0</span>
        </div>
    </div>
    <div class="kb-upload-panel-body">

        {{-- Left: upload controls --}}
        <div class="kb-upload-left">
            <div class="kb-dropzone" id="knowledgeDropzone" tabindex="0" role="button" aria-label="{{ __('messages.select_files') }}">
                <input type="file" id="knowledgeFiles" class="d-none" accept=".pdf,.doc,.docx,.pptx,.txt" multiple>
                <i class="fas fa-cloud-arrow-up"></i>
                <p>{{ __('messages.drag_drop_knowledge') }} &nbsp;<strong id="selectKnowledgeFilesBtn">{{ __('messages.select_files') }}</strong></p>
                <small>PDF · DOCX · PPTX · TXT &nbsp;·&nbsp; {{ $maxUploadMb }}MB max</small>
            </div>

            <div class="kb-upload-controls">
                <input type="text" class="form-control form-control-sm" id="knowledgeBatchCategory"
                    placeholder="{{ __('messages.document_category') }}…">
                <div class="kb-upload-action-btns">
                    <button type="button" class="btn btn-outline-primary btn-sm" id="uploadKnowledgeOnly" disabled>
                        <i class="fas fa-cloud-arrow-up me-1"></i>{{ __('messages.upload_files') }}
                    </button>
                    <button type="button" class="btn btn-primary btn-sm" id="uploadAndStartKnowledgeBatch" disabled>
                        <i class="fas fa-play me-1"></i>{{ __('messages.upload_and_start_all') }}
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="stopKnowledgeBatch" disabled>
                        <i class="fas fa-hand me-1"></i>{{ __('messages.stop_current_run') }}
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="clearKnowledgeBatch" disabled>
                        <i class="fas fa-broom me-1"></i>{{ __('messages.clear_finished') }}
                    </button>
                </div>
            </div>

            <div class="kb-progress">
                <div class="kb-progress-track">
                    <span id="knowledgeBatchProgressBar"></span>
                </div>
                <div class="kb-progress-meta">
                    <span id="knowledgeBatchProgressNote">{{ __('messages.no_files_selected') }}</span>
                    <span id="knowledgeBatchActivity">0 / 0</span>
                </div>
            </div>

            {{-- Mini summary --}}
            <div style="display:flex;flex-wrap:wrap;gap:.35rem;margin-top:.75rem;">
                <span class="kb-stat-chip"><span>{{ __('messages.ready_to_upload') }}</span>&nbsp;<strong id="summaryWaiting">0</strong></span>
                <span class="kb-stat-chip kc-process"><span>{{ __('messages.queued_for_indexing') }}</span>&nbsp;<strong id="summaryQueued">0</strong></span>
                <span class="kb-stat-chip kc-process"><span>{{ __('messages.processing') }}</span>&nbsp;<strong id="summaryProcessing">0</strong></span>
                <span class="kb-stat-chip kc-indexed"><span>{{ __('messages.indexed_documents') }}</span>&nbsp;<strong id="summaryProcessed">0</strong></span>
                <span class="kb-stat-chip kc-issues"><span>{{ __('messages.issues') }}</span>&nbsp;<strong id="summaryFailed">0</strong></span>
                <span class="kb-stat-chip" style="display:none;"><strong id="summaryTotal">0</strong></span>
            </div>
        </div>

        {{-- Right: file queue --}}
        <div class="kb-queue">
            <div class="kb-queue-head">
                <span style="font-weight:600;color:#444;">{{ __('messages.selected_batch_files') }}</span>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="refreshKnowledgeStatuses" style="padding:.15rem .5rem;font-size:.72rem;">
                    <i class="fas fa-rotate me-1"></i>{{ __('messages.refresh_status') }}
                </button>
            </div>
            <div class="kb-queue-body">
                <div id="knowledgeBatchList">
                    <div class="kb-queue-empty">
                        <i class="fas fa-file-circle-plus"></i>
                        <p>{{ __('messages.no_files_selected') }}</p>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

{{-- ── Library ───────────────────────────────────────────────────── --}}
<div class="kb-library-card">

    {{-- Toolbar --}}
    <div class="kb-library-toolbar">
        <form method="GET" action="{{ route('admin.knowledge.index') }}">
            <select name="status" class="form-select form-select-sm" style="min-width:150px;">
                <option value="">{{ __('messages.all') }} {{ __('messages.status') }}</option>
                @foreach(['uploaded', 'processing', 'processed', 'failed', 'cancelled'] as $status)
                    <option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>
                        {{ __('messages.' . $status) }}
                    </option>
                @endforeach
            </select>
            <input type="text" name="category" class="form-control form-control-sm"
                value="{{ request('category') }}" placeholder="{{ __('messages.document_category') }}…"
                style="min-width:160px;">
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="fas fa-filter me-1"></i>{{ __('messages.filter') }}
            </button>
            <a href="{{ route('admin.knowledge.index') }}" class="btn btn-outline-secondary btn-sm">
                {{ __('messages.reset') }}
            </a>
        </form>

        <div class="kb-toolbar-actions">
            <button type="button" class="btn btn-primary btn-sm" id="startVisibleKnowledgeDocs">
                <i class="fas fa-play me-1"></i>{{ __('messages.start_all_ready') }}
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="stopVisibleKnowledgeDocs">
                <i class="fas fa-stop me-1"></i>{{ __('messages.stop_processing') }}
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="refreshKnowledgeStatusesSecondary">
                <i class="fas fa-rotate"></i>
            </button>
            <span class="kb-visible-count" id="knowledgeDocumentsTableCount">
                {{ $documents->count() }} {{ __('messages.visible_count') }}
            </span>
        </div>
    </div>

    {{-- Table --}}
    <div class="table-responsive">
        <table class="kb-table">
            <thead>
                <tr>
                    <th style="width:42px;">#</th>
                    <th>{{ __('messages.document_title') }}</th>
                    <th>{{ __('messages.document_category') }}</th>
                    <th>{{ __('messages.document_status') }}</th>
                    <th>{{ __('messages.chunk_progress') }}</th>
                    <th>{{ __('messages.uploaded_by') }}</th>
                    <th>{{ __('messages.processed_at') }}</th>
                    <th style="width:180px;">{{ __('messages.actions') }}</th>
                </tr>
            </thead>
            <tbody id="knowledgeDocumentsTableBody">
                @forelse($documents as $doc)
                    @php
                        $statusValue = $doc->status?->value;
                        $canStart = $doc->canStartProcessing();
                        $canStop  = $doc->canStopProcessing();
                        $chunkTotal = (int) $doc->total_chunks_count;
                        $chunkDone  = (int) $doc->processed_chunks_count;
                    @endphp
                    <tr
                        id="knowledge-doc-row-{{ $doc->id }}"
                        class="knowledge-doc-row knowledge-doc-row--{{ $statusValue }}"
                        data-knowledge-row="{{ $doc->id }}"
                        data-status="{{ $statusValue }}"
                        data-category="{{ strtolower((string) $doc->category) }}"
                        data-can-start="{{ $canStart ? 1 : 0 }}"
                        data-can-stop="{{ $canStop ? 1 : 0 }}"
                    >
                        <td style="color:#999;font-size:.78rem;">{{ $doc->id }}</td>
                        <td>
                            <div class="kb-doc-name">{{ $doc->title }}</div>
                            <div class="kb-doc-filename">{{ $doc->original_name }}</div>
                        </td>
                        <td style="color:#777;font-size:.82rem;">{{ $doc->category ?? '—' }}</td>
                        <td>
                            @php
                                $badgeClass = match($statusValue) {
                                    'processed'  => 'kb-badge-processed',
                                    'processing' => 'kb-badge-processing',
                                    'failed'     => 'kb-badge-failed',
                                    'cancelled'  => 'kb-badge-cancelled',
                                    'uploaded'   => 'kb-badge-uploaded',
                                    default      => 'kb-badge-pending',
                                };
                                $badgeLabel = $statusValue === 'processed'
                                    ? __('messages.indexed')
                                    : __('messages.' . $statusValue);
                            @endphp
                            <span class="kb-badge {{ $badgeClass }}">{{ $badgeLabel }}</span>

                            @if($chunkTotal > 0 && in_array($statusValue, ['processing','processed','failed','cancelled'], true))
                                <div class="table-status-note">{{ __('messages.processing_progress_value', ['processed' => $chunkDone, 'total' => $chunkTotal]) }}</div>
                            @endif
                            @if($statusValue === 'processing' && $doc->processing_started_at)
                                <div class="table-status-note">{{ __('messages.processing_started_label') }}: {{ $doc->processing_started_at->format('d M Y · H:i') }}</div>
                            @endif
                            @if(in_array($statusValue, ['failed','cancelled'], true) && $doc->processing_error)
                                <div class="table-status-note text-danger">{{ $doc->processing_error }}</div>
                            @endif
                        </td>
                        <td>
                            <div class="kb-chunk-count">{{ number_format($doc->qdrant_points_count) }}</div>
                            <div class="kb-chunk-sub">
                                {{ $chunkTotal > 0 ? $chunkDone.'/'.$chunkTotal : '—' }} {{ __('messages.chunks') }}
                            </div>
                        </td>
                        <td style="color:#777;font-size:.82rem;">{{ $doc->uploadedBy->name ?? '—' }}</td>
                        <td style="color:#777;font-size:.78rem;">{{ $doc->processed_at?->format('d M Y · H:i') ?? '—' }}</td>
                        <td>
                            <div class="knowledge-table-actions">
                                @if($canStart)
                                    <button type="button" class="btn btn-outline-primary btn-sm knowledge-doc-action"
                                        data-action="start" data-document-id="{{ $doc->id }}">
                                        <i class="fas fa-play"></i>{{ __('messages.start_processing') }}
                                    </button>
                                @elseif($statusValue === 'processed')
                                    <button type="button" class="btn btn-outline-secondary btn-sm knowledge-doc-action"
                                        data-action="reprocess" data-document-id="{{ $doc->id }}">
                                        <i class="fas fa-rotate"></i>{{ __('messages.reprocess_doc') }}
                                    </button>
                                @endif
                                @if($canStop)
                                    <button type="button" class="btn btn-outline-warning btn-sm knowledge-doc-action"
                                        data-action="stop" data-document-id="{{ $doc->id }}">
                                        <i class="fas fa-stop"></i>
                                    </button>
                                @endif
                                <button type="button" class="btn btn-outline-danger btn-sm knowledge-doc-action"
                                    data-action="delete" data-document-id="{{ $doc->id }}">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr data-empty-state="true">
                        <td colspan="8">
                            <div class="kb-empty">
                                <i class="fas fa-book-open"></i>
                                <p>{{ __('messages.no_data') }}</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($documents->hasPages())
        <div class="kb-pagination d-flex justify-content-center">
            {{ $documents->appends(request()->query())->links() }}
        </div>
    @endif

</div>

</div>{{-- .kb-page --}}
@endsection

@section('script')
<script>
(function () {
    const fileInput = document.getElementById('knowledgeFiles');
    const dropzone = document.getElementById('knowledgeDropzone');
    const selectBtn = document.getElementById('selectKnowledgeFilesBtn');
    const openBtn = document.getElementById('openBatchUploader');
    const uploadOnlyBtn = document.getElementById('uploadKnowledgeOnly');
    const uploadStartBtn = document.getElementById('uploadAndStartKnowledgeBatch');
    const stopBatchBtn = document.getElementById('stopKnowledgeBatch');
    const clearBtn = document.getElementById('clearKnowledgeBatch');
    const startVisibleBtn = document.getElementById('startVisibleKnowledgeDocs');
    const stopVisibleBtn = document.getElementById('stopVisibleKnowledgeDocs');
    const refreshBtn = document.getElementById('refreshKnowledgeStatuses');
    const refreshBtnSecondary = document.getElementById('refreshKnowledgeStatusesSecondary');
    const batchList = document.getElementById('knowledgeBatchList');
    const batchCategory = document.getElementById('knowledgeBatchCategory');
    const progressBar = document.getElementById('knowledgeBatchProgressBar');
    const progressNote = document.getElementById('knowledgeBatchProgressNote');
    const batchActivity = document.getElementById('knowledgeBatchActivity');
    const selectedCount = document.getElementById('knowledgeBatchSelectedCount');
    const readyCounter = document.getElementById('knowledgeReadyCounter');
    const summaryTotal = document.getElementById('summaryTotal');
    const summaryWaiting = document.getElementById('summaryWaiting');
    const summaryQueued = document.getElementById('summaryQueued');
    const summaryProcessing = document.getElementById('summaryProcessing');
    const summaryProcessed = document.getElementById('summaryProcessed');
    const summaryFailed = document.getElementById('summaryFailed');
    const tableBody = document.getElementById('knowledgeDocumentsTableBody');
    const tableCount = document.getElementById('knowledgeDocumentsTableCount');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    const storeUrl = @json(route('admin.knowledge.store'));
    const statusesUrl = @json(route('admin.knowledge.statuses'));
    const processNowUrlTemplate = @json(route('admin.knowledge.process-now', ['knowledgeDocument' => '__ID__']));
    const stopUrlTemplate = @json(route('admin.knowledge.stop', ['knowledgeDocument' => '__ID__']));
    const reprocessUrlTemplate = @json(route('admin.knowledge.reprocess', ['knowledgeDocument' => '__ID__']));
    const destroyUrlTemplate = @json(route('admin.knowledge.destroy', ['knowledgeDocument' => '__ID__']));
    const activeFilters = {
        status: @json((string) request('status')),
        category: @json(strtolower((string) request('category'))),
    };

    const labels = {
        files: @json(__('messages.files')),
        visible: @json(__('messages.visible_count')),
        waiting: @json(__('messages.waiting')),
        uploading: @json(__('messages.uploading_now')),
        upload_failed: @json(__('messages.upload_failed')),
        uploaded: @json(__('messages.uploaded')),
        processing: @json(__('messages.processing')),
        processed: @json(__('messages.indexed')),
        failed: @json(__('messages.failed')),
        cancelled: @json(__('messages.cancelled')),
        ready_to_upload: @json(__('messages.ready_to_upload')),
        ready_to_start: @json(__('messages.ready_to_start')),
        no_files_selected: @json(__('messages.no_files_selected')),
        upload_complete: @json(__('messages.upload_complete_hint')),
        run_stopping: @json(__('messages.batch_stop_requested')),
        processing_progress: @json(__('messages.processing_progress_value', ['processed' => '__DONE__', 'total' => '__TOTAL__'])),
        processing_started: @json(__('messages.processing_started_label')),
        preparing_chunks: @json(__('messages.processing_preparing_chunks')),
        indexing_chunks: @json(__('messages.processing_indexing_chunks')),
        start_processing: @json(__('messages.start_processing')),
        stop_processing: @json(__('messages.stop_processing')),
        reprocess: @json(__('messages.reprocess_doc')),
        delete: @json(__('messages.delete')),
        remove: @json(__('messages.remove_file')),
        delete_confirm: @json(__('messages.confirm_delete')),
        stop_confirm: @json(__('messages.confirm_stop_processing')),
        no_data: @json(__('messages.no_data')),
    };

    const state = {
        items: [],
        isUploading: false,
        isProcessingQueue: false,
        stopRequested: false,
        pollTimer: null,
        counter: 0,
    };

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatBytes(bytes) {
        if (!bytes) { return '0 B'; }
        const units = ['B', 'KB', 'MB', 'GB'];
        const index = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
        const value = bytes / Math.pow(1024, index);
        return `${value.toFixed(value >= 10 || index === 0 ? 0 : 1)} ${units[index]}`;
    }

    function formatDate(value) {
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) { return '—'; }
        return date.toLocaleString(document.documentElement.lang || 'en', {
            day: '2-digit', month: 'short', year: 'numeric',
            hour: '2-digit', minute: '2-digit',
        });
    }

    function titleFromFileName(name) {
        const withoutExtension = name.replace(/\.[^.]+$/, '');
        return withoutExtension
            .replace(/[_\-.]+/g, ' ')
            .replace(/\s+/g, ' ')
            .trim()
            .replace(/\b\w/g, function (char) { return char.toUpperCase(); });
    }

    function createItem(file) {
        state.counter += 1;
        return {
            localId: `local-${state.counter}`,
            signature: [file.name, file.size, file.lastModified].join(':'),
            file,
            originalName: file.name,
            title: titleFromFileName(file.name),
            size: file.size,
            stage: 'waiting',
            message: '',
            documentId: null,
            category: '',
            qdrantPoints: 0,
            processedChunks: 0,
            totalChunks: 0,
            processedAt: null,
            processingStartedAt: null,
            canStart: false,
            canStop: false,
        };
    }

    function findItemByDocumentId(documentId) {
        return state.items.find(function (item) { return item.documentId === documentId; }) || null;
    }

    function addFiles(fileList) {
        Array.from(fileList || []).forEach(function (file) {
            const signature = [file.name, file.size, file.lastModified].join(':');
            const exists = state.items.some(function (item) { return item.signature === signature; });
            if (!exists) { state.items.push(createItem(file)); }
        });
        renderQueue();
        updateSummary();
    }

    function queueBadgeClass(stage) {
        switch (stage) {
            case 'waiting':    return 'badge-pending';
            case 'uploading':
            case 'processing': return 'badge-processing';
            case 'uploaded':   return 'badge-uploaded';
            case 'processed':  return 'badge-analyzed';
            case 'cancelled':  return 'badge-cancelled';
            case 'failed':
            case 'upload_failed': return 'badge-failed';
            default:           return 'badge-inactive';
        }
    }

    function queueBadgeLabel(stage) { return labels[stage] || stage; }

    function isTerminalStage(stage) {
        return ['processed', 'failed', 'cancelled', 'upload_failed'].includes(stage);
    }

    function createActionButton(action, icon, text, variant, id) {
        return `<button type="button" class="knowledge-inline-action knowledge-inline-action-${variant}" data-action="${action}" data-id="${id}"><i class="fas fa-${icon}"></i>${escapeHtml(text)}</button>`;
    }

    function queueActions(item) {
        const actions = [];
        if (item.stage === 'waiting') {
            actions.push(createActionButton('remove-local', 'xmark', labels.remove, 'muted', item.localId));
        }
        if (item.documentId && item.canStart) {
            actions.push(createActionButton('start', 'play', labels.start_processing, 'primary', item.documentId));
        }
        if (item.documentId && item.stage === 'processed') {
            actions.push(createActionButton('reprocess', 'rotate', labels.reprocess, 'primary', item.documentId));
        }
        if (item.documentId && item.canStop) {
            actions.push(createActionButton('stop', 'stop', labels.stop_processing, 'warning', item.documentId));
        }
        if (item.documentId) {
            actions.push(createActionButton('delete', 'trash', labels.delete, 'danger', item.documentId));
        }
        return actions.length > 0 ? `<div class="knowledge-batch-item-actions">${actions.join('')}</div>` : '';
    }

    function progressLabel(processed, total) {
        return labels.processing_progress
            .replace('__DONE__', String(processed || 0))
            .replace('__TOTAL__', String(total || 0));
    }

    function statusDetailMessage(docData) {
        if (docData.processing_error) { return docData.processing_error; }
        if (docData.status === 'processing' && Number(docData.total_chunks_count || 0) === 0) { return labels.preparing_chunks; }
        if (docData.status === 'processing') { return labels.indexing_chunks; }
        return '';
    }

    function renderQueue() {
        if (state.items.length === 0) {
            batchList.innerHTML = `<div class="kb-queue-empty"><i class="fas fa-file-circle-plus"></i><p>${escapeHtml(labels.no_files_selected)}</p></div>`;
            return;
        }
        batchList.innerHTML = state.items.map(function (item, index) {
            const metaBits = [
                item.title,
                item.size ? formatBytes(item.size) : null,
                item.documentId ? `#${item.documentId}` : null,
                item.category || null,
            ].filter(Boolean);
            const message  = item.message ? `<div class="knowledge-batch-item-message">${escapeHtml(item.message)}</div>` : '';
            const progress = item.totalChunks > 0 ? `<div class="knowledge-batch-item-progress">${escapeHtml(progressLabel(item.processedChunks, item.totalChunks))}</div>` : '';
            const startedAt = item.processingStartedAt && item.stage === 'processing'
                ? `<div class="knowledge-batch-item-time">${escapeHtml(labels.processing_started)}: ${escapeHtml(formatDate(item.processingStartedAt))}</div>` : '';
            const processedAt = item.processedAt && item.stage !== 'processing'
                ? `<div class="knowledge-batch-item-time">${escapeHtml(formatDate(item.processedAt))}</div>` : '';
            return `
                <div class="knowledge-batch-item knowledge-batch-item--${escapeHtml(item.stage)}" data-queue-item="${escapeHtml(item.localId)}" style="--queue-index:${index};">
                    <div class="knowledge-batch-item-main">
                        <div class="knowledge-batch-item-name">${escapeHtml(item.originalName)}</div>
                        <div class="knowledge-batch-item-meta">${metaBits.map(escapeHtml).join(' · ')}</div>
                        ${progress}${message}${queueActions(item)}
                    </div>
                    <div class="knowledge-batch-item-side">
                        <span class="badge-status ${queueBadgeClass(item.stage)}">${escapeHtml(queueBadgeLabel(item.stage))}</span>
                        ${startedAt || processedAt}
                    </div>
                </div>`;
        }).join('');
    }

    function updateSummary() {
        const total      = state.items.length;
        const waiting    = state.items.filter(function (i) { return i.stage === 'waiting'; }).length;
        const queued     = state.items.filter(function (i) { return ['uploaded','failed','cancelled'].includes(i.stage); }).length;
        const processing = state.items.filter(function (i) { return ['uploading','processing'].includes(i.stage); }).length;
        const processed  = state.items.filter(function (i) { return i.stage === 'processed'; }).length;
        const failed     = state.items.filter(function (i) { return ['failed','cancelled','upload_failed'].includes(i.stage); }).length;
        const finished   = processed + failed;
        const progress   = total > 0 ? Math.round((finished / total) * 100) : 0;

        selectedCount.textContent = `${total} ${labels.files}`;
        readyCounter.textContent  = `${labels.ready_to_upload}: ${waiting}`;
        summaryTotal.textContent     = total;
        summaryWaiting.textContent   = waiting;
        summaryQueued.textContent    = queued;
        summaryProcessing.textContent= processing;
        summaryProcessed.textContent = processed;
        summaryFailed.textContent    = failed;
        batchActivity.textContent    = `${finished} / ${total}`;
        progressBar.style.width      = `${progress}%`;

        if (total === 0) {
            progressNote.textContent = labels.no_files_selected;
        } else if (state.stopRequested) {
            progressNote.textContent = labels.run_stopping;
        } else if (state.isUploading || state.isProcessingQueue) {
            progressNote.textContent = `${processed} ${labels.processed.toLowerCase()} · ${processing + queued} active · ${failed} issues`;
        } else if (waiting > 0) {
            progressNote.textContent = labels.upload_complete;
        } else {
            progressNote.textContent = `${processed} ${labels.processed.toLowerCase()} · ${failed} issues`;
        }

        uploadOnlyBtn.disabled  = waiting === 0 || state.isUploading;
        uploadStartBtn.disabled = waiting === 0 || state.isUploading || state.isProcessingQueue;
        stopBatchBtn.disabled   = !state.isUploading && !state.isProcessingQueue && collectVisibleProcessingDocumentIds().length === 0;
        clearBtn.disabled       = finished === 0;
        startVisibleBtn.disabled = state.isProcessingQueue || collectStartableVisibleDocumentIds().length === 0;
        stopVisibleBtn.disabled  = collectVisibleProcessingDocumentIds().length === 0;
    }

    function matchesActiveFilters(docData) {
        if (activeFilters.status && activeFilters.status !== docData.status) { return false; }
        if (activeFilters.category && activeFilters.category !== String(docData.category || '').toLowerCase()) { return false; }
        return true;
    }

    function tableStatusBadge(docData) {
        const map = { processed:'kb-badge-processed', processing:'kb-badge-processing', failed:'kb-badge-failed', cancelled:'kb-badge-cancelled', uploaded:'kb-badge-uploaded' };
        const cls  = map[docData.status] || 'kb-badge-pending';
        const lbl  = docData.status === 'processed' ? escapeHtml(labels.processed) : escapeHtml(queueBadgeLabel(docData.status));
        return `<span class="kb-badge ${cls}">${lbl}</span>`;
    }

    function buildStatusNotes(docData) {
        const notes = [];
        if (docData.status === 'processing' && Number(docData.total_chunks_count || 0) === 0) {
            notes.push(`<div class="table-status-note">${escapeHtml(labels.preparing_chunks)}</div>`);
        }
        if (Number(docData.total_chunks_count || 0) > 0 && ['processing','processed','failed','cancelled'].includes(docData.status)) {
            notes.push(`<div class="table-status-note">${escapeHtml(progressLabel(docData.processed_chunks_count, docData.total_chunks_count))}</div>`);
        }
        if (docData.status === 'processing' && docData.processing_started_at) {
            notes.push(`<div class="table-status-note">${escapeHtml(labels.processing_started)}: ${escapeHtml(formatDate(docData.processing_started_at))}</div>`);
        }
        if (['failed','cancelled'].includes(docData.status) && docData.processing_error) {
            notes.push(`<div class="table-status-note text-danger">${escapeHtml(docData.processing_error)}</div>`);
        }
        return notes.join('');
    }

    function buildTableActions(docData) {
        const buttons = [];
        if (docData.can_start_processing) {
            buttons.push(`<button type="button" class="btn btn-outline-primary btn-sm knowledge-doc-action" data-action="start" data-document-id="${docData.id}"><i class="fas fa-play"></i>${escapeHtml(labels.start_processing)}</button>`);
        } else if (docData.status === 'processed') {
            buttons.push(`<button type="button" class="btn btn-outline-secondary btn-sm knowledge-doc-action" data-action="reprocess" data-document-id="${docData.id}"><i class="fas fa-rotate"></i>${escapeHtml(labels.reprocess)}</button>`);
        }
        if (docData.can_stop_processing) {
            buttons.push(`<button type="button" class="btn btn-outline-warning btn-sm knowledge-doc-action" data-action="stop" data-document-id="${docData.id}"><i class="fas fa-stop"></i></button>`);
        }
        buttons.push(`<button type="button" class="btn btn-outline-danger btn-sm knowledge-doc-action" data-action="delete" data-document-id="${docData.id}"><i class="fas fa-trash"></i></button>`);
        return `<div class="knowledge-table-actions">${buttons.join('')}</div>`;
    }

    function buildKnowledgeTableRow(docData) {
        const uploadedBy  = docData.uploaded_by && docData.uploaded_by.name ? docData.uploaded_by.name : '—';
        const chunkTotal  = Number(docData.total_chunks_count || 0);
        const chunkDone   = Number(docData.processed_chunks_count || 0);
        const points      = Number(docData.qdrant_points_count || 0).toLocaleString();
        return `
            <tr
                id="knowledge-doc-row-${docData.id}"
                class="knowledge-doc-row knowledge-doc-row--${escapeHtml(docData.status)}"
                data-knowledge-row="${docData.id}"
                data-status="${escapeHtml(docData.status)}"
                data-category="${escapeHtml(String(docData.category || '').toLowerCase())}"
                data-can-start="${docData.can_start_processing ? '1' : '0'}"
                data-can-stop="${docData.can_stop_processing ? '1' : '0'}"
            >
                <td style="color:#999;font-size:.78rem;">${docData.id}</td>
                <td>
                    <div class="kb-doc-name">${escapeHtml(docData.title || '')}</div>
                    <div class="kb-doc-filename">${escapeHtml(docData.original_name || '')}</div>
                </td>
                <td style="color:#777;font-size:.82rem;">${escapeHtml(docData.category || '—')}</td>
                <td>${tableStatusBadge(docData)}${buildStatusNotes(docData)}</td>
                <td>
                    <div class="kb-chunk-count">${points}</div>
                    <div class="kb-chunk-sub">${chunkTotal > 0 ? `${chunkDone}/${chunkTotal}` : '—'} ${escapeHtml(@json(__('messages.chunks')))}</div>
                </td>
                <td style="color:#777;font-size:.82rem;">${escapeHtml(uploadedBy)}</td>
                <td style="color:#777;font-size:.78rem;">${docData.processed_at ? escapeHtml(formatDate(docData.processed_at)) : '—'}</td>
                <td>${buildTableActions(docData)}</td>
            </tr>`;
    }

    function updateTableVisibleCount() {
        const rows = tableBody.querySelectorAll('tr[data-knowledge-row]').length;
        tableCount.textContent = `${rows} ${labels.visible}`;
    }

    function ensureTableEmptyState() {
        const rows = tableBody.querySelectorAll('tr[data-knowledge-row]');
        const emptyState = tableBody.querySelector('tr[data-empty-state="true"]');
        if (rows.length === 0 && !emptyState) {
            tableBody.innerHTML = `<tr data-empty-state="true"><td colspan="8"><div class="kb-empty"><i class="fas fa-book-open"></i><p>${escapeHtml(labels.no_data)}</p></div></td></tr>`;
        }
        if (rows.length > 0 && emptyState) { emptyState.remove(); }
        updateTableVisibleCount();
    }

    function upsertKnowledgeTableRow(docData) {
        const existingRow = document.getElementById(`knowledge-doc-row-${docData.id}`);
        if (!matchesActiveFilters(docData)) {
            if (existingRow) { existingRow.remove(); }
            ensureTableEmptyState();
            updateSummary();
            return;
        }
        tableBody.querySelector('tr[data-empty-state="true"]')?.remove();
        if (existingRow) {
            existingRow.outerHTML = buildKnowledgeTableRow(docData);
        } else {
            tableBody.insertAdjacentHTML('afterbegin', buildKnowledgeTableRow(docData));
        }
        ensureTableEmptyState();
        updateSummary();
    }

    function removeKnowledgeTableRow(documentId) {
        document.getElementById(`knowledge-doc-row-${documentId}`)?.remove();
        ensureTableEmptyState();
        updateSummary();
    }

    function updateItemFromDocument(item, docData) {
        item.documentId = docData.id;
        item.title = docData.title || item.title;
        item.originalName = docData.original_name || item.originalName;
        item.category = docData.category || '';
        item.stage = docData.status || item.stage;
        item.qdrantPoints = Number(docData.qdrant_points_count || 0);
        item.processedChunks = Number(docData.processed_chunks_count || 0);
        item.totalChunks = Number(docData.total_chunks_count || 0);
        item.processedAt = docData.processed_at || null;
        item.processingStartedAt = docData.processing_started_at || null;
        item.canStart = Boolean(docData.can_start_processing);
        item.canStop = Boolean(docData.can_stop_processing);
        item.message = statusDetailMessage(docData);
    }

    function extractRequestError(payload, fallback) {
        if (payload && payload.errors) {
            const firstKey = Object.keys(payload.errors)[0];
            if (firstKey && Array.isArray(payload.errors[firstKey]) && payload.errors[firstKey][0]) {
                return payload.errors[firstKey][0];
            }
        }
        if (payload && payload.message) { return payload.message; }
        return fallback;
    }

    function extractDocumentPayload(payload) {
        if (!payload || typeof payload !== 'object') { return null; }
        if (payload.data && payload.data.id) { return payload.data; }
        if (payload.id) { return payload; }
        return null;
    }

    function createRequestError(message, status) {
        const error = new Error(message);
        error.status = status;
        return error;
    }

    function showActionError(error) {
        progressNote.textContent = error instanceof Error ? error.message : labels.failed;
    }

    function wait(ms) { return new Promise(function (resolve) { window.setTimeout(resolve, ms); }); }

    async function requestJson(url, options) {
        const response = await fetch(url, options);
        const payload  = await response.json().catch(function () { return null; });
        return { response, payload };
    }

    async function fetchStatusesByIds(documentIds) {
        if (!Array.isArray(documentIds) || documentIds.length === 0) { return []; }
        const { response, payload } = await requestJson(`${statusesUrl}?ids=${documentIds.join(',')}`, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        }).catch(function () { return { response: null, payload: null }; });
        if (!response || !response.ok || !payload || !Array.isArray(payload.data)) { return []; }
        return payload.data;
    }

    async function uploadItem(item) {
        item.stage = 'uploading';
        item.message = '';
        renderQueue();
        updateSummary();
        const formData = new FormData();
        formData.append('file', item.file);
        if (batchCategory.value.trim() !== '') { formData.append('category', batchCategory.value.trim()); }
        const { response, payload } = await requestJson(storeUrl, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
            body: formData,
        });
        const docData = extractDocumentPayload(payload);
        if (!response.ok || !docData) {
            item.stage = 'upload_failed';
            item.message = extractRequestError(payload, labels.upload_failed);
            renderQueue();
            updateSummary();
            throw createRequestError(item.message, response.status);
        }
        updateItemFromDocument(item, docData);
        item.stage = 'uploaded';
        item.message = '';
        upsertKnowledgeTableRow(docData);
        renderQueue();
        updateSummary();
    }

    async function startDocumentProcessing(documentId, action) {
        const item = findItemByDocumentId(documentId);
        if (item) { item.stage = 'processing'; item.message = ''; renderQueue(); updateSummary(); }
        const urlTemplate = action === 'reprocess' ? reprocessUrlTemplate : processNowUrlTemplate;
        const url = urlTemplate.replace('__ID__', String(documentId));
        const { response, payload } = await requestJson(url, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
        });
        const docData = extractDocumentPayload(payload);
        if (docData) {
            upsertKnowledgeTableRow(docData);
            if (item) { updateItemFromDocument(item, docData); }
        }
        if (!response.ok) {
            const message = extractRequestError(payload, labels.failed);
            if (item) {
                if (!docData) { item.stage = 'uploaded'; }
                item.message = message;
            }
            renderQueue();
            updateSummary();
            throw createRequestError(message, response.status);
        }
        renderQueue();
        updateSummary();
        if (!docData || docData.status !== 'processing') { return docData; }
        let latestDocData = docData;

        while (!state.stopRequested) {
            await wait(1500);

            const fallback = await fetchStatusesByIds([documentId]);
            const currentDoc = Array.isArray(fallback) && fallback[0] ? fallback[0] : null;

            if (!currentDoc) { continue; }

            latestDocData = currentDoc;
            upsertKnowledgeTableRow(currentDoc);
            if (item) { updateItemFromDocument(item, currentDoc); }
            renderQueue();
            updateSummary();

            if (currentDoc.status !== 'processing') { return latestDocData; }
        }
        return latestDocData;
    }

    async function stopDocument(documentId) {
        const { response, payload } = await requestJson(stopUrlTemplate.replace('__ID__', String(documentId)), {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
        });
        const docData = extractDocumentPayload(payload);
        if (docData) {
            upsertKnowledgeTableRow(docData);
            const item = findItemByDocumentId(documentId);
            if (item) { updateItemFromDocument(item, docData); }
        }
        if (!response.ok) { throw createRequestError(extractRequestError(payload, labels.failed), response.status); }
        renderQueue();
        updateSummary();
    }

    async function deleteDocument(documentId) {
        if (!window.confirm(labels.delete_confirm)) { return; }
        const { response, payload } = await requestJson(destroyUrlTemplate.replace('__ID__', String(documentId)), {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
            body: new URLSearchParams({ _method: 'DELETE' }),
        });
        if (!response.ok) { throw createRequestError(extractRequestError(payload, labels.failed), response.status); }
        state.items = state.items.filter(function (item) { return item.documentId !== documentId; });
        removeKnowledgeTableRow(documentId);
        renderQueue();
        updateSummary();
    }

    async function uploadFiles(startAfterUpload) {
        if (state.isUploading) { return; }
        state.isUploading = true;
        state.stopRequested = false;
        ensureStatusPolling();
        updateSummary();
        try {
            while (true) {
                const nextItem = state.items.find(function (item) { return item.stage === 'waiting'; });
                if (!nextItem || state.stopRequested) { break; }
                try { await uploadItem(nextItem); } catch (error) { continue; }
                if (startAfterUpload && nextItem.documentId && !state.stopRequested) {
                    try {
                        await startDocumentProcessing(nextItem.documentId, 'start');
                    } catch (error) {
                        nextItem.message = error instanceof Error ? error.message : labels.failed;
                        if (error && error.status === 409) { state.stopRequested = true; }
                    }
                }
                renderQueue();
                updateSummary();
            }
        } finally {
            state.isUploading = false;
            updateSummary();
            stopStatusPollingIfIdle();
        }
    }

    async function startDocumentsSequentially(documentIds, action) {
        if (state.isProcessingQueue || documentIds.length === 0) { return; }
        state.isProcessingQueue = true;
        state.stopRequested = false;
        ensureStatusPolling();
        updateSummary();
        try {
            for (const documentId of documentIds) {
                if (state.stopRequested) { break; }
                try {
                    await startDocumentProcessing(documentId, action);
                } catch (error) {
                    const item = findItemByDocumentId(documentId);
                    if (item) {
                        item.message = error instanceof Error ? error.message : labels.failed;
                    } else {
                        showActionError(error);
                    }
                    if (error && error.status === 409) { state.stopRequested = true; }
                }
                renderQueue();
                updateSummary();
            }
        } finally {
            state.isProcessingQueue = false;
            updateSummary();
            stopStatusPollingIfIdle();
        }
    }

    async function requestBatchStop() {
        state.stopRequested = true;
        updateSummary();
        const processingIds = collectVisibleProcessingDocumentIds();
        if (processingIds.length > 0) {
            try { await stopDocument(processingIds[0]); } catch (error) { return; }
        }
    }

    function collectStartableVisibleDocumentIds() {
        return Array.from(tableBody.querySelectorAll('tr[data-knowledge-row][data-can-start="1"]'))
            .map(function (row) { return Number(row.getAttribute('data-knowledge-row')); })
            .filter(Boolean);
    }

    function collectVisibleProcessingDocumentIds() {
        return Array.from(tableBody.querySelectorAll('tr[data-knowledge-row][data-can-stop="1"]'))
            .map(function (row) { return Number(row.getAttribute('data-knowledge-row')); })
            .filter(Boolean);
    }

    function collectTrackedDocumentIds() {
        const ids = new Set();
        state.items.forEach(function (item) {
            if (item.documentId && ['uploaded','processing'].includes(item.stage)) { ids.add(item.documentId); }
        });
        tableBody.querySelectorAll('tr[data-knowledge-row]').forEach(function (row) {
            const status = row.getAttribute('data-status');
            const id     = Number(row.getAttribute('data-knowledge-row'));
            if (id && ['uploaded','processing'].includes(status || '')) { ids.add(id); }
        });
        return Array.from(ids);
    }

    function ensureStatusPolling() {
        if (state.pollTimer) { return; }
        state.pollTimer = window.setInterval(pollStatuses, 4000);
    }

    function stopStatusPollingIfIdle() {
        if (state.isUploading || state.isProcessingQueue || collectTrackedDocumentIds().length > 0) { return; }
        if (state.pollTimer) { window.clearInterval(state.pollTimer); state.pollTimer = null; }
    }

    async function pollStatuses() {
        const ids = collectTrackedDocumentIds();
        if (ids.length === 0) { stopStatusPollingIfIdle(); return; }
        const documents = await fetchStatusesByIds(ids);
        if (!Array.isArray(documents) || documents.length === 0) { stopStatusPollingIfIdle(); return; }
        documents.forEach(function (docData) {
            upsertKnowledgeTableRow(docData);
            const item = findItemByDocumentId(docData.id);
            if (item) { updateItemFromDocument(item, docData); }
        });
        renderQueue();
        updateSummary();
        stopStatusPollingIfIdle();
    }

    function removeLocalItem(localId) {
        state.items = state.items.filter(function (item) { return item.localId !== localId; });
        renderQueue();
        updateSummary();
    }

    function clearFinishedItems() {
        state.items = state.items.filter(function (item) { return !isTerminalStage(item.stage); });
        renderQueue();
        updateSummary();
    }

    async function handleQueueAction(action, id) {
        if (action === 'remove-local') { removeLocalItem(id); return; }
        const documentId = Number(id);
        if (!documentId) { return; }
        if (action === 'start' || action === 'reprocess') { await startDocumentsSequentially([documentId], action); return; }
        if (action === 'stop') {
            if (!window.confirm(labels.stop_confirm)) { return; }
            await stopDocument(documentId);
            return;
        }
        if (action === 'delete') { await deleteDocument(documentId); }
    }

    function bindStaticEvents() {
        openBtn?.addEventListener('click', function () {
            document.getElementById('knowledgeBatchUploader')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });

        selectBtn?.addEventListener('click', function (e) {
            e.stopPropagation();
            fileInput?.click();
        });

        dropzone?.addEventListener('click', function () { fileInput?.click(); });
        dropzone?.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); fileInput?.click(); }
        });

        ['dragenter','dragover'].forEach(function (eventName) {
            dropzone?.addEventListener(eventName, function (event) { event.preventDefault(); dropzone.classList.add('is-dragging'); });
        });
        ['dragleave','drop'].forEach(function (eventName) {
            dropzone?.addEventListener(eventName, function (event) { event.preventDefault(); dropzone.classList.remove('is-dragging'); });
        });
        dropzone?.addEventListener('drop', function (event) { addFiles(event.dataTransfer?.files || []); });
        fileInput?.addEventListener('change', function (event) { addFiles(event.target.files || []); fileInput.value = ''; });

        uploadOnlyBtn?.addEventListener('click', function () { uploadFiles(false); });
        uploadStartBtn?.addEventListener('click', function () { uploadFiles(true); });
        stopBatchBtn?.addEventListener('click', function () { requestBatchStop(); });
        clearBtn?.addEventListener('click', function () { clearFinishedItems(); });

        startVisibleBtn?.addEventListener('click', function () {
            startDocumentsSequentially(collectStartableVisibleDocumentIds(), 'start');
        });
        stopVisibleBtn?.addEventListener('click', async function () {
            if (!window.confirm(labels.stop_confirm)) { return; }
            for (const documentId of collectVisibleProcessingDocumentIds()) {
                try { await stopDocument(documentId); } catch (error) { showActionError(error); break; }
            }
        });

        [refreshBtn, refreshBtnSecondary].forEach(function (button) {
            button?.addEventListener('click', function () { ensureStatusPolling(); pollStatuses(); });
        });

        batchList?.addEventListener('click', function (event) {
            const button = event.target.closest('[data-action][data-id]');
            if (!button) { return; }
            handleQueueAction(button.getAttribute('data-action'), button.getAttribute('data-id')).catch(showActionError);
        });

        tableBody?.addEventListener('click', function (event) {
            const button = event.target.closest('.knowledge-doc-action');
            if (!button) { return; }
            handleQueueAction(button.getAttribute('data-action'), button.getAttribute('data-document-id')).catch(showActionError);
        });
    }

    bindStaticEvents();
    renderQueue();
    ensureTableEmptyState();
    updateSummary();

    if (collectTrackedDocumentIds().length > 0) {
        ensureStatusPolling();
        pollStatuses();
    }
})();
</script>
@endsection
