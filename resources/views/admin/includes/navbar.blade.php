@php $locale = app()->getLocale(); @endphp

<header class="top-navbar">

    {{-- Desktop sidebar toggle --}}
    <button class="navbar-toggle-btn d-none d-lg-flex" id="sidebarToggleDesktop" title="Toggle sidebar">
        <i class="fas fa-bars"></i>
    </button>

    {{-- Mobile sidebar toggle --}}
    <button class="navbar-toggle-btn d-flex d-lg-none" id="sidebarToggleMobile" title="Open menu">
        <i class="fas fa-bars"></i>
    </button>

    {{-- Page title --}}
    <span class="navbar-page-title d-none d-sm-block">@yield('page_title', __('messages.dashboard'))</span>

    <div class="navbar-spacer"></div>

    <div class="navbar-actions">

        {{-- Language switcher --}}
        @if($locale === 'ar')
            <a href="{{ url('/en' . request()->getPathInfo()) }}" class="navbar-btn lang-btn" title="Switch to English">
                <i class="fas fa-language"></i> EN
            </a>
        @else
            <a href="{{ url('/ar' . request()->getPathInfo()) }}" class="navbar-btn lang-btn" title="التبديل إلى العربية">
                <i class="fas fa-language"></i> AR
            </a>
        @endif

        {{-- User dropdown --}}
        <div class="dropdown">
            <a href="#" class="user-dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <div class="user-avatar">
                    {{ strtoupper(substr(auth('admin_web')->user()->name ?? 'A', 0, 1)) }}
                </div>
                <div class="d-none d-md-block text-start">
                    <div class="user-name">{{ auth('admin_web')->user()->name ?? 'Admin' }}</div>
                    <div class="user-role">{{ __('messages.administrator') }}</div>
                </div>
                <i class="fas fa-chevron-down ms-1" style="font-size:0.65rem; color:#94a3b8;"></i>
            </a>
            <ul class="dropdown-menu dropdown-menu-end shadow-lg">
                <li>
                    <a class="dropdown-item" href="{{ route('admin.profile') }}">
                        <i class="fas fa-user-gear"></i>
                        {{ __('messages.account_settings') }}
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item text-danger" href="#"
                       onclick="event.preventDefault(); document.getElementById('navbar-logout-form').submit()">
                        <i class="fas fa-right-from-bracket"></i>
                        {{ __('messages.logout') }}
                    </a>
                </li>
            </ul>
        </div>

    </div>

    <form id="navbar-logout-form" action="{{ route('admin.logout') }}" method="POST" class="d-none">@csrf</form>

</header>
