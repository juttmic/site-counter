@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3">Edit Timer</h1>
    <a href="{{ route('timers.preview', $timer) }}" class="btn btn-outline-secondary">Preview</a>
  </div>

<form method="POST" action="{{ route('timers.update', $timer) }}" class="row g-3">
    @csrf
    @method('PUT')
    <div class="col-md-6">
        <label class="form-label">Title</label>
        <input type="text" name="title" value="{{ old('title', $timer->title) }}" class="form-control" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
            <option value="active" @selected($timer->status==='active')>Active</option>
            <option value="inactive" @selected($timer->status==='inactive')>Inactive</option>
        </select>
    </div>

    <div class="col-md-6">
        <label class="form-label">Timer Type</label>
        <select name="timer_type" id="timer_type" class="form-select" onchange="toggleInputs()">
            <option value="fixed_date" @selected($timer->timer_type==='fixed_date')>Fixed Date</option>
            <option value="reload_based" @selected($timer->timer_type==='reload_based')>Reload Based</option>
        </select>
    </div>
    <div class="col-md-6" id="fixed_date_input">
        <label class="form-label">Target DateTime</label>
        <input type="datetime-local" name="target_datetime" class="form-control" value="{{ optional($timer->target_datetime)->format('Y-m-d\TH:i') }}">
    </div>
    <div class="col-md-6 d-none" id="duration_input">
        <label class="form-label">Duration (seconds)</label>
        <input type="number" name="duration_seconds" class="form-control" min="1" max="604800" value="{{ old('duration_seconds', $timer->duration_seconds) }}">
    </div>

    <div class="col-md-6">
        <label class="form-label">Skin</label>
        <select name="skin" class="form-select">
            @foreach($skins as $skin)
                <option value="{{ $skin }}" @selected($timer->skin===$skin)>{{ $skin }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-6">
        <label class="form-label">Text Position</label>
        <select name="text_position" class="form-select">
            <option value="none" @selected($timer->text_position==='none')>None</option>
            <option value="top" @selected($timer->text_position==='top')>Top</option>
            <option value="bottom" @selected($timer->text_position==='bottom')>Bottom</option>
        </select>
    </div>

    <div class="col-12">
        <label class="form-label">Custom Text</label>
        <input type="text" name="custom_text" class="form-control" maxlength="255" value="{{ old('custom_text', $timer->custom_text) }}">
    </div>

    <div class="col-12">
        <button class="btn btn-primary">Save</button>
        <a href="{{ route('dashboard') }}" class="btn btn-secondary">Back</a>
    </div>
</form>

@if($timer->gif_url)
<div class="mt-4">
    <h2 class="h5">Embed</h2>
    <div class="input-group">
        <input type="text" class="form-control" value='<img src="{{ $timer->gif_url }}" alt="Countdown Timer">' readonly>
        <button class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText(document.querySelector('.input-group input').value)">Copy</button>
    </div>
    <div class="mt-3">
        <img src="{{ $timer->gif_url }}" class="img-fluid border" alt="preview">
    </div>
</div>
@endif

<script>
function toggleInputs() {
  const type = document.getElementById('timer_type').value;
  document.getElementById('fixed_date_input').classList.toggle('d-none', type !== 'fixed_date');
  document.getElementById('duration_input').classList.toggle('d-none', type !== 'reload_based');
}
document.addEventListener('DOMContentLoaded', toggleInputs);
</script>
@endsection


