@php $locale = app()->getLocale(); @endphp

<footer class="admin-footer">
    <span>&copy; {{ date('Y') }} Judger AI</span>
    <span>{{ $locale === 'ar' ? 'جميع الحقوق محفوظة' : 'All Rights Reserved' }}</span>
</footer>
