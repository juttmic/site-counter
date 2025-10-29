@extends('layouts.app')

@section('content')
<h1 class="h3 mb-3">Register</h1>
<form method="POST" action="{{ route('register') }}" class="row g-3" autocomplete="on">
    @csrf
    <div class="col-12">
        <label class="form-label">Name</label>
        <input type="text" name="name" class="form-control" required>
    </div>
    <div class="col-12">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" required>
    </div>
    <div class="col-12">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control" required>
    </div>
    <div class="col-12">
        <label class="form-label">Confirm Password</label>
        <input type="password" name="password_confirmation" class="form-control" required>
    </div>
    <div class="col-12">
        <button class="btn btn-primary">Register</button>
        <a href="{{ route('login') }}" class="btn btn-link">Login</a>
    </div>
</form>
@endsection


