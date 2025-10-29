@extends('layouts.app')

@section('content')
<h1 class="h3 mb-3">Preview: {{ $timer->title }}</h1>

@if($timer->gif_url)
    <div class="mb-3">
        <img src="{{ $timer->gif_url }}" class="img-fluid border" alt="preview">
    </div>
    <div class="mb-3">
        <label class="form-label">Embed Code</label>
        <input type="text" class="form-control" value='<img src="{{ $timer->gif_url }}" alt="Countdown Timer">' readonly>
    </div>
@else
    <div class="alert alert-warning">No GIF generated yet.</div>
@endif

<a href="{{ route('timers.edit', $timer) }}" class="btn btn-secondary">Back</a>
@endsection


