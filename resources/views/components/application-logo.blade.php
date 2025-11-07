@once
    <style>
        .theme-logo-dark,
        .theme-logo-small-dark { display: none; }

        .dark .theme-logo-dark,
        .dark .theme-logo-small-dark { display: inline-block; }

        .dark .theme-logo-light,
        .dark .theme-logo-small-light { display: none; }

        .theme-logo-small { display: none; }

        .sidebar-desktop.collapsed .theme-logo-large { display: none !important; }
        .sidebar-desktop.collapsed .theme-logo-small { display: inline-block !important; }

        .theme-logo-wrapper img {
            transition: none !important;
        }

        .theme-logo-wrapper img:hover {
            filter: none !important;
            transform: none !important;
        }
    </style>
@endonce

<span class="theme-logo-wrapper d-inline-flex align-items-center">
    <img
        src="{{ asset('assets/images/countdownly_dark.png') }}"
        alt="Countdownly logo"
        class="theme-logo-large theme-logo-light"
        style="height: 40px; width: auto;"
    >
    <img
        src="{{ asset('assets/images/countdownly_light.png') }}"
        alt="Countdownly logo"
        class="theme-logo-large theme-logo-dark"
        style="height: 40px; width: auto;"
    >
    <img
        src="{{ asset('assets/images/small_light.png') }}"
        alt="Countdownly icon"
        class="theme-logo-small theme-logo-small-light"
        style="height: 28px; width: auto;"
    >
    <img
        src="{{ asset('assets/images/small_dark.png') }}"
        alt="Countdownly icon"
        class="theme-logo-small theme-logo-small-dark"
        style="height: 28px; width: auto;"
    >
</span>