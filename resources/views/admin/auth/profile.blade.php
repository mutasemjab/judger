@extends('layouts.admin')
@section('title', __('messages.account_settings'))
@section('page_title', __('messages.account_settings'))

@section('content')
<div class="page-header">
    <div>
        <h1>{{ __('messages.account_settings') }}</h1>
        <nav aria-label="breadcrumb"><ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ __('messages.dashboard') }}</a></li>
            <li class="breadcrumb-item active">{{ __('messages.account_settings') }}</li>
        </ol></nav>
    </div>
</div>

<div class="row g-4">
    <div class="col-12 col-md-6">
        <div class="admin-card">
            <div class="admin-card-header"><h3 class="admin-card-title"><i class="fas fa-user-gear"></i> {{ __('messages.account_settings') }}</h3></div>
            <div class="admin-card-body">
                <form method="POST" action="{{ route('admin.profile.update') }}" class="admin-form" style="display:flex;flex-direction:column;gap:14px;">
                    @csrf @method('PUT')
                    <div>
                        <label class="form-label">{{ __('messages.name') }}</label>
                        <input type="text" name="name" class="form-control" value="{{ $user->name }}" required>
                    </div>
                    <div>
                        <label class="form-label">{{ __('messages.email') }}</label>
                        <input type="email" name="email" class="form-control" value="{{ $user->email }}" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-save me-1"></i>{{ __('messages.save') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6">
        <div class="admin-card">
            <div class="admin-card-header"><h3 class="admin-card-title"><i class="fas fa-lock"></i> {{ __('messages.password') }}</h3></div>
            <div class="admin-card-body">
                <form method="POST" action="{{ route('admin.profile.password') }}" class="admin-form" style="display:flex;flex-direction:column;gap:14px;">
                    @csrf @method('PUT')
                    <div>
                        <label class="form-label">{{ app()->getLocale()==='ar'?'كلمة المرور الحالية':'Current Password' }}</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div>
                        <label class="form-label">{{ app()->getLocale()==='ar'?'كلمة المرور الجديدة':'New Password' }}</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div>
                        <label class="form-label">{{ app()->getLocale()==='ar'?'تأكيد كلمة المرور':'Confirm Password' }}</label>
                        <input type="password" name="password_confirmation" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-lock me-1"></i>{{ __('messages.save') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
