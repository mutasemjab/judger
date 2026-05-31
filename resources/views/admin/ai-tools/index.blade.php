@extends('layouts.admin')
@section('title', __('messages.ai_tools_history'))
@section('page_title', __('messages.ai_tools_history'))

@section('content')
<div class="page-header">
    <div>
        <h1>{{ __('messages.ai_tools_history') }}</h1>
        <nav aria-label="breadcrumb"><ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ __('messages.dashboard') }}</a></li>
            <li class="breadcrumb-item active">{{ __('messages.ai_tools_history') }}</li>
        </ol></nav>
    </div>
</div>

<form method="GET" action="{{ route('admin.ai-tools.index') }}" class="filter-bar">
    <select name="tool_type" class="form-select" style="max-width:220px;">
        <option value="">{{ __('messages.all') }} {{ __('messages.tool_type') }}</option>
        @foreach(['case_summarizer','document_summarizer','contract_analyzer','risk_estimator','memo_generator','legal_notice_generator','demand_letter_generator','timeline_generator','checklist_generator','client_explanation_simplifier','defense_assistant'] as $t)
        <option value="{{ $t }}" {{ request('tool_type')===$t?'selected':'' }}>{{ __('messages.'.$t) }}</option>
        @endforeach
    </select>
    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter me-1"></i>{{ __('messages.filter') }}</button>
    <a href="{{ route('admin.ai-tools.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('messages.reset') }}</a>
</form>

<div class="admin-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title"><i class="fas fa-robot"></i> {{ __('messages.ai_tools_history') }}</h3>
        <span class="badge bg-secondary">{{ $outputs->total() }} {{ __('messages.total') }}</span>
    </div>
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('messages.tool_type') }}</th>
                    <th>{{ __('messages.tool_user') }}</th>
                    <th>{{ __('messages.tool_case') }}</th>
                    <th>{{ __('messages.created_at') }}</th>
                    <th>{{ __('messages.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($outputs as $output)
                <tr>
                    <td class="text-muted-sm">{{ $output->id }}</td>
                    <td><span class="badge-status badge-analyzed">{{ __('messages.'.$output->tool_type?->value) }}</span></td>
                    <td class="text-muted-sm">{{ $output->user->name ?? '—' }}</td>
                    <td class="text-muted-sm">{{ $output->legalCase->title ?? '—' }}</td>
                    <td class="text-muted-sm">{{ $output->created_at->format('d M Y H:i') }}</td>
                    <td>
                        <button type="button" class="btn-action btn-action-view"
                                title="{{ __('messages.view_output') }}"
                                data-bs-toggle="modal"
                                data-bs-target="#outputModal{{ $output->id }}">
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6"><div class="empty-state"><i class="fas fa-robot"></i><p>{{ __('messages.no_data') }}</p></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($outputs->hasPages())
    <div class="admin-card-footer d-flex justify-content-center">{{ $outputs->appends(request()->query())->links() }}</div>
    @endif
</div>

{{-- Output Modals --}}
@foreach($outputs as $output)
<div class="modal fade" id="outputModal{{ $output->id }}" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border-radius:16px;">
            <div class="modal-header">
                <h5 class="modal-title fw-600"><i class="fas fa-robot me-2 text-primary"></i>{{ __('messages.'.$output->tool_type?->value) }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="output-box">{{ $output->content }}</div>
                @if($output->disclaimer)
                <div class="flash-alert alert-warning mt-3" style="background:var(--warning-soft);border:1px solid rgba(245,158,11,0.3);color:#92400e;border-radius:10px;padding:10px 14px;font-size:0.82rem;">
                    <i class="fas fa-triangle-exclamation me-2"></i>{{ $output->disclaimer }}
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endforeach
@endsection
