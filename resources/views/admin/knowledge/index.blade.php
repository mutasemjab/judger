@extends('layouts.admin')
@section('title', __('messages.knowledge_base'))
@section('page_title', __('messages.knowledge_base'))

@php
    $maxUploadMb = (int) ceil(\App\Models\KnowledgeDocument::MAX_UPLOAD_SIZE_KB / 1024);
@endphp

@section('content')
<div class="page-header">
    <div>
        <h1>{{ __('messages.knowledge_base') }}</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ __('messages.dashboard') }}</a></li>
                <li class="breadcrumb-item active">{{ __('messages.knowledge_base') }}</li>
            </ol>
        </nav>
    </div>
    <button class="btn btn-primary btn-sm" type="button" id="openBatchUploader">
        <i class="fas fa-upload me-1"></i>{{ __('messages.batch_uploader') }}
    </button>
</div>

<div class="row g-3 mb-4">
    <div class="col-12 col-md-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon-wrap bg-primary-soft"><i class="fas fa-book"></i></div>
            <div>
                <div class="stat-number">{{ number_format($stats['total']) }}</div>
                <div class="stat-label">{{ __('messages.total') }} {{ __('messages.knowledge_base') }}</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon-wrap bg-purple-soft"><i class="fas fa-inbox"></i></div>
            <div>
                <div class="stat-number">{{ number_format($stats['uploaded']) }}</div>
                <div class="stat-label">{{ __('messages.ready_to_upload') }}</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon-wrap bg-info-soft"><i class="fas fa-gears"></i></div>
            <div>
                <div class="stat-number">{{ number_format($stats['processing']) }}</div>
                <div class="stat-label">{{ __('messages.processing') }}</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon-wrap bg-success-soft"><i class="fas fa-check-double"></i></div>
            <div>
                <div class="stat-number">{{ number_format($stats['processed']) }}</div>
                <div class="stat-label">{{ __('messages.indexed_documents') }}</div>
            </div>
        </div>
    </div>
</div>

