@extends('layouts.admin')
@section('title', __('messages.template_categories'))
@section('page_title', __('messages.template_categories'))

@section('content')
<div class="page-header">
    <div>
        <h1>{{ __('messages.template_categories') }}</h1>
        <nav aria-label="breadcrumb"><ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ __('messages.dashboard') }}</a></li>
            <li class="breadcrumb-item active">{{ __('messages.template_categories') }}</li>
        </ol></nav>
    </div>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createCatModal">
        <i class="fas fa-plus me-1"></i>{{ __('messages.add_category') }}
    </button>
</div>

<div class="admin-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title"><i class="fas fa-folder-open"></i> {{ __('messages.template_categories') }}</h3>
    </div>
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('messages.category_name') }}</th>
                    <th>{{ __('messages.category_slug') }}</th>
                    <th>{{ __('messages.log_description') }}</th>
                    <th>{{ __('messages.templates') }}</th>
                    <th>{{ __('messages.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($categories as $cat)
                <tr>
                    <td class="text-muted-sm">{{ $cat->id }}</td>
                    <td class="fw-600">{{ $cat->name }}</td>
                    <td class="text-muted-sm"><code>{{ $cat->slug }}</code></td>
                    <td class="text-muted-sm">{{ $cat->description ?? '—' }}</td>
                    <td class="text-muted-sm">{{ $cat->templates_count }}</td>
                    <td>
                        <button type="button" class="btn-action btn-action-edit"
                                data-bs-toggle="modal" data-bs-target="#editCatModal{{ $cat->id }}"
                                title="{{ __('messages.edit') }}">
                            <i class="fas fa-pen"></i>
                        </button>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6"><div class="empty-state"><i class="fas fa-folder-open"></i><p>{{ __('messages.no_data') }}</p></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Create modal --}}
<div class="modal fade" id="createCatModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius:16px;">
            <div class="modal-header"><h5 class="modal-title fw-600"><i class="fas fa-plus me-2 text-primary"></i>{{ __('messages.add_category') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST" action="{{ route('admin.template-categories.store') }}" class="admin-form">
                @csrf
                <div class="modal-body" style="display:flex;flex-direction:column;gap:14px;">
                    <div><label class="form-label">{{ __('messages.category_name') }} *</label><input type="text" name="name" class="form-control" required></div>
                    <div><label class="form-label">{{ __('messages.category_slug') }} *</label><input type="text" name="slug" class="form-control" required placeholder="contracts, legal-notices..."></div>
                    <div><label class="form-label">{{ __('messages.log_description') }}</label><input type="text" name="description" class="form-control"></div>
                </div>
                <div class="modal-footer" style="border-top:1px solid var(--card-border);">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">{{ __('messages.cancel') }}</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save me-1"></i>{{ __('messages.save') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Edit modals --}}
@foreach($categories as $cat)
<div class="modal fade" id="editCatModal{{ $cat->id }}" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius:16px;">
            <div class="modal-header"><h5 class="modal-title fw-600"><i class="fas fa-pen me-2 text-warning"></i>{{ __('messages.edit') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST" action="{{ route('admin.template-categories.update', $cat) }}" class="admin-form">
                @csrf @method('PUT')
                <div class="modal-body" style="display:flex;flex-direction:column;gap:14px;">
                    <div><label class="form-label">{{ __('messages.category_name') }} *</label><input type="text" name="name" class="form-control" value="{{ $cat->name }}" required></div>
                    <div><label class="form-label">{{ __('messages.log_description') }}</label><input type="text" name="description" class="form-control" value="{{ $cat->description }}"></div>
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
