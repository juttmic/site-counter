@extends('layouts.app')

@section('content')
<h1 class="h3 mb-3">Login</h1>
<form method="POST" action="{{ route('login') }}" class="row g-3" autocomplete="on">
    @csrf
    <div class="col-12">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" required>
    </div>
    <div class="col-12">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control" required>
    </div>
    <div class="col-12">
        <button class="btn btn-primary">Login</button>
        <a href="{{ route('register') }}" class="btn btn-link">Register</a>
    </div>
</form>
@endsection


