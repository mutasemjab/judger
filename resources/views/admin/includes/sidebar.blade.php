@php $locale = app()->getLocale(); $cur = Route::currentRouteName() ?? ''; @endphp

<aside class="sidebar" id="adminSidebar">

    {{-- Brand --}}
    <a href="{{ route('admin.dashboard') }}" class="sidebar-brand">
        <div class="brand-icon"><i class="fas fa-scale-balanced"></i></div>
        <span class="brand-name">{{ $locale === 'ar' ? 'جدجر AI' : 'Judger AI' }}</span>
    </a>

    <ul class="sidebar-nav">

        {{-- ── MAIN ── --}}
        <li><span class="nav-section-label">{{ __('messages.nav_main') }}</span></li>

        <li class="nav-item">
            <a href="{{ route('admin.dashboard') }}"
               class="nav-link {{ Str::startsWith($cur,'admin.dashboard') ? 'active':'' }}">
                <i class="nav-icon fas fa-gauge-high"></i>
                <span class="nav-text">{{ __('messages.dashboard') }}</span>
            </a>
        </li>

        {{-- ── USERS ── --}}
        <li><span class="nav-section-label">{{ __('messages.nav_users') }}</span></li>

        <li class="nav-item">
            <a href="{{ route('admin.users.index') }}"
               class="nav-link {{ Str::startsWith($cur,'admin.users') ? 'active':'' }}">
                <i class="nav-icon fas fa-users"></i>
                <span class="nav-text">{{ __('messages.users') }}</span>
            </a>
        </li>

        {{-- ── LEGAL ── --}}
        <li><span class="nav-section-label">{{ __('messages.nav_legal') }}</span></li>

        <li class="nav-item">
            <a href="{{ route('admin.cases.index') }}"
               class="nav-link {{ Str::startsWith($cur,'admin.cases') ? 'active':'' }}">
                <i class="nav-icon fas fa-briefcase"></i>
                <span class="nav-text">{{ __('messages.legal_cases') }}</span>
            </a>
        </li>

        <li class="nav-item">
            <a href="{{ route('admin.documents.index') }}"
               class="nav-link {{ Str::startsWith($cur,'admin.documents') ? 'active':'' }}">
                <i class="nav-icon fas fa-file-lines"></i>
                <span class="nav-text">{{ __('messages.case_documents') }}</span>
            </a>
        </li>

        <li class="nav-item">
            <a href="{{ route('admin.conversations.index') }}"
               class="nav-link {{ Str::startsWith($cur,'admin.conversations') ? 'active':'' }}">
                <i class="nav-icon fas fa-comments"></i>
                <span class="nav-text">{{ __('messages.conversations') }}</span>
            </a>
        </li>

        {{-- ── AI & KNOWLEDGE ── --}}
        <li><span class="nav-section-label">{{ __('messages.nav_ai') }}</span></li>

        <li class="nav-item">
            <a href="{{ route('admin.knowledge.index') }}"
               class="nav-link {{ Str::startsWith($cur,'admin.knowledge') ? 'active':'' }}">
                <i class="nav-icon fas fa-book-open"></i>
                <span class="nav-text">{{ __('messages.knowledge_base') }}</span>
            </a>
        </li>

        <li class="nav-item">
            <a href="{{ route('admin.ai-tools.index') }}"
               class="nav-link {{ Str::startsWith($cur,'admin.ai-tools') ? 'active':'' }}">
                <i class="nav-icon fas fa-robot"></i>
                <span class="nav-text">{{ __('messages.ai_tools_history') }}</span>
            </a>
        </li>

        {{-- ── CONTENT ── --}}
        <li><span class="nav-section-label">{{ __('messages.nav_content') }}</span></li>

        <li class="nav-item">
            <a href="{{ route('admin.template-categories.index') }}"
               class="nav-link {{ Str::startsWith($cur,'admin.template-categories') ? 'active':'' }}">
                <i class="nav-icon fas fa-folder-open"></i>
                <span class="nav-text">{{ __('messages.template_categories') }}</span>
            </a>
        </li>

        <li class="nav-item">
            <a href="{{ route('admin.templates.index') }}"
               class="nav-link {{ Str::startsWith($cur,'admin.templates') ? 'active':'' }}">
                <i class="nav-icon fas fa-file-contract"></i>
                <span class="nav-text">{{ __('messages.templates') }}</span>
            </a>
        </li>

        {{-- ── BILLING ── --}}
        <li><span class="nav-section-label">{{ __('messages.nav_billing') }}</span></li>

        <li class="nav-item">
            <a href="{{ route('admin.plans.index') }}"
               class="nav-link {{ Str::startsWith($cur,'admin.plans') ? 'active':'' }}">
                <i class="nav-icon fas fa-tags"></i>
                <span class="nav-text">{{ __('messages.subscription_plans') }}</span>
            </a>
        </li>

        <li class="nav-item">
            <a href="{{ route('admin.subscriptions.index') }}"
               class="nav-link {{ Str::startsWith($cur,'admin.subscriptions') ? 'active':'' }}">
                <i class="nav-icon fas fa-credit-card"></i>
                <span class="nav-text">{{ __('messages.subscriptions') }}</span>
            </a>
        </li>

        {{-- ── SYSTEM ── --}}
        <li><span class="nav-section-label">{{ __('messages.nav_system') }}</span></li>

        <li class="nav-item">
            <a href="{{ route('admin.notifications.index') }}"
               class="nav-link {{ Str::startsWith($cur,'admin.notifications') ? 'active':'' }}">
                <i class="nav-icon fas fa-bell"></i>
                <span class="nav-text">{{ __('messages.notifications_menu') }}</span>
            </a>
        </li>

        <li class="nav-item">
            <a href="{{ route('admin.activity.index') }}"
               class="nav-link {{ Str::startsWith($cur,'admin.activity') ? 'active':'' }}">
                <i class="nav-icon fas fa-clock-rotate-left"></i>
                <span class="nav-text">{{ __('messages.activity_logs') }}</span>
            </a>
        </li>

    </ul>

    {{-- Footer / Logout --}}
    <div class="sidebar-footer">
        <form id="sidebar-logout-form" action="{{ route('admin.logout') }}" method="POST" class="d-none">@csrf</form>
        <a href="#" class="nav-link"
           onclick="event.preventDefault(); document.getElementById('sidebar-logout-form').submit()">
            <i class="nav-icon fas fa-right-from-bracket"></i>
            <span class="nav-text">{{ __('messages.logout') }}</span>
        </a>
    </div>

</aside>