<div class="admin-card knowledge-batch-card mb-4" id="knowledgeBatchUploader">
    <div class="admin-card-header">
        <div>
            <h3 class="admin-card-title"><i class="fas fa-layer-group"></i> {{ __('messages.batch_uploader') }}</h3>
            <div class="knowledge-batch-subtitle">{{ __('messages.batch_uploader_hint') }}</div>
        </div>
        <span class="badge bg-secondary" id="knowledgeBatchSelectedCount">0 {{ __('messages.total') }}</span>
    </div>
    <div class="admin-card-body">
        <div class="knowledge-batch-layout">
            <div class="knowledge-batch-panel">
                <div class="knowledge-dropzone" id="knowledgeDropzone" tabindex="0" role="button" aria-label="{{ __('messages.select_files') }}">
                    <input type="file" id="knowledgeFiles" class="d-none" accept=".pdf,.doc,.docx,.pptx,.txt" multiple>
                    <div class="knowledge-dropzone-icon"><i class="fas fa-cloud-arrow-up"></i></div>
                    <h4>{{ __('messages.drag_drop_knowledge') }}</h4>
                    <p>{{ __('messages.upload_one_by_one_hint') }}</p>
                    <button type="button" class="btn btn-primary btn-sm" id="selectKnowledgeFilesBtn">
                        <i class="fas fa-folder-open me-1"></i>{{ __('messages.select_files') }}
                    </button>
                    <div class="knowledge-dropzone-note">
                        PDF, DOC, DOCX, PPTX, TXT · {{ $maxUploadMb }}MB max / file
                    </div>
                </div>

                <div class="knowledge-batch-fields">
                    <div>
                        <label class="form-label">{{ __('messages.document_category') }}</label>
                        <input type="text" class="form-control" id="knowledgeBatchCategory" placeholder="civil_law, procedure, general...">
                        <div class="knowledge-field-help">{{ __('messages.batch_category_hint') }}</div>
                    </div>
                    <div class="knowledge-field-help knowledge-field-help-inline">
                        <i class="fas fa-wand-magic-sparkles"></i>
                        <span>{{ __('messages.auto_titles_from_filename') }}</span>
                    </div>
                </div>

                <div class="knowledge-batch-actions">
                    <button type="button" class="btn btn-primary btn-sm" id="startKnowledgeBatch" disabled>
                        <i class="fas fa-play me-1"></i>{{ __('messages.start_batch_upload') }}
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="clearKnowledgeBatch" disabled>
                        <i class="fas fa-broom me-1"></i>{{ __('messages.clear_finished') }}
                    </button>
                </div>
            </div>

            <div class="knowledge-batch-panel knowledge-batch-summary-panel">
                <div class="knowledge-progress-card">
                    <div class="knowledge-progress-header">
                        <span>{{ __('messages.batch_progress') }}</span>
                        <span id="knowledgeBatchActivity">0 / 0</span>
                    </div>
                    <div class="knowledge-progress-bar">
                        <span id="knowledgeBatchProgressBar"></span>
                    </div>
                    <div class="knowledge-progress-note" id="knowledgeBatchProgressNote">{{ __('messages.no_files_selected') }}</div>
                </div>

                <div class="knowledge-summary-grid">
                    <div class="knowledge-summary-tile">
                        <span class="knowledge-summary-label">{{ __('messages.total') }}</span>
                        <strong id="summaryTotal">0</strong>
                    </div>
                    <div class="knowledge-summary-tile">
                        <span class="knowledge-summary-label">{{ __('messages.ready_to_upload') }}</span>
                        <strong id="summaryWaiting">0</strong>
                    </div>
                    <div class="knowledge-summary-tile">
                        <span class="knowledge-summary-label">{{ __('messages.queued_for_indexing') }}</span>
                        <strong id="summaryQueued">0</strong>
                    </div>
                    <div class="knowledge-summary-tile">
                        <span class="knowledge-summary-label">{{ __('messages.processing') }}</span>
                        <strong id="summaryProcessing">0</strong>
                    </div>
                    <div class="knowledge-summary-tile">
                        <span class="knowledge-summary-label">{{ __('messages.indexed_documents') }}</span>
                        <strong id="summaryProcessed">0</strong>
                    </div>
                    <div class="knowledge-summary-tile">
                        <span class="knowledge-summary-label">{{ __('messages.issues') }}</span>
                        <strong id="summaryFailed">0</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="knowledge-queue-wrap">
            <div class="knowledge-queue-header">
                <h4>{{ __('messages.upload_queue') }}</h4>
                <span class="text-muted-sm">{{ __('messages.upload_one_by_one_hint') }}</span>
            </div>
            <div class="knowledge-queue-list" id="knowledgeBatchList">
                <div class="knowledge-queue-empty">
                    <i class="fas fa-file-circle-plus"></i>
                    <p>{{ __('messages.no_files_selected') }}</p>
                </div>
            </div>
        </div>
    </div>
</div>

<form method="GET" action="{{ route('admin.knowledge.index') }}" class="filter-bar">
    <select name="status" class="form-select" style="max-width:160px;">
        <option value="">{{ __('messages.all') }} {{ __('messages.status') }}</option>
        @foreach(['uploaded','processing','processed','failed'] as $status)
            <option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>{{ __('messages.' . $status) }}</option>
        @endforeach
    </select>
    <input type="text" name="category" class="form-control" value="{{ request('category') }}" placeholder="{{ __('messages.document_category') }}..." style="max-width:180px;">
    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter me-1"></i>{{ __('messages.filter') }}</button>
    <a href="{{ route('admin.knowledge.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('messages.reset') }}</a>
</form>

<div class="admin-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title"><i class="fas fa-book-open"></i> {{ __('messages.knowledge_base') }}</h3>
        <span class="badge bg-secondary" id="knowledgeDocumentsTableCount">{{ $documents->total() }} {{ __('messages.total') }}</span>
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
            <tbody id="knowledgeDocumentsTableBody">
                @forelse($documents as $doc)
                    @php $statusValue = $doc->status?->value; @endphp
                    <tr id="knowledge-doc-row-{{ $doc->id }}" data-knowledge-row="{{ $doc->id }}" data-status="{{ $statusValue }}" data-category="{{ strtolower((string) $doc->category) }}">
                        <td class="text-muted-sm">{{ $doc->id }}</td>
                        <td>
                            <div class="fw-600">{{ $doc->title }}</div>
                            <div class="text-muted-sm">{{ $doc->original_name }}</div>
                        </td>
                        <td class="text-muted-sm">{{ $doc->category ?? '—' }}</td>
                        <td>
                            @if($statusValue === 'processed')
                                <span class="badge-status badge-analyzed">{{ __('messages.indexed') }}</span>
                            @else
                                <span class="badge-status badge-{{ $statusValue }}">{{ __('messages.' . $statusValue) }}</span>
                            @endif
                            @if($statusValue === 'failed' && $doc->processing_error)
                                <div class="table-status-note text-danger">{{ $doc->processing_error }}</div>
                            @endif
                        </td>
                        <td class="text-muted-sm">{{ number_format($doc->qdrant_points_count) }}</td>
                        <td class="text-muted-sm">{{ $doc->uploadedBy->name ?? '—' }}</td>
                        <td class="text-muted-sm">{{ $doc->processed_at?->format('d M Y · H:i') ?? '—' }}</td>
                        <td>
                            <div class="d-flex gap-1">
                                <form method="POST" action="{{ route('admin.knowledge.reprocess', $doc) }}">
                                    @csrf
                                    <button type="submit" class="btn-action btn-action-sync" title="{{ __('messages.reprocess_doc') }}">
                                        <i class="fas fa-rotate"></i>
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.knowledge.destroy', $doc) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-action btn-action-delete" data-confirm="{{ __('messages.confirm_delete') }}" title="{{ __('messages.delete') }}">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr data-empty-state="true">
                        <td colspan="8">
                            <div class="empty-state">
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
        <div class="admin-card-footer d-flex justify-content-center">{{ $documents->appends(request()->query())->links() }}</div>
    @endif
