@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3">Your Timers</h1>
    <a href="{{ route('timers.create') }}" class="btn btn-primary">Create Timer</a>
  </div>

@if($timers->isEmpty())
    <div class="alert alert-info">No timers yet. Create your first one!</div>
@else
    <div class="row g-3">
        @foreach($timers as $timer)
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">{{ $timer->title }}</h5>
                        <p class="card-text">
                            Type: <span class="badge bg-secondary">{{ $timer->timer_type }}</span><br>
                            Skin: {{ $timer->skin }}<br>
                            Status: <span class="badge {{ $timer->status === 'active' ? 'bg-success' : 'bg-danger' }}">{{ $timer->status }}</span>
                        </p>
                        <div class="mb-2">
                            @if($timer->gif_url)
                                <img src="{{ $timer->gif_url }}" alt="preview" class="img-fluid border">
                            @endif
                        </div>
                        <a href="{{ route('timers.preview', $timer) }}" class="btn btn-sm btn-outline-secondary">Preview</a>
                        <a href="{{ route('timers.edit', $timer) }}" class="btn btn-sm btn-primary">Edit</a>
                        <form action="{{ route('timers.destroy', $timer) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this timer?');">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-danger">Delete</button>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    <div class="mt-3">{{ $timers->links() }}</div>
@endif

@endsection


