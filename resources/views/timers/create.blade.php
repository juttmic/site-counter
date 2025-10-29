@extends('layouts.app')

@section('content')
<h1 class="h3 mb-3">Create Timer</h1>

<form method="POST" action="{{ route('timers.store') }}" class="row g-3">
    @csrf
    <div class="col-md-6">
        <label class="form-label">Title</label>
        <input type="text" name="title" value="{{ old('title') }}" class="form-control" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
            <option value="active" selected>Active</option>
            <option value="inactive">Inactive</option>
        </select>
    </div>

    <div class="col-md-6">
        <label class="form-label">Timer Type</label>
        <select name="timer_type" id="timer_type" class="form-select" onchange="toggleInputs()">
            <option value="fixed_date">Fixed Date</option>
            <option value="reload_based">Reload Based</option>
        </select>
    </div>
    <div class="col-md-6" id="fixed_date_input">
        <label class="form-label">Target DateTime</label>
        <input type="datetime-local" name="target_datetime" class="form-control">
    </div>
    <div class="col-md-6 d-none" id="duration_input">
        <label class="form-label">Duration (seconds)</label>
        <input type="number" name="duration_seconds" class="form-control" min="1" max="604800">
    </div>

    <div class="col-md-6">
        <label class="form-label">Skin</label>
        <select name="skin" class="form-select">
            @foreach($skins as $skin)
                <option value="{{ $skin }}">{{ $skin }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-6">
        <label class="form-label">Text Position</label>
        <select name="text_position" class="form-select">
            <option value="none" selected>None</option>
            <option value="top">Top</option>
            <option value="bottom">Bottom</option>
        </select>
    </div>

    <div class="col-12">
        <label class="form-label">Custom Text (optional)</label>
        <input type="text" name="custom_text" class="form-control" maxlength="255">
    </div>

    <div class="col-12">
        <button class="btn btn-primary">Save</button>
        <a href="{{ route('dashboard') }}" class="btn btn-secondary">Cancel</a>
    </div>
</form>

<script>
function toggleInputs() {
  const type = document.getElementById('timer_type').value;
  document.getElementById('fixed_date_input').classList.toggle('d-none', type !== 'fixed_date');
  document.getElementById('duration_input').classList.toggle('d-none', type !== 'reload_based');
}
document.addEventListener('DOMContentLoaded', toggleInputs);
</script>
@endsection


