@extends('layouts.admin')
@section('title', __('messages.templates'))
@section('page_title', __('messages.templates'))

@section('content')
<div class="page-header">
    <div>
        <h1>{{ __('messages.templates') }}</h1>
        <nav aria-label="breadcrumb"><ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ __('messages.dashboard') }}</a></li>
            <li class="breadcrumb-item active">{{ __('messages.templates') }}</li>
        </ol></nav>
    </div>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createModal">
        <i class="fas fa-plus me-1"></i>{{ __('messages.add_template') }}
    </button>
</div>

<form method="GET" action="{{ route('admin.templates.index') }}" class="filter-bar">
    <input type="text" name="search" class="form-control" value="{{ request('search') }}" placeholder="{{ __('messages.search') }}..." style="max-width:220px;">
    <select name="category_id" class="form-select" style="max-width:200px;">
        <option value="">{{ __('messages.all') }} {{ __('messages.template_category') }}</option>
        @foreach($categories as $cat)
        <option value="{{ $cat->id }}" {{ request('category_id')==$cat->id?'selected':'' }}>{{ $cat->name }}</option>
        @endforeach
    </select>
    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter me-1"></i>{{ __('messages.filter') }}</button>
    <a href="{{ route('admin.templates.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('messages.reset') }}</a>
</form>

<div class="admin-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title"><i class="fas fa-file-contract"></i> {{ __('messages.templates') }}</h3>
        <span class="badge bg-secondary">{{ $templates->total() }} {{ __('messages.total') }}</span>
    </div>
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('messages.template_title') }}</th>
                    <th>{{ __('messages.template_category') }}</th>
                    <th>{{ __('messages.template_variables') }}</th>
                    <th>{{ __('messages.is_active') }}</th>
                    <th>{{ __('messages.created_at') }}</th>
                    <th>{{ __('messages.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($templates as $tpl)
                <tr>
                    <td class="text-muted-sm">{{ $tpl->id }}</td>
                    <td>
                        <div class="fw-600">{{ $tpl->title }}</div>
                        @if($tpl->description)<div class="text-muted-sm">{{ Str::limit($tpl->description,60) }}</div>@endif
                    </td>
                    <td class="text-muted-sm">{{ $tpl->category->name ?? '—' }}</td>
                    <td class="text-muted-sm">{{ $tpl->variables ? count($tpl->variables) . ' vars' : '—' }}</td>
                    <td>
                        <span class="badge-status {{ $tpl->is_active ? 'badge-analyzed' : 'badge-inactive' }}">
                            {{ $tpl->is_active ? __('messages.active') : __('messages.inactive') }}
                        </span>
                    </td>
                    <td class="text-muted-sm">{{ $tpl->created_at->format('d M Y') }}</td>
                    <td>
                        <div class="d-flex gap-1">
                            <button type="button" class="btn-action btn-action-edit"
                                    data-bs-toggle="modal" data-bs-target="#editModal{{ $tpl->id }}"
                                    title="{{ __('messages.edit') }}">
                                <i class="fas fa-pen"></i>
                            </button>
                            <form method="POST" action="{{ route('admin.templates.destroy', $tpl) }}">
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
                <tr><td colspan="7"><div class="empty-state"><i class="fas fa-file-contract"></i><p>{{ __('messages.no_data') }}</p></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($templates->hasPages())
    <div class="admin-card-footer d-flex justify-content-center">{{ $templates->appends(request()->query())->links() }}</div>
    @endif
</div>

{{-- Create Modal --}}
<div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border-radius:16px;">
            <div class="modal-header"><h5 class="modal-title fw-600"><i class="fas fa-plus me-2 text-primary"></i>{{ __('messages.add_template') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST" action="{{ route('admin.templates.store') }}" class="admin-form">
                @csrf
                <div class="modal-body" style="display:flex;flex-direction:column;gap:14px;">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">{{ __('messages.template_title') }} *</label>
                            <input type="text" name="title" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ __('messages.template_category') }} *</label>
                            <select name="template_category_id" class="form-select" required>
                                <option value="">— {{ __('messages.template_category') }} —</option>
                                @foreach($categories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="form-label">{{ __('messages.log_description') }}</label>
                        <input type="text" name="description" class="form-control">
                    </div>
                    <div>
                        <label class="form-label">{{ __('messages.template_content') }} * <small class="text-muted-sm">(use {{variable_name}} for placeholders)</small></label>
                        <textarea name="content" class="form-control" rows="8" required></textarea>
                    </div>
                    <div>
                        <label class="form-label">{{ __('messages.template_variables') }} <small class="text-muted-sm">(comma separated)</small></label>
                        <input type="text" name="variables_raw" class="form-control" placeholder="client_name, case_number, date, court">
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="is_active" value="1" id="is_active_create" class="form-check-input" checked>
                        <label class="form-check-label" for="is_active_create">{{ __('messages.is_active') }}</label>
                    </div>
                </div>
                <div class="modal-footer" style="border-top:1px solid var(--card-border);">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">{{ __('messages.cancel') }}</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save me-1"></i>{{ __('messages.save') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Edit Modals --}}
@foreach($templates as $tpl)
<div class="modal fade" id="editModal{{ $tpl->id }}" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border-radius:16px;">
            <div class="modal-header"><h5 class="modal-title fw-600"><i class="fas fa-pen me-2 text-warning"></i>{{ __('messages.edit_template') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST" action="{{ route('admin.templates.update', $tpl) }}" class="admin-form">
                @csrf @method('PUT')
                <div class="modal-body" style="display:flex;flex-direction:column;gap:14px;">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">{{ __('messages.template_title') }} *</label>
                            <input type="text" name="title" class="form-control" value="{{ $tpl->title }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ __('messages.template_category') }} *</label>
                            <select name="template_category_id" class="form-select" required>
                                @foreach($categories as $cat)
                                <option value="{{ $cat->id }}" {{ $tpl->template_category_id==$cat->id?'selected':'' }}>{{ $cat->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="form-label">{{ __('messages.log_description') }}</label>
                        <input type="text" name="description" class="form-control" value="{{ $tpl->description }}">
                    </div>
                    <div>
                        <label class="form-label">{{ __('messages.template_content') }} *</label>
                        <textarea name="content" class="form-control" rows="8" required>{{ $tpl->content }}</textarea>
                    </div>
                    <div>
                        <label class="form-label">{{ __('messages.template_variables') }} <small class="text-muted-sm">(comma separated)</small></label>
                        <input type="text" name="variables_raw" class="form-control" value="{{ $tpl->variables ? implode(', ', $tpl->variables) : '' }}">
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="is_active" value="1" id="is_active_{{ $tpl->id }}" class="form-check-input" {{ $tpl->is_active?'checked':'' }}>
                        <label class="form-check-label" for="is_active_{{ $tpl->id }}">{{ __('messages.is_active') }}</label>
                    </div>
                </div>
                <div class="modal-footer" style="border-top:1px solid var(--card-border);">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">{{ __('messages.cancel') }}</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save me-1"></i>{{ __('messages.save') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach
@endsection
