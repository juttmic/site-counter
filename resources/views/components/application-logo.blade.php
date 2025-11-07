@once
    <style>
        .theme-logo-dark { display: none; }
        .dark .theme-logo-dark { display: inline-block; }
        .dark .theme-logo-light { display: none; }
    </style>
@endonce

<span class="theme-logo-wrapper d-inline-flex align-items-center">
    <img
        src="{{ asset('assets/images/countdownly_dark.png') }}"
        alt="Countdownly logo"
        class="theme-logo-light"
        style="height: 40px; width: auto;"
    >
    <img
        src="{{ asset('assets/images/countdownly_light.png') }}"
        alt="Countdownly logo"
        class="theme-logo-dark"
        style="height: 40px; width: auto;"
    >
</span>