</div>
@endsection

@section('script')
<script>
(function () {
    const fileInput = document.getElementById('knowledgeFiles');
    const dropzone = document.getElementById('knowledgeDropzone');
    const selectBtn = document.getElementById('selectKnowledgeFilesBtn');
    const openBtn = document.getElementById('openBatchUploader');
    const startBtn = document.getElementById('startKnowledgeBatch');
    const clearBtn = document.getElementById('clearKnowledgeBatch');
    const batchList = document.getElementById('knowledgeBatchList');
    const batchCategory = document.getElementById('knowledgeBatchCategory');
    const progressBar = document.getElementById('knowledgeBatchProgressBar');
    const progressNote = document.getElementById('knowledgeBatchProgressNote');
    const batchActivity = document.getElementById('knowledgeBatchActivity');
    const selectedCount = document.getElementById('knowledgeBatchSelectedCount');
    const summaryTotal = document.getElementById('summaryTotal');
    const summaryWaiting = document.getElementById('summaryWaiting');
    const summaryQueued = document.getElementById('summaryQueued');
    const summaryProcessing = document.getElementById('summaryProcessing');
    const summaryProcessed = document.getElementById('summaryProcessed');
    const summaryFailed = document.getElementById('summaryFailed');
    const tableBody = document.getElementById('knowledgeDocumentsTableBody');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    const storeUrl = @json(route('admin.knowledge.store'));
    const statusesUrl = @json(route('admin.knowledge.statuses'));
    const knowledgeBaseUrl = @json(route('admin.knowledge.index'));
    const activeFilters = {
        status: @json((string) request('status')),
        category: @json(strtolower((string) request('category'))),
    };

    const labels = {
        waiting: @json(__('messages.waiting')),
        uploading: @json(__('messages.uploading_now')),
        upload_failed: @json(__('messages.upload_failed')),
        uploaded: @json(__('messages.uploaded')),
        processing: @json(__('messages.processing')),
        processed: @json(__('messages.indexed')),
        failed: @json(__('messages.failed')),
    };

    const state = {
        items: [],
        isUploading: false,
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
        if (!bytes) {
            return '0 B';
        }

        const units = ['B', 'KB', 'MB', 'GB'];
        const index = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
        const value = bytes / Math.pow(1024, index);

        return `${value.toFixed(value >= 10 || index === 0 ? 0 : 1)} ${units[index]}`;
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
            qdrantPoints: 0,
            processedAt: null,
            category: '',
        };
    }

    function addFiles(fileList) {
        const incomingFiles = Array.from(fileList || []);

        incomingFiles.forEach(function (file) {
            const signature = [file.name, file.size, file.lastModified].join(':');
            const exists = state.items.some(function (item) {
                return item.signature === signature;
            });

            if (!exists) {
                state.items.push(createItem(file));
            }
        });

        renderQueue();
        updateSummary();
    }

    function queueBadgeClass(stage) {
        switch (stage) {
            case 'waiting':
                return 'badge-pending';
            case 'uploading':
                return 'badge-processing';
            case 'uploaded':
                return 'badge-uploaded';
            case 'processing':
                return 'badge-processing';
            case 'processed':
                return 'badge-analyzed';
            case 'failed':
            case 'upload_failed':
                return 'badge-failed';
            default:
                return 'badge-inactive';
        }
    }

    function queueBadgeLabel(stage) {
        return labels[stage] || stage;
    }

    function isTerminalStage(stage) {
        return stage === 'processed' || stage === 'failed' || stage === 'upload_failed';
    }

    function renderQueue() {
        if (state.items.length === 0) {
            batchList.innerHTML = `
                <div class="knowledge-queue-empty">
                    <i class="fas fa-file-circle-plus"></i>
                    <p>${escapeHtml(@json(__('messages.no_files_selected')))}</p>
                </div>
            `;

            return;
        }

        batchList.innerHTML = state.items.map(function (item) {
            const message = item.message ? `<div class="knowledge-batch-item-message">${escapeHtml(item.message)}</div>` : '';
            const metaBits = [
                item.title,
                formatBytes(item.size),
                item.documentId ? `#${item.documentId}` : null,
                item.qdrantPoints ? `${item.qdrantPoints} chunks` : null,
            ].filter(Boolean);

            return `
                <div class="knowledge-batch-item">
                    <div class="knowledge-batch-item-main">
                        <div class="knowledge-batch-item-name">${escapeHtml(item.originalName)}</div>
                        <div class="knowledge-batch-item-meta">${metaBits.map(escapeHtml).join(' · ')}</div>
                        ${message}
                    </div>
                    <div class="knowledge-batch-item-side">
                        <span class="badge-status ${queueBadgeClass(item.stage)}">${escapeHtml(queueBadgeLabel(item.stage))}</span>
                        <span class="knowledge-batch-item-time">${item.processedAt ? escapeHtml(formatDate(item.processedAt)) : ''}</span>
                    </div>
                </div>
            `;
        }).join('');
    }

    function updateSummary() {
        const total = state.items.length;
        const waiting = state.items.filter(function (item) { return item.stage === 'waiting'; }).length;
        const queued = state.items.filter(function (item) { return item.stage === 'uploaded'; }).length;
        const processing = state.items.filter(function (item) { return item.stage === 'processing' || item.stage === 'uploading'; }).length;
        const processed = state.items.filter(function (item) { return item.stage === 'processed'; }).length;
        const failed = state.items.filter(function (item) { return item.stage === 'failed' || item.stage === 'upload_failed'; }).length;
        const finished = processed + failed;
        const progress = total > 0 ? Math.round((finished / total) * 100) : 0;

        selectedCount.textContent = `${total} ${@json(__('messages.total'))}`;
        summaryTotal.textContent = total;
        summaryWaiting.textContent = waiting;
        summaryQueued.textContent = queued;
        summaryProcessing.textContent = processing;
        summaryProcessed.textContent = processed;
        summaryFailed.textContent = failed;
        batchActivity.textContent = `${finished} / ${total}`;
        progressBar.style.width = `${progress}%`;
        progressNote.textContent = total === 0
            ? @json(__('messages.no_files_selected'))
            : `${processed} indexed · ${queued + processing} active · ${failed} issues`;

        startBtn.disabled = waiting === 0 || state.isUploading;
        clearBtn.disabled = finished === 0;
    }

    async function startBatchUpload() {
        if (state.isUploading) {
            return;
        }

        const nextItem = state.items.find(function (item) {
            return item.stage === 'waiting';
        });

        if (!nextItem) {
            return;
        }

        state.isUploading = true;
        startBtn.disabled = true;
        ensureStatusPolling();

        while (true) {
            const item = state.items.find(function (candidate) {
                return candidate.stage === 'waiting';
            });

            if (!item) {
                break;
            }

            item.stage = 'uploading';
            item.message = '';
            renderQueue();
            updateSummary();

            const formData = new FormData();
            formData.append('file', item.file);

            if (batchCategory.value.trim() !== '') {
                formData.append('category', batchCategory.value.trim());
            }

            try {
                const response = await fetch(storeUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: formData,
                });

                const payload = await response.json().catch(function () { return null; });

                if (!response.ok || !payload) {
                    throw new Error(extractRequestError(payload));
                }

                updateItemFromDocument(item, payload);
                upsertKnowledgeTableRow(payload);
            } catch (error) {
                item.stage = 'upload_failed';
                item.message = error instanceof Error ? error.message : @json(__('messages.upload_failed'));
            }

            renderQueue();
            updateSummary();
        }

        state.isUploading = false;
        updateSummary();
    }

    function updateItemFromDocument(item, docData) {
        item.documentId = docData.id;
        item.title = docData.title || item.title;
        item.category = docData.category || '';
        item.stage = docData.status || 'uploaded';
        item.qdrantPoints = Number(docData.qdrant_points_count || 0);
        item.processedAt = docData.processed_at || null;
        item.message = docData.processing_error || '';
    }

    function extractRequestError(payload) {
        if (payload && payload.errors) {
            const firstKey = Object.keys(payload.errors)[0];

            if (firstKey && Array.isArray(payload.errors[firstKey]) && payload.errors[firstKey][0]) {
                return payload.errors[firstKey][0];
            }
        }

        if (payload && payload.message) {
            return payload.message;
        }

        return @json(__('messages.upload_failed'));
    }

    function ensureStatusPolling() {
        if (state.pollTimer) {
            return;
        }

        state.pollTimer = window.setInterval(pollStatuses, 4000);
    }

    function stopStatusPollingIfIdle() {
        const hasPendingServerWork = state.items.some(function (item) {
            return item.documentId && (item.stage === 'uploaded' || item.stage === 'processing');
        });

        if (!state.isUploading && !hasPendingServerWork && state.pollTimer) {
            window.clearInterval(state.pollTimer);
            state.pollTimer = null;
        }
    }

    async function pollStatuses() {
        const ids = state.items
            .filter(function (item) {
                return item.documentId && (item.stage === 'uploaded' || item.stage === 'processing');
            })
            .map(function (item) {
                return item.documentId;
            });

        if (ids.length === 0) {
            stopStatusPollingIfIdle();
            return;
        }

        try {
            const response = await fetch(`${statusesUrl}?ids=${ids.join(',')}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                return;
            }

            const payload = await response.json();
            const documents = Array.isArray(payload.data) ? payload.data : [];

            documents.forEach(function (docData) {
                const item = state.items.find(function (candidate) {
                    return candidate.documentId === docData.id;
                });

                if (!item) {
                    return;
                }

                updateItemFromDocument(item, docData);
                upsertKnowledgeTableRow(docData);
            });
        } catch (error) {
            return;
        } finally {
            renderQueue();
            updateSummary();
            stopStatusPollingIfIdle();
        }
    }

    function formatDate(value) {
        const date = new Date(value);

        if (Number.isNaN(date.getTime())) {
            return '—';
        }

        return date.toLocaleString(document.documentElement.lang || 'en', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    function matchesActiveFilters(docData) {
        if (activeFilters.status && activeFilters.status !== docData.status) {
            return false;
        }

        if (activeFilters.category && activeFilters.category !== String(docData.category || '').toLowerCase()) {
            return false;
        }

        return true;
    }

    function tableStatusBadge(docData) {
        if (docData.status === 'processed') {
            return `<span class="badge-status badge-analyzed">${escapeHtml(labels.processed)}</span>`;
        }

        return `<span class="badge-status ${queueBadgeClass(docData.status)}">${escapeHtml(queueBadgeLabel(docData.status))}</span>`;
    }

    function buildKnowledgeTableRow(docData) {
        const reprocessAction = `${knowledgeBaseUrl.replace(/\/$/, '')}/${docData.id}/reprocess`;
        const destroyAction = `${knowledgeBaseUrl.replace(/\/$/, '')}/${docData.id}`;
        const uploadedBy = docData.uploaded_by && docData.uploaded_by.name ? docData.uploaded_by.name : '—';
        const errorNote = docData.status === 'failed' && docData.processing_error
            ? `<div class="table-status-note text-danger">${escapeHtml(docData.processing_error)}</div>`
            : '';

        return `
            <tr id="knowledge-doc-row-${docData.id}" data-knowledge-row="${docData.id}" data-status="${escapeHtml(docData.status)}" data-category="${escapeHtml(String(docData.category || '').toLowerCase())}">
                <td class="text-muted-sm">${docData.id}</td>
                <td>
                    <div class="fw-600">${escapeHtml(docData.title || '')}</div>
                    <div class="text-muted-sm">${escapeHtml(docData.original_name || '')}</div>
                </td>
                <td class="text-muted-sm">${escapeHtml(docData.category || '—')}</td>
                <td>
                    ${tableStatusBadge(docData)}
                    ${errorNote}
                </td>
                <td class="text-muted-sm">${Number(docData.qdrant_points_count || 0).toLocaleString()}</td>
                <td class="text-muted-sm">${escapeHtml(uploadedBy)}</td>
                <td class="text-muted-sm">${escapeHtml(docData.processed_at ? formatDate(docData.processed_at) : '—')}</td>
                <td>
                    <div class="d-flex gap-1">
                        <form method="POST" action="${escapeHtml(reprocessAction)}">
                            <input type="hidden" name="_token" value="${escapeHtml(csrfToken)}">
                            <button type="submit" class="btn-action btn-action-sync" title="${escapeHtml(@json(__('messages.reprocess_doc')))}">
                                <i class="fas fa-rotate"></i>
                            </button>
                        </form>
                        <form method="POST" action="${escapeHtml(destroyAction)}">
                            <input type="hidden" name="_token" value="${escapeHtml(csrfToken)}">
                            <input type="hidden" name="_method" value="DELETE">
                            <button type="submit" class="btn-action btn-action-delete" data-confirm="${escapeHtml(@json(__('messages.confirm_delete')))}" title="${escapeHtml(@json(__('messages.delete')))}">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
        `;
    }

    function bindConfirmHandler(root) {
        if (!root) {
            return;
        }

        root.querySelectorAll('[data-confirm]').forEach(function (button) {
            if (button.dataset.confirmBound === 'true') {
                return;
            }

            button.dataset.confirmBound = 'true';
            button.addEventListener('click', function (event) {
                if (!confirm(button.dataset.confirm)) {
                    event.preventDefault();
                }
            });
        });
    }

    function ensureKnowledgeTableEmptyState() {
        if (tableBody.querySelector('[data-knowledge-row]')) {
            return;
        }

        if (tableBody.querySelector('[data-empty-state="true"]')) {
            return;
        }

        tableBody.insertAdjacentHTML('afterbegin', `
            <tr data-empty-state="true">
                <td colspan="8">
                    <div class="empty-state">
                        <i class="fas fa-book-open"></i>
                        <p>${escapeHtml(@json(__('messages.no_data')))}</p>
                    </div>
                </td>
            </tr>
        `);
    }

    function upsertKnowledgeTableRow(docData) {
        const existingRow = window.document.getElementById(`knowledge-doc-row-${docData.id}`);

        if (!matchesActiveFilters(docData)) {
            if (existingRow) {
                existingRow.remove();
                ensureKnowledgeTableEmptyState();
            }

            return;
        }

        const html = buildKnowledgeTableRow(docData);

        if (existingRow) {
            existingRow.outerHTML = html;
            bindConfirmHandler(window.document.getElementById(`knowledge-doc-row-${docData.id}`));
            return;
        }

        const emptyState = tableBody.querySelector('[data-empty-state="true"]');

        if (emptyState) {
            emptyState.remove();
        }

        tableBody.insertAdjacentHTML('afterbegin', html);
        bindConfirmHandler(window.document.getElementById(`knowledge-doc-row-${docData.id}`));
    }

    function clearFinishedItems() {
        state.items = state.items.filter(function (item) {
            return !isTerminalStage(item.stage);
        });

        renderQueue();
        updateSummary();
        stopStatusPollingIfIdle();
    }

    selectBtn.addEventListener('click', function () {
        fileInput.click();
    });

    openBtn.addEventListener('click', function () {
        document.getElementById('knowledgeBatchUploader').scrollIntoView({ behavior: 'smooth', block: 'start' });
        window.setTimeout(function () {
            fileInput.click();
        }, 250);
    });

    fileInput.addEventListener('change', function (event) {
        addFiles(event.target.files);
        event.target.value = '';
    });

    startBtn.addEventListener('click', startBatchUpload);
    clearBtn.addEventListener('click', clearFinishedItems);

    ['dragenter', 'dragover'].forEach(function (eventName) {
        dropzone.addEventListener(eventName, function (event) {
            event.preventDefault();
            dropzone.classList.add('is-dragging');
        });
    });

    ['dragleave', 'dragend', 'drop'].forEach(function (eventName) {
        dropzone.addEventListener(eventName, function (event) {
            event.preventDefault();
            dropzone.classList.remove('is-dragging');
        });
    });

    dropzone.addEventListener('drop', function (event) {
        addFiles(event.dataTransfer?.files || []);
    });

    dropzone.addEventListener('click', function (event) {
        if (selectBtn.contains(event.target)) {
            return;
        }

        fileInput.click();
    });

    dropzone.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            fileInput.click();
        }
    });

    bindConfirmHandler(document);
    updateSummary();
})();
</script>
@endsection
