@once
<style>
    /* Default (Light theme) - Large logo visible, Small hidden */
    .theme-logo-large-light {
        display: inline-block;
    }
    .theme-logo-small-light {
        display: none;
    }
    .theme-logo-large-dark,
    .theme-logo-small-dark {
        display: none;
    }

    /* Dark mode handling */
    .dark .theme-logo-large-light,
    .dark .theme-logo-small-light {
        display: none;
    }
    .dark .theme-logo-large-dark {
        display: inline-block;
        max-width:348px;
    }
    .dark .theme-logo-small-dark {
        display: none;
    }

    /* Collapsed sidebar state */
    .sidebar-desktop.collapsed .theme-logo-large-light,
    .sidebar-desktop.collapsed .theme-logo-large-dark {
        display: none;
    }

    .sidebar-desktop.collapsed .theme-logo-small-light {
        display: inline-block;
    }

    .dark .sidebar-desktop.collapsed .theme-logo-small-light {
        display: none;
    }

    .dark .sidebar-desktop.collapsed .theme-logo-small-dark {
        display: inline-block;
    }

    /* Smooth handling */
    .theme-logo-wrapper img {
        height: auto;
        transition: none;
        max-width: 350px;
        object-fit: contain;
    }
</style>
@endonce

<span class="theme-logo-wrapper d-inline-flex align-items-center">
    {{-- Light Theme Logos --}}
    <img
        src="{{ asset('assets/images/Countdownly logo for white bg.png') }}"
        alt="Countdownly logo light"
        class="theme-logo-large-light"
        style="height: 40px;"
    >
    <img
        src="{{ asset('assets/images/small_dark.png') }}"
        alt="Countdownly icon light"
        class="theme-logo-small-light"
        style="height: 28px;"
    >

    {{-- Dark Theme Logos --}}
    <img
        src="{{ asset('assets/images/Countdownly logo for black bg.png') }}"
        alt="Countdownly logo dark"
        class="theme-logo-large-dark"
        style="height: 40px;"
    >
    <img
        src="{{ asset('assets/images/small_light.png') }}"
        alt="Countdownly icon dark"
        class="theme-logo-small-dark"
        style="height: 28px;"
    >
</span>
