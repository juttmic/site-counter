@php
    $isEdit = isset($timer);
    $formAction = $isEdit ? route('timers.update', $timer) : route('timers.store');
    $formMethod = $isEdit ? 'PUT' : 'POST';
    $gifQueueMode = config('app.gif_generation_mode', 'queue') === 'queue';
    $gifPendingEdit = $isEdit && $gifQueueMode && ($timer->needsGifRegeneration() || empty($timer->gif_url));
@endphp
<style>
  .mine {
    padding: 3px !important;
    position: relative;
    border: 2px solid transparent;
    box-sizing: border-box;
    overflow: hidden;
  }
  .mine:hover {
    border: 2px solid #29b672 !important;
    transition: border 0.3s ease;
    border-radius: 25px !important;
  }
  .mine.selected-skin {
    border: 2px solid #29b672 !important;
    border-radius: 25px !important;
  }
  .skin-checkmark {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    z-index: 10;
    pointer-events: none;
    transition: opacity 0.3s ease, filter 0.3s ease;
    font-size: 3rem;
    color: #29b672;
    opacity: 0;
  }
  .mine:hover .skin-checkmark:not(.selected-checkmark) {
    opacity: 0.4;
    filter: blur(2px);
  }
  .mine.selected-skin .skin-checkmark.selected-checkmark {
    opacity: 1;
    filter: none;
  }
  .mine.selected-skin .skin-checkmark:not(.selected-checkmark) {
    opacity: 0;
  }
  #skin_scroll_bar {
    scroll-snap-type: x mandatory;
    scroll-behavior: smooth;
    -webkit-overflow-scrolling: touch;
  }
  .skin-card {
    scroll-snap-align: start;
    scroll-snap-stop: always;
  }
  #skin_scroll_bar .skin-card.mine .skin-card-preview {
    flex: 1 1 auto;
    min-width: 0;
    min-height: 0;
  }
  #skin_scroll_bar .skin-card.mine .skin-card-preview img {
    max-width: 100%;
    max-height: 100%;
    width: auto;
    height: auto;
    object-fit: contain;
    vertical-align: middle;
  }
  .scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
  }
  .scrollbar-hide::-webkit-scrollbar {
    display: none;
  }
  .owl-nav {
    user-select: none;
  }
  .owl-prev,
  .owl-next {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    transition: opacity 0.2s ease;
  }
  .owl-prev.disabled,
  .owl-next.disabled {
    opacity: 0.3;
    cursor: not-allowed !important;
    pointer-events: none;
  }
  .owl-prev:not(.disabled):hover,
  .owl-next:not(.disabled):hover {
    opacity: 0.8;
  }
  .owl-prev i,
  .owl-next i {
    font-size: 1.2rem;
  }
  
  /* Sticky Preview Section */
  .sticky-preview {
    position: sticky;
    top: 30vh;
    align-self: flex-start;
    max-height: calc(100vh - 40px);
    z-index: 10;
  }
  
  /* Smooth scrolling for preview container if content overflows */
  .sticky-preview .card-body {
    overflow-y: auto;
    max-height: calc(100vh - 120px);
  }
  
  /* Hide scrollbar for cleaner look (optional) */
  .sticky-preview .card-body::-webkit-scrollbar {
    width: 6px;
  }
  
  .sticky-preview .card-body::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
  }
  
  .sticky-preview .card-body::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 3px;
  }
  
  .sticky-preview .card-body::-webkit-scrollbar-thumb:hover {
    background: #555;
  }
  
  /* Collapse Icon Styling */
  .collapse-icon {
    font-size: 1rem;
    color: #6b7280;
    transition: transform 0.2s ease, color 0.2s ease;
    cursor: pointer;
    flex-shrink: 0;
  }
  
  .collapsible-header:hover .collapse-icon {
    color: #374151;
  }
  
  /* Modern Color Picker */
  .modern-color-picker {
    position: relative;
    display: flex;
    align-items: center;
    gap: 0.75rem;
  }
  
  .color-picker-swatch {
    position: relative;
    width: 48px;
    height: 48px;
    border-radius: 0.5rem;
    border: 2px solid #e5e7eb;
    background: #fff;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    flex-shrink: 0;
  }
  
  .color-picker-swatch:hover {
    border-color: #3b82f6;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    transform: translateY(-1px);
  }
  
  .color-picker-swatch::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(45deg, #ccc 25%, transparent 25%), 
                linear-gradient(-45deg, #ccc 25%, transparent 25%), 
                linear-gradient(45deg, transparent 75%, #ccc 75%), 
                linear-gradient(-45deg, transparent 75%, #ccc 75%);
    background-size: 8px 8px;
    background-position: 0 0, 0 4px, 4px -4px, -4px 0px;
    z-index: 0;
  }
  
  .color-picker-swatch::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: var(--swatch-color, #000);
    z-index: 1;
  }
  
  .color-picker-input-wrapper {
    flex: 1;
    position: relative;
  }
  
  .color-picker-input-wrapper input[type="color"] {
    position: absolute;
    opacity: 0;
    width: 100%;
    height: 100%;
    cursor: pointer;
    z-index: 2;
  }
  
  .color-picker-display {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.625rem 0.875rem;
    border: 1.5px solid #e5e7eb;
    border-radius: 0.5rem;
    background: #fff;
    cursor: pointer;
    transition: all 0.2s ease;
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
    font-size: 0.875rem;
    color: #374151;
    min-height: 48px;
  }
  
  .color-picker-display:hover {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
  }
  
  .color-picker-display:focus-within {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    outline: none;
  }
  
  .color-picker-hex {
    flex: 1;
    font-weight: 600;
    letter-spacing: 0.05em;
  }
  
  .color-picker-icon {
    color: #6b7280;
    font-size: 1rem;
    transition: color 0.2s ease;
  }
  
  .color-picker-display:hover .color-picker-icon {
    color: #3b82f6;
  }

  /* Toggle pills */
  .toggle-pill-group {
    display: inline-flex;
    border: 1.5px solid #e5e7eb;
    border-radius: 999px;
    overflow: hidden;
    background: #f9fafb;
  }
  .toggle-pill {
    font-size: 10px;
    border: none;
    background: transparent;
    padding: 5px 5px;
    font-weight: bold;
    color: #4b5563;
    transition: all 0.2s ease;
  }
  .toggle-pill:not(:last-child) {
    border-right: 1px solid #e5e7eb;
  }
  .toggle-pill.active {
    background: #0d6efd;
    color: #fff;
  }
  .toggle-pill:focus {
    outline: none;
  }
  
  /* Enhanced Form Styling */
  .form-label {
    font-weight: 600;
    font-size: 0.875rem;
    color: #374151;
    margin-bottom: 0.5rem;
    letter-spacing: 0.01em;
    font-family: 'Figtree', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  }
  
  .form-control,
  .form-select {
    border: 1.5px solid #e5e7eb;
    border-radius: 0.5rem;
    padding: 0.625rem 0.875rem;
    font-size: 0.9375rem;
    transition: all 0.2s ease;
    background-color: #ffffff;
    color: #111827;
    font-family: 'Figtree', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  }
  
  .form-control:focus,
  .form-select:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    outline: none;
    background-color: #ffffff;
  }
  
  .form-control:hover:not(:disabled):not(:focus),
  .form-select:hover:not(:disabled):not(:focus) {
    border-color: #d1d5db;
  }
  
  .form-control::placeholder {
    color: #9ca3af;
    opacity: 1;
  }
  
  .form-select {
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right 0.75rem center;
    background-size: 16px 12px;
    padding-right: 2.5rem;
  }

  /* Select2 styling to match .form-select / .form-control */
  .select2-container .select2-selection--single {
    height: auto;
    min-height: 2.75rem;
    padding: 0.625rem 0.875rem;
    border-radius: 0.5rem;
    border: 1.5px solid #e5e7eb;
    background-color: #ffffff;
    display: flex;
    align-items: center;
    transition: all 0.2s ease;
    font-size: 0.9375rem;
    font-family: 'Figtree', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    color: #111827;
  }

  .select2-container--default .select2-selection--single .select2-selection__rendered {
    padding: 0;
    color: #111827;
  }

  .select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 100%;
    right: 0.75rem;
  }

  .select2-container--default .select2-selection--single .select2-selection__placeholder {
    color: #9ca3af;
  }

  .select2-container--default.select2-container--focus .select2-selection--single,
  .select2-container--default .select2-selection--single:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    outline: none;
    background-color: #ffffff;
  }

  .select2-container--default .select2-selection--single:hover {
    border-color: #d1d5db;
  }

  .select2-container--default .select2-results__option {
    font-size: 0.9375rem;
    font-family: 'Figtree', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    color: #111827;
  }

  .select2-container--default .select2-results__option--highlighted[aria-selected] {
    background-color: #0d6efd;
    color: #ffffff;
  }

  /* Dark mode overrides */
  .dark .select2-container .select2-selection--single {
    background-color: #343a40;
    border-color: #495057;
    color: #e9ecef;
  }

  .dark .select2-container--default .select2-selection--single .select2-selection__rendered {
    color: #e9ecef;
  }

  .dark .select2-container--default .select2-selection--single .select2-selection__placeholder {
    color: #9ca3af;
  }

  .dark .select2-container--default .select2-results__option {
    background-color: #343a40;
    color: #e9ecef;
  }

  .dark .select2-container--default .select2-results__option--highlighted[aria-selected] {
    background-color: #0d6efd;
    color: #ffffff;
  }
  
  .form-control:disabled,
  .form-select:disabled {
    background-color: #f9fafb;
    color: #6b7280;
    cursor: not-allowed;
    opacity: 0.7;
  }
  
  .form-text {
    font-size: 0.8125rem;
    color: #6b7280;
    margin-top: 0.375rem;
    font-family: 'Figtree', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  }
  
  /* Small form labels */
  .form-label.small {
    font-size: 0.8125rem;
    font-weight: 600;
    color: #4b5563;
  }

  /* Dark mode: form block loads after global CSS — explicit overrides for label/contrast */
  .dark .form-label {
    color: #f1f5f9;
  }
  .dark .form-label.small {
    color: #e2e8f0;
  }
  .dark .form-text,
  .dark .form-text.small {
    color: #cbd5e1;
  }
  .dark .card-body .fw-semibold.small.text-uppercase.text-muted {
    color: #94a3b8 !important;
  }
  
  /* Input group enhancements */
  .card-body .form-label:first-child {
    margin-top: 0;
  }
  
  /* Better spacing for form groups */
  .mb-3 {
    margin-bottom: 1.25rem !important;
  }
  
  /* Enhanced datetime input */
  input[type="datetime-local"] {
    position: relative;
  }
  
  input[type="datetime-local"]::-webkit-calendar-picker-indicator {
    cursor: pointer;
    opacity: 0.6;
    transition: opacity 0.2s ease;
  }
  
  input[type="datetime-local"]::-webkit-calendar-picker-indicator:hover {
    opacity: 1;
  }
  
  /* Number input styling */
  input[type="number"] {
    -moz-appearance: textfield;
  }
  
  input[type="number"]::-webkit-inner-spin-button,
  input[type="number"]::-webkit-outer-spin-button {
    opacity: 1;
    cursor: pointer;
  }

  @media (max-width:576px){
    .type-pill {
        align-items: flex-start !important;
    }

  }
