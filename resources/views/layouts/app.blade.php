<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TimerGIF App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container">
        <a class="navbar-brand" href="/">TimerGIF App</a>
        <div class="d-flex">
            @auth
                <a href="{{ route('dashboard') }}" class="btn btn-outline-light me-2">Dashboard</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn btn-warning">Logout</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="btn btn-outline-light me-2">Login</a>
                <a href="{{ route('register') }}" class="btn btn-warning">Register</a>
            @endauth
        </div>
    </div>
    </nav>

<main class="container">
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @yield('content')
    </main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


