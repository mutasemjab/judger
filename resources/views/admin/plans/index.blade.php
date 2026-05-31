@extends('layouts.admin')
@section('title', __('messages.subscription_plans'))
@section('page_title', __('messages.subscription_plans'))

@section('content')
<div class="page-header">
    <div>
        <h1>{{ __('messages.subscription_plans') }}</h1>
        <nav aria-label="breadcrumb"><ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ __('messages.dashboard') }}</a></li>
            <li class="breadcrumb-item active">{{ __('messages.subscription_plans') }}</li>
        </ol></nav>
    </div>
</div>

<div class="row g-4">
    @foreach($plans as $plan)
    <div class="col-12 col-md-4">
        <div class="admin-card">
            <div class="admin-card-header">
                <h3 class="admin-card-title">
                    <span class="badge-status badge-{{ $plan->name?->value }}">{{ __('messages.'.$plan->name?->value) }}</span>
                </h3>
                <button type="button" class="btn btn-sm btn-outline-warning"
                        data-bs-toggle="modal" data-bs-target="#editPlanModal{{ $plan->id }}">
                    <i class="fas fa-pen me-1"></i>{{ __('messages.edit_plan') }}
                </button>
            </div>
            <div class="admin-card-body">
                <div class="stat-number mb-1">
                    {{ $plan->price ? '$'.number_format($plan->price,2) : __('messages.free') }}
                    @if($plan->billing_period)<small class="text-muted-sm">/{{ __('messages.'.$plan->billing_period) }}</small>@endif
                </div>
                <p class="text-muted-sm mb-3">{{ $plan->description }}</p>

                <div class="fw-600 mb-2" style="font-size:0.82rem;">{{ __('messages.plan_limits') }}</div>
                <ul class="list-unstyled" style="display:flex;flex-direction:column;gap:7px;margin-bottom:16px;">
                    @foreach($plan->limits as $key => $val)
                    <li class="d-flex justify-content-between text-muted-sm">
                        <span>{{ str_replace('_', ' ', $key) }}</span>
                        <span class="fw-600">{{ $val === null ? '∞' : $val }}</span>
                    </li>
                    @endforeach
                </ul>

                <div class="fw-600 mb-2" style="font-size:0.82rem;">{{ __('messages.plan_features') }}</div>
                <ul class="list-unstyled" style="display:flex;flex-direction:column;gap:6px;">
                    @foreach($plan->features as $feat => $enabled)
                    <li class="d-flex align-items-center gap-2 text-muted-sm">
                        <i class="fas {{ $enabled ? 'fa-check text-success' : 'fa-xmark text-danger' }}" style="width:14px;"></i>
                        {{ str_replace('_', ' ', $feat) }}
                    </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>

    {{-- Edit Plan Modal --}}
    <div class="modal fade" id="editPlanModal{{ $plan->id }}" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content" style="border-radius:16px;">
                <div class="modal-header"><h5 class="modal-title fw-600"><i class="fas fa-pen me-2 text-warning"></i>{{ __('messages.edit_plan') }} — {{ __('messages.'.$plan->name?->value) }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <form method="POST" action="{{ route('admin.plans.update', $plan) }}" class="admin-form">
                    @csrf @method('PUT')
                    <div class="modal-body" style="display:flex;flex-direction:column;gap:14px;">
                        <div><label class="form-label">{{ __('messages.log_description') }}</label><input type="text" name="description" class="form-control" value="{{ $plan->description }}"></div>
                        <div><label class="form-label">{{ __('messages.plan_price') }} (USD)</label><input type="number" name="price" step="0.01" class="form-control" value="{{ $plan->price }}"></div>
                        <div class="form-check">
                            <input type="checkbox" name="is_active" value="1" class="form-check-input" id="active_{{ $plan->id }}" {{ $plan->is_active?'checked':'' }}>
                            <label class="form-check-label" for="active_{{ $plan->id }}">{{ __('messages.is_active') }}</label>
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
</div>
@endsection