@media(min-width:460px){
    .type-toggle-top .toggle-pill-group{
        width:35% !important;
    }
  .type-toggle .toggle-pill-group{
        width:48% !important;
    }
}
  @media(min-width:500px){
    .type-toggle-top .toggle-pill-group{
        width:31% !important;
    }
    .type-toggle .toggle-pill-group{
        width:37% !important;
    }
}
   @media(min-width:575.98px){
    .type-toggle-top .toggle-pill-group{
        width:20% !important;
    }
     .type-toggle .toggle-pill-group{
        width:54% !important;
    }
}
  @media(min-width:767.98px){
    .type-toggle-top .toggle-pill-group{
        width:33% !important;
    }
    .type-toggle .toggle-pill-group{
        width:87% !important;
    }
}

  @media(min-width:991.98px){
    .type-toggle-top .toggle-pill-group{
        width:26% !important;
    }
    .type-toggle .toggle-pill-group{
        width:70% !important;
    }
}
  .type-toggle-top button {
    font-size: 12px !important;
    width: 50% !important;
  }
  .type-toggle .toggle-pill-group {
    min-width: 180px !important;
    
  }
  .type-toggle button {
    font-size: 16px !important;
    width: 50% !important;
  }
  #preview_placeholder {
    /* display: none !important; */
  }
  .dark .prevbtntop{
    border: 1px solid #fff !important;
  }
  .dark .color-picker-display {
    background-color: #343a40;
    color: #e9ecef;
    border-color: #495057;
}
  .dark .toggle-pill:not(.active) {
    background-color: #343a40;
    color: #fff;
}

  /* Colorizer (Design group) — matches modern inputs / color picker */
  .skin-colorizer-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    padding: 0.35rem 0.85rem;
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 0.03em;
    font-family: 'Figtree', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    color: #374151;
    background: linear-gradient(180deg, #ffffff 0%, #f9fafb 100%);
    border: 1.5px solid #e5e7eb;
    border-radius: 999px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.06);
    transition: border-color 0.2s ease, box-shadow 0.2s ease, color 0.2s ease, transform 0.15s ease;
    flex-shrink: 0;
    line-height: 1.2;
  }
  .skin-colorizer-btn:hover {
    color: #1d4ed8;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12), 0 2px 6px rgba(0, 0, 0, 0.06);
  }
  .skin-colorizer-btn:focus-visible {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.18);
  }
  .skin-colorizer-btn:active {
    transform: scale(0.98);
  }
  .skin-colorizer-btn .bi {
    font-size: 0.9rem;
    color: #6366f1;
    transition: color 0.2s ease;
  }
  .skin-colorizer-btn:hover .bi {
    color: #2563eb;
  }
  .dark .skin-colorizer-btn {
    color: #e9ecef;
    background: linear-gradient(180deg, #3d4449 0%, #343a40 100%);
    border-color: #495057;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
  }
  .dark .skin-colorizer-btn:hover {
    color: #93c5fd;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2), 0 2px 6px rgba(0, 0, 0, 0.25);
  }
  .dark .skin-colorizer-btn .bi {
    color: #a5b4fc;
  }
  .dark .skin-colorizer-btn:hover .bi {
    color: #93c5fd;
  }

  .type-toggle-top .toggle-pill-group{
    min-width: 120px !important;
  }
</style>

<div class="py-1" style="border: 0px solid red; display: flex; justify-content: space-between; gap: 0.5rem; padding: inherit;">
  @if(isset($timer))
    {{-- <h2 class="font-semibold text-xl text-gray-800 leading-tight" style="font-size: 1.5rem !important">Edit Counter</h2> --}}
    <a id="form_preview_btn"
       href="{{ $gifPendingEdit ? '#' : route('timers.preview', $timer) }}"
       data-preview-url="{{ route('timers.preview', $timer) }}"
       class="btn btn-outline-secondary border rounded prevbtntop {{ $gifPendingEdit ? 'disabled' : '' }}"
       style="display: flex; align-items: center;"
       @if($gifPendingEdit) title="Processing" tabindex="-1" aria-disabled="true" @else title="Preview counter" @endif
    >Preview Counter</a>
  @else
    {{-- <h2 class="font-semibold text-xl text-gray-800 leading-tight" style="font-size: 1.5rem !important">Create New Counter</h2> --}}
  @endif
  
</div>
<div class="py-4 py-md-3">
    <div class="container-fluid">
        <form id="timer_form" method="POST" action="{{ $formAction }}"
              data-timer-id="{{ $isEdit ? $timer->id : '' }}"
              data-is-edit="{{ $isEdit ? '1' : '0' }}"
              data-has-gif="{{ $isEdit && !empty($timer->gif_url) && !$timer->needsGifRegeneration() ? '1' : '0' }}"
              data-gif-pending="{{ $gifPendingEdit ? '1' : '0' }}"
              data-gif-generation-mode="{{ $gifQueueMode ? 'queue' : 'sync' }}"
              data-timer-status="{{ $isEdit ? $timer->status : 'active' }}"
              data-user-id="{{ $isEdit ? $timer->user_id : Auth::id() }}"
              data-gif-url="{{ $isEdit && !empty($timer->gif_url) ? $timer->gif_url : '' }}"
              data-timer-width="{{ $isEdit ? ($timer->width ?? '') : '' }}"
              data-timer-output-width="{{ $isEdit ? ($timer->skin_overrides['outputWidth'] ?? $timer->skin_overrides['width'] ?? '') : '' }}"
              @if($isEdit)
                  data-original-custom-text="{{ $timer->custom_text ?? '' }}"
                  data-original-text-position="{{ $timer->text_position ?? 'none' }}"
                  data-original-timer-type="{{ $timer->timer_type ?? 'fixed_date' }}"
                  data-original-target-datetime-utc="{{ $timer->target_datetime ? $timer->target_datetime->copy()->utc()->toIso8601String() : '' }}"
                  data-original-duration-seconds="{{ $timer->duration_seconds ?? '' }}"
              @endif
        >
            @csrf
            @if($isEdit)
                @method('PUT')
            @endif
            <input type="hidden" name="client_timezone" id="client_timezone" value="">

            <div class="row g-3">
                <!-- Top skin selector bar -->
                <div class="col-12">
                    <div class="card" style="height:202px">
                        <div class="card-body">
                            <label class="form-label d-block mb-2">Select a Template</label>
                            <input type="hidden" name="skin" id="skin_key" value="{{ $isEdit ? $timer->skin : '' }}" />
                            <div class="position-relative" style="min-height: 102px;">
                                <div class="overflow-hidden">
                                    <div id="skin_scroll_bar" class="d-flex gap-3 flex-nowrap overflow-auto scrollbar-hide py-1 w-100">
                                        <!-- Skins will be injected here -->
                                    </div>
                                </div>
                            </div>
                            <div class="owl-nav d-flex justify-content-between align-items-center">
                              <div class="owl-prev" style="cursor: pointer;">
                                <i class="bi bi-arrow-left-circle me-1"></i> <b>Prev</b>
                              </div>
                              <div class="owl-next" style="cursor: pointer;"><b>Next</b> <i class="bi bi-arrow-right-circle ms-1"></i>
                              </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Left column: all form fields + customization -->
                <div class="col-12 col-md-6">
                    <div class="d-flex flex-column gap-3 h-100">
                        <!-- General -->
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center py-2 collapsible-header" data-target="#general_settings_body">
                                <span class="fw-semibold small text-muted">General Setting</span>
                                <i class="bi bi-chevron-down collapse-icon"></i>
                            </div>
                            <div class="card-body collapsible-body" id="general_settings_body">
                              <div class="col-12 type-toggle-top" style="
    display: flex;
    justify-content: end;
">
                                        <div class="toggle-pill-group" data-toggle-select="status">
                                            <button type="button" class="toggle-pill" data-value="active">Active</button>
                                            <button type="button" class="toggle-pill" data-value="inactive">Inactive</button>
                                        </div>
                                        <select name="status" class="form-select d-none" aria-hidden="true">
                                            <option value="active" {{ ($isEdit && $timer->status === 'active') || !$isEdit ? 'selected' : '' }}>Active</option>
                                            <option value="inactive" {{ $isEdit && $timer->status === 'inactive' ? 'selected' : '' }}>Inactive</option>
                                        </select>
                                    </div>
                                <div class="mb-3">
                                    <label class="form-label" for="timer_title_input">Title</label>
                                    <input type="text" name="title" id="timer_title_input" value="{{ old('title', $timer->title ?? '') }}" class="form-control @error('title') is-invalid @enderror" required aria-describedby="title_field_feedback">
                                    <div class="invalid-feedback d-block {{ $errors->has('title') ? '' : 'd-none' }}" id="title_field_feedback" role="alert">@error('title'){{ $message }}@enderror</div>
                                </div>

                                <div class="row g-3 mt-1">
                                    <label class="form-label">Counter Expires</label>
                                    <div class="col-12 col-lg-6 type-pill type-toggle" style="
    display: flex;
    flex-direction: column;
    align-items: center;">
                                        <div class="toggle-pill-group" data-toggle-select="timer_type">
                                            <button type="button" class="toggle-pill" data-value="fixed_date">On Date</button>
                                            <button type="button" class="toggle-pill" data-value="reload_based">EverGreen</button>
                                        </div>
                                        <select name="timer_type" id="timer_type" class="form-select d-none" aria-hidden="true" onchange="toggleInputs(false)">
                                            <option value="fixed_date" {{ ($isEdit && $timer->timer_type === 'fixed_date') || !$isEdit ? 'selected' : '' }}>Fixed Date</option>
                                            <option value="reload_based" {{ $isEdit && $timer->timer_type === 'reload_based' ? 'selected' : '' }}>Reload Based</option>
                                        </select>
                                    </div>
                                    <div class="col-12 col-lg-6" style="
    display: flex;
    flex-direction: column;
    justify-content: center;
    /* align-items: center; */
">
                                      <div id="fixed_date_input" class="{{ $isEdit && $timer->timer_type !== 'fixed_date' ? 'd-none' : '' }}">
                                    {{-- <label class="form-label">Target DateTime</label> --}}
                                    @php
                                        $oldTarget = old('target_datetime');
                                        $utcIso = ($isEdit && isset($timer) && $timer->target_datetime) ? $timer->target_datetime->copy()->utc()->toIso8601String() : '';
                                        // Create: leave empty so frontend can set "now" in user's local time.
                                        $inputValue = $oldTarget !== null ? $oldTarget : ($isEdit ? '' : '');
                                    @endphp
                                    <input type="datetime-local"
                                           name="target_datetime"
                                           class="form-control"
                                           step="1"
                                           min="{{ now()->format('Y-m-d\\TH:i:s') }}"
                                           value="{{ $inputValue }}"
                                           data-utc="{{ $utcIso }}">
                                           <div class="form-text small">Select the date and time when the counter stops.</div>
                                </div>

                                <div id="duration_input" class="{{ ($isEdit && $timer->timer_type !== 'reload_based') || !$isEdit ? 'd-none' : '' }}">
                                    {{-- <label class="form-label">Duration (seconds)</label> --}}
                                    <input type="number" name="duration_seconds" class="form-control" min="1" max="604800"
                                           value="{{ old('duration_seconds', $timer->duration_seconds ?? '') }}">
                                           <div class="form-text small">Enter how long the counter runs before restarting<br>e.g. Enter 120 for 2 minutes.</div>
                                </div>
                                    </div>
                                </div>

                                {{-- Per-skin visibility toggles (e.g., show Days / Seconds) and global font override --}}
                                @php
                                    $overrides = ($isEdit && isset($timer) && is_array($timer->skin_overrides ?? null)) ? $timer->skin_overrides : [];
                                    $customTextFont = old('customTextFont', $overrides['customTextFont'] ?? '');
                                @endphp
                                <div class="row g-3 mt-2" id="visibility_font_row">
                                    <div class="d-none" id="visibility_toggles_col">
                                        <label class="form-label small mb-1">Days</label>
                                        <div id="visibility_toggles_inner"></div>
                                    </div>
                                    <div class="col-6" id="font_field_col">
                                        <label class="form-label small mb-1">Font</label>
                                        <select name="customTextFont" id="customTextFont" class="form-select form-select-sm" data-saved-value="{{ $customTextFont ?? '' }}">
                                            <option value="">Default</option>
                                            {{-- Populated via JS from /api/fonts --}}
                                        </select>
                                    </div>
                                </div>

                                <div class="mt-3 d-none">
                                    <label class="form-label">Selected Skin</label>
                                    <div class="mt-2 position-relative d-inline-flex align-items-center gap-2 border rounded p-2 bg-light">
                                        <img id="selected_skin_preview" alt="Selected skin preview" class="border rounded bg-white d-none" style="height: 40px; width: auto;" />
                                        <span id="selected_skin_label" class="small"></span>
                                    </div>
                                    <p class="form-text mb-0">Use the skin bar above to change the selected skin.</p>
                                </div>
                            </div>
                        </div>

                        <!-- TEXT ( optional ) -->
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center py-2 collapsible-header" data-target="#text_optional_body">
                                <span class="fw-semibold small text-muted">Heading (optional)</span>
                                <i class="bi bi-chevron-down collapse-icon"></i>
                            </div>
                            <div class="card-body collapsible-body" id="text_optional_body">
                                <div class="row g-3">
                                  <div class="col-12 col-sm-6">
                                    <label class="form-label small mb-1">Custom Text</label>
                                    <input type="text" name="custom_text" class="form-control" maxlength="255" value="{{ old('custom_text', $timer->custom_text ?? '') }}">
                                  </div>
                                  @php
                                      $customTextFontSize = old('customTextFontSize', $overrides['customTextFontSize'] ?? '');
                                      $legacyMargin = $overrides['customTextMarginTop'] ?? 0;
                                      $distanceValue = old('customTextDistance', $overrides['customTextDistance'] ?? $legacyMargin ?? 0);
                                  @endphp

                                    <div class="col-12 col-sm-6">
                                        <label class="form-label small mb-1">Position</label>
                                        <select name="text_position" class="form-select">
                                            <option value="none" {{ ($isEdit && $timer->text_position === 'none') || !$isEdit ? 'selected' : '' }}>None</option>
                                            <option value="top" {{ $isEdit && $timer->text_position === 'top' ? 'selected' : '' }}>Top</option>
                                            <option value="bottom" {{ $isEdit && $timer->text_position === 'bottom' ? 'selected' : '' }}>Bottom</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-12 col-sm-6">
                                        @php
                                            $customTextColor = old(
                                                'customTextColor',
                                                ($isEdit && is_array($timer->skin_overrides) && isset($timer->skin_overrides['customTextColor']))
                                                    ? $timer->skin_overrides['customTextColor']
                                                    : '#000000'
                                            );
                                        @endphp
                                        <label class="form-label small mb-1">Text Color</label>
                                        <div class="modern-color-picker">
                                            <div class="color-picker-swatch" style="--swatch-color: {{ $customTextColor }};"></div>
                                            <div class="color-picker-input-wrapper">
                                                <div class="color-picker-display">
                                                    <span class="color-picker-hex">{{ strtoupper($customTextColor) }}</span>
                                                    <i class="bi bi-palette color-picker-icon"></i>
                                                </div>
                                                <input type="color" name="customTextColor" class="color-picker-native" value="{{ $customTextColor }}">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12 col-sm-6">
                                        <label class="form-label small mb-1">Padding</label>
                                        <input type="number" name="customTextDistance" class="form-control form-control-sm" min="-400" max="400" step="1" value="{{ $distanceValue }}">
                                        <div class="form-text small">Space between the heading and the counter.</div>
                                    </div>
                                    <div class="col-12 col-sm-6">
                                      <label class="form-label small mb-1">Font Size</label>
                                      <input
                                          type="number"
                                          name="customTextFontSize"
                                          class="form-control form-control-sm"
                                          min="8"
                                          max="200"
                                          step="1"
                                          value="{{ $customTextFontSize }}"
                                      >
                                      
                                  </div>
                                </div>
                            </div>
                        </div>

                        <!-- Template (grouped fields) -->
                        <div class="card d-none" id="template_card">
                            <div class="card-header d-flex justify-content-between align-items-center py-2 collapsible-header" data-target="#template_body">
                                <span class="fw-semibold small text-muted">Template</span>
                                <i class="bi bi-chevron-down collapse-icon"></i>
                            </div>
                            <div class="card-body collapsible-body" id="template_body" style="padding:1rem">
                                <!-- Dynamic skin customization fields -->
                                <div id="skin_overrides_section" class="d-none">
                                    <div id="skin_fields" class="row g-3"></div>
                                </div>

                                <!-- Static customization fields (timer-level but displayed here) -->
                                <!-- Text & Animation (only animation-related) -->
                                <div class="card border shadow-sm mb-3 d-none">
                                    <div class="card-header bg-light py-2 collapsible-header" data-target="#skin_animation_group">
                                        <span class="text-muted fw-semibold small">Text & Animation</span>
                                        <i class="bi bi-chevron-down collapse-icon float-end"></i>
                                    </div>
                                    <div class="card-body collapsible-body" id="skin_animation_group">
                                        <div class="row g-3">
                                            <div class="col-12 col-sm-6">
                                                <label class="form-label small mb-1">Animated Seconds</label>
                                                <input type="number"
                                                       name="animation_frame_limit"
                                                       class="form-control"
                                                       min="60"
                                                       max="60"
                                                       value="{{ old('animation_frame_limit', $timer->animation_frame_limit ?? 60) }}">
                                                <div class="form-text small">Animated duration for generated GIF (10-60 seconds).</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <input type="hidden" name="skin_overrides" id="skin_overrides_input" value="{{ $isEdit ? json_encode($timer->skin_overrides ?? []) : '' }}" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right column: live preview -->
                <div class="col-12 col-md-6">
                    <div class="card sticky-preview">
                        <div class="card-body d-flex flex-column">
                            {{-- <h3 class="h6 fw-semibold mb-3">Preview</h3> --}}
                            <div id="timer_preview" class="d-flex align-items-center justify-content-center rounded mb-3" style="min-height: 260px;">
                                <div id="preview_loader" class="text-center text-muted d-none">
                                    <div class="spinner-border text-primary mb-2" role="status" style="width: 3rem; height: 3rem;">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <p class="small mb-0">Generating preview...</p>
                                </div>
                                <img id="preview_frame" src="" class="img-fluid d-none" style="max-height: 400px; object-fit: contain;" alt="Timer preview">
                                <img id="preview_gif" src="" class="img-fluid d-none" style="max-height: 400px; object-fit: contain;" alt="Timer GIF">
                                <div id="preview_placeholder" class="text-center text-muted">
                                    <svg class="mb-2" width="48" height="48" fill="currentColor" viewBox="0 0 16 16">
                                        <path d="M8 3.993c-2.21 0-4 1.79-4 4 0 .705.183 1.368.504 1.944l-.837.837A5.002 5.002 0 1 1 13 8a4.978 4.978 0 0 1-1.944.504l-.837-.837c.576-.321 1.239-.504 1.944-.504 2.21 0 4 1.79 4 4s-1.79 4-4 4-4-1.79-4-4c0-.705.183-1.368.504-1.944l-.837-.837A3.993 3.993 0 0 0 8 3.993z"/>
                                    </svg>
                                    <p class="small mb-0">Timer preview will appear here</p>
                                    <p class="text-muted small">Configure your counter settings to see the preview</p>
                                </div>
                            </div>
                            <div class="d-flex gap-2" style="justify-content: space-between;">
                              <button id="reset_design_btn" type="button" class="btn btn-outline-secondary" disabled>Reset Design</button>
                              <button id="save_btn" type="button" class="btn btn-primary">Save Counter</button>
                            </div>
                            
                            
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="col-12">
                    <div class="d-flex gap-2 flex-wrap">
                        {{-- Additional actions can go here if needed; primary Generate GIF button is in the top header --}}
                    </div>
                </div>

            </div>
        </form>
    </div>
</div>

{{-- Select2 assets for searchable font dropdown --}}
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
  window.COUNTER_FONT_BASE_URL = "{{ rtrim(asset('assets/fonts'), '/') }}";
</script>

<script src="{{ asset('assets/generator/js/main_script.js') }}" defer></script>
