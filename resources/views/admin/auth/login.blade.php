<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('messages.login_title') }} — Judger AI</title>

    @if(app()->getLocale() === 'ar')
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">
    @else
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    @endif
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: {{ app()->getLocale() === 'ar' ? "'Tajawal'" : "'Poppins'" }}, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #0f172a;
            overflow: hidden;
            position: relative;
        }
        .bg-scene {
            position: fixed; inset: 0;
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #0f172a 100%);
            z-index: 0;
        }
        .bg-dots {
            position: absolute; inset: 0;
            background-image: radial-gradient(circle, rgba(255,255,255,0.05) 1px, transparent 1px);
            background-size: 36px 36px;
        }
        .blob {
            position: absolute; border-radius: 50%;
            filter: blur(80px); opacity: 0.25;
            animation: floatBlob 10s ease-in-out infinite;
        }
        .blob-1 { width: 500px; height: 500px; background: radial-gradient(circle, #6366f1, #8b5cf6); top: -160px; left: -160px; animation-delay: 0s; }
        .blob-2 { width: 380px; height: 380px; background: radial-gradient(circle, #06b6d4, #3b82f6); bottom: -120px; right: -100px; animation-delay: -4s; }
        .blob-3 { width: 260px; height: 260px; background: radial-gradient(circle, #a855f7, #ec4899); top: 50%; left: 60%; animation-delay: -7s; }
        @keyframes floatBlob {
            0%, 100% { transform: translate(0,0) scale(1); }
            33%       { transform: translate(24px,-32px) scale(1.05); }
            66%       { transform: translate(-16px,20px) scale(0.97); }
        }
        .login-wrapper {
            position: relative; z-index: 10;
            width: 100%; max-width: 440px; padding: 20px;
            animation: cardIn 0.6s cubic-bezier(0.16,1,0.3,1) both;
        }
        @keyframes cardIn {
            from { opacity: 0; transform: translateY(28px) scale(0.97); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }
        .login-card {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(24px);
            border: 1px solid rgba(255,255,255,0.10);
            border-radius: 22px;
            padding: 46px 42px 40px;
            box-shadow: 0 12px 48px rgba(0,0,0,0.45);
        }
        .brand-section { text-align: center; margin-bottom: 36px; }
        .brand-icon-wrap {
            display: inline-flex; align-items: center; justify-content: center;
            width: 68px; height: 68px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            border-radius: 18px; font-size: 1.8rem; color: #fff; margin-bottom: 16px;
            box-shadow: 0 8px 28px rgba(99,102,241,0.50);
            animation: iconGlow 3s ease-in-out infinite;
        }
        @keyframes iconGlow {
            0%, 100% { box-shadow: 0 8px 28px rgba(99,102,241,0.50); }
            50%       { box-shadow: 0 8px 40px rgba(139,92,246,0.70); }
        }
        .brand-title { font-size: 1.5rem; font-weight: 800; color: #fff; margin-bottom: 5px; }
        .brand-subtitle { font-size: 0.87rem; color: rgba(255,255,255,0.42); }
        .field-label { display: block; font-size: 0.8rem; font-weight: 600; color: rgba(255,255,255,0.60); margin-bottom: 7px; }
        .field-wrap { position: relative; margin-bottom: 18px; }
        .field-input {
            width: 100%; height: 50px;
            background: rgba(255,255,255,0.07);
            border: 1.5px solid rgba(255,255,255,0.10);
            border-radius: 12px;
            padding: 0 46px 0 14px;
            color: #fff; font-size: 0.92rem;
            font-family: inherit;
            transition: all 0.2s;
            outline: none;
        }
        .field-input::placeholder { color: rgba(255,255,255,0.20); }
        .field-input:focus { background: rgba(255,255,255,0.10); border-color: rgba(99,102,241,0.80); box-shadow: 0 0 0 4px rgba(99,102,241,0.16); }
        .field-input.has-error { border-color: rgba(239,68,68,0.70); }
        .field-icon { position: absolute; top: 50%; right: 15px; transform: translateY(-50%); color: rgba(255,255,255,0.28); font-size: 0.87rem; pointer-events: none; }
        .field-input:focus ~ .field-icon { color: #818cf8; }
        .field-input.with-toggle { padding-left: 42px; }
        .pw-toggle { position: absolute; top: 50%; left: 13px; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: rgba(255,255,255,0.28); font-size: 0.87rem; padding: 4px; transition: color 0.2s; }
        .pw-toggle:hover { color: rgba(255,255,255,0.60); }
        .error-text { display: flex; align-items: center; gap: 5px; margin-top: -10px; margin-bottom: 14px; font-size: 0.77rem; color: #fca5a5; }
        .btn-submit {
            width: 100%; height: 50px; border: none; border-radius: 12px;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: #fff; font-size: 0.98rem; font-weight: 700; font-family: inherit;
            cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px;
            position: relative; overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 5px 20px rgba(99,102,241,0.46);
            margin-top: 8px;
        }
        .btn-submit::before { content: ''; position: absolute; inset: 0; background: linear-gradient(135deg,#818cf8,#a855f7); opacity: 0; transition: opacity 0.2s; }
        .btn-submit:hover::before { opacity: 1; }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(99,102,241,0.52); }
        .btn-submit:active { transform: translateY(0); }
        .btn-submit span, .btn-submit i { position: relative; z-index: 1; }
        .card-footer-note { text-align: center; margin-top: 28px; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.07); }
        .card-footer-note p { font-size: 0.76rem; color: rgba(255,255,255,0.25); }
        .shake { animation: shake 0.4s cubic-bezier(.36,.07,.19,.97) both; }
        @keyframes shake {
            10%,90%  { transform: translateX(-3px); }
            20%,80%  { transform: translateX(4px); }
            30%,50%,70% { transform: translateX(-5px); }
            40%,60%  { transform: translateX(5px); }
        }
        @media (max-width:480px) { .login-card { padding: 36px 24px 32px; } }
    </style>
</head>
<body>

<div class="bg-scene">
    <div class="bg-dots"></div>
    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>
    <div class="blob blob-3"></div>
</div>

<div class="login-wrapper">
    <div class="login-card" id="loginCard">

        <div class="brand-section">
            <div class="brand-icon-wrap"><i class="fas fa-scale-balanced"></i></div>
            <h1 class="brand-title">{{ __('messages.login_title') }}</h1>
            <p class="brand-subtitle">{{ __('messages.login_subtitle') }}</p>
        </div>

        @if(session('error'))
            <div style="background:rgba(239,68,68,0.15);border:1px solid rgba(239,68,68,0.4);border-radius:10px;padding:10px 14px;margin-bottom:18px;font-size:0.83rem;color:#fca5a5;display:flex;align-items:center;gap:7px;">
                <i class="fas fa-circle-exclamation"></i> {{ session('error') }}
            </div>
        @endif

        <form action="{{ route('admin.login') }}" method="POST" novalidate>
            @csrf

            {{-- Email --}}
            <div>
                <label class="field-label" for="email">{{ __('messages.email') }}</label>
                <div class="field-wrap">
                    <input type="email" id="email" name="email"
                           class="field-input {{ $errors->has('email') ? 'has-error' : '' }}"
                           placeholder="{{ app()->getLocale() === 'ar' ? 'أدخل بريدك الإلكتروني' : 'Enter your email' }}"
                           value="{{ old('email') }}" autocomplete="email" autofocus>
                    <i class="fas fa-envelope field-icon"></i>
                </div>
                @error('email')
                    <div class="error-text"><i class="fas fa-circle-exclamation"></i>{{ $message }}</div>
                @enderror
            </div>

            {{-- Password --}}
            <div>
                <label class="field-label" for="password">{{ __('messages.password') }}</label>
                <div class="field-wrap">
                    <input type="password" id="password" name="password"
                           class="field-input with-toggle {{ $errors->has('password') ? 'has-error' : '' }}"
                           placeholder="{{ app()->getLocale() === 'ar' ? 'أدخل كلمة المرور' : 'Enter your password' }}"
                           autocomplete="current-password">
                    <i class="fas fa-lock field-icon"></i>
                    <button type="button" class="pw-toggle" id="pwToggle">
                        <i class="fas fa-eye" id="pwToggleIcon"></i>
                    </button>
                </div>
                @error('password')
                    <div class="error-text"><i class="fas fa-circle-exclamation"></i>{{ $message }}</div>
                @enderror
            </div>

            <button type="submit" class="btn-submit">
                <i class="fas fa-right-to-bracket"></i>
                <span>{{ __('messages.login') }}</span>
            </button>
        </form>

        <div class="card-footer-note">
            <p>&copy; {{ date('Y') }} Judger AI</p>
        </div>

    </div>
</div>

<script>
    const pwInput = document.getElementById('password');
    const pwIcon  = document.getElementById('pwToggleIcon');
    document.getElementById('pwToggle').addEventListener('click', function () {
        const show = pwInput.type === 'password';
        pwInput.type = show ? 'text' : 'password';
        pwIcon.className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
    });
    @if($errors->any())
    (function () {
        const card = document.getElementById('loginCard');
        card.classList.add('shake');
        card.addEventListener('animationend', function () { card.classList.remove('shake'); }, { once: true });
    })();
    @endif
</script>
</body>
</html>
