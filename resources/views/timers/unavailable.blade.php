<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'CountDownly') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Bootstrap 5 CSS -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <!-- Scripts -->
        @vite(['resources/js/app.js'])
        <!-- Bootstrap 5 JS -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    </head>
    <body class="bg-light">
        <div class="min-vh-100 d-flex flex-column justify-content-center align-items-center py-5">
            <a href="{{ $empireUrl }}" class="mb-3 text-decoration-none">
                <x-application-logo class="d-block" style="width: 76px; height: 76px;" />
            </a>
            <div class="card shadow-sm" style="width: 100%; max-width: 460px;">
                <div class="card-body p-4">
                    <div class="text-center">
                        <h1 class="h5 fw-semibold mb-2">This countdown timer is no longer available.</h1>
                        <p class="text-muted mb-0">
                            If you’d like to create your own countdown timer,
                            <a href="{{ $empireUrl }}" class="fw-bold text-decoration-none" style="font-size: 1.05rem;">
                                click here
                            </a>
                            to get started.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
