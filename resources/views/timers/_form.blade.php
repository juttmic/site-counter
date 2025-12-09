@php
    $isEdit = isset($timer);
    $formAction = $isEdit ? route('timers.update', $timer) : route('timers.store');
    $formMethod = $isEdit ? 'PUT' : 'POST';
@endphp
<style>
  .mine {
    padding: 3px !important;
    position: relative;
    border: 2px solid transparent;
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
    top: 20px;
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
</style>
<div class="py-1" style="border: 0px solid red; display: flex; justify-content: flex-end; gap: 0.5rem;">
  @if(isset($timer))
    <a href="{{ route('timers.preview', $timer) }}" class="btn btn-outline-secondary border rounded">Preview Timer</a>
  @endif
  <button id="save_btn" type="button" class="btn btn-primary">Generate GIF</button>
</div>
<div class="py-4 py-md-3">
    <div class="container-fluid">
        <form id="timer_form" method="POST" action="{{ $formAction }}" data-timer-id="{{ $isEdit ? $timer->id : '' }}" data-is-edit="{{ $isEdit ? '1' : '0' }}" data-has-gif="{{ $isEdit && !empty($timer->gif_url) ? '1' : '0' }}" data-timer-status="{{ $isEdit ? $timer->status : 'active' }}">
            @csrf
            @if($isEdit)
                @method('PUT')
            @endif

            <div class="row g-3">
                <!-- Top skin selector bar -->
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <label class="form-label d-block mb-2">Choose a Skin</label>
                            <input type="hidden" name="skin" id="skin_key" value="{{ $isEdit ? $timer->skin : '' }}" />
                            <div class="position-relative" style="min-height: 120px;">
                                <div class="overflow-hidden">
                                    <div id="skin_scroll_bar" class="d-flex gap-3 flex-nowrap overflow-auto scrollbar-hide py-1 w-100">
                                        <!-- Skins will be injected here -->
                                    </div>
                                </div>
                            </div>
                            <div class="owl-nav d-flex justify-content-between align-items-center mt-2">
                              <div class="owl-prev" style="cursor: pointer;">
                                <i class="bi bi-arrow-left-circle me-1"></i> Prev
                              </div>
                              <div class="owl-next" style="cursor: pointer;">Next <i class="bi bi-arrow-right-circle ms-1"></i>
                              </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Left column: all form fields + customization -->
                <div class="col-12 col-md-6">
                    <div class="d-flex flex-column gap-3 h-100">
                        <!-- General settings -->
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center py-2 collapsible-header" data-target="#general_settings_body">
                                <span class="fw-semibold small text-uppercase text-muted">General settings</span>
                                <span class="collapse-icon small">−</span>
                            </div>
                            <div class="card-body collapsible-body" id="general_settings_body">
                                <div class="mb-3">
                                    <label class="form-label">Title</label>
                                    <input type="text" name="title" value="{{ old('title', $timer->title ?? '') }}" class="form-control" required>
                                </div>

                                <div class="row g-3 mt-1">
                                    <div class="col-12 col-sm-6">
                                        <label class="form-label">Status</label>
                                        <select name="status" class="form-select">
                                            <option value="active" {{ ($isEdit && $timer->status === 'active') || !$isEdit ? 'selected' : '' }}>Active</option>
                                            <option value="inactive" {{ $isEdit && $timer->status === 'inactive' ? 'selected' : '' }}>Inactive</option>
                                        </select>
                                    </div>
                                    <div class="col-12 col-sm-6">
                                        <label class="form-label">Timer Type</label>
                                        <select name="timer_type" id="timer_type" class="form-select" onchange="toggleInputs()">
                                            <option value="fixed_date" {{ ($isEdit && $timer->timer_type === 'fixed_date') || !$isEdit ? 'selected' : '' }}>Fixed Date</option>
                                            <option value="reload_based" {{ $isEdit && $timer->timer_type === 'reload_based' ? 'selected' : '' }}>Reload Based</option>
                                        </select>
                                    </div>
                                </div>

                                <div id="fixed_date_input" class="mt-3 {{ $isEdit && $timer->timer_type !== 'fixed_date' ? 'd-none' : '' }}">
                                    <label class="form-label">Target DateTime</label>
                                    <input type="datetime-local" name="target_datetime" class="form-control"
                                           value="{{ old('target_datetime', $isEdit && $timer->target_datetime ? $timer->target_datetime->format('Y-m-d\\TH:i') : ($isEdit ? '' : now()->addDays(3)->format('Y-m-d\\TH:i'))) }}">
                                </div>

                                <div id="duration_input" class="mt-3 {{ ($isEdit && $timer->timer_type !== 'reload_based') || !$isEdit ? 'd-none' : '' }}">
                                    <label class="form-label">Duration (seconds)</label>
                                    <input type="number" name="duration_seconds" class="form-control" min="1" max="604800"
                                           value="{{ old('duration_seconds', $timer->duration_seconds ?? '') }}">
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

                        <!-- Customize skin (grouped fields) -->
                        <div class="card flex-grow-1">
                            <div class="card-header d-flex justify-content-between align-items-center py-2 collapsible-header" data-target="#customize_skin_body">
                                <span class="fw-semibold small text-uppercase text-muted">Customize skin</span>
                                <span class="collapse-icon small">−</span>
                            </div>
                            <div class="card-body collapsible-body" id="customize_skin_body">
                                <!-- Static customization fields (timer-level but displayed here) -->
                                <div class="card border shadow-sm mb-3">
                                    <div class="card-header bg-light py-2 collapsible-header" data-target="#skin_custom_text_group">
                                        <span class="text-uppercase text-muted fw-semibold small">Text & Animation</span>
                                        <span class="collapse-icon small float-end">−</span>
                                    </div>
                                    <div class="card-body collapsible-body" id="skin_custom_text_group">
                                        <div class="row g-3">
                                            <div class="col-12 col-sm-6">
                                                <label class="form-label small mb-1">Text Position</label>
                                                <select name="text_position" class="form-select">
                                                    <option value="none" {{ ($isEdit && $timer->text_position === 'none') || !$isEdit ? 'selected' : '' }}>None</option>
                                                    <option value="top" {{ $isEdit && $timer->text_position === 'top' ? 'selected' : '' }}>Top</option>
                                                    <option value="bottom" {{ $isEdit && $timer->text_position === 'bottom' ? 'selected' : '' }}>Bottom</option>
                                                </select>
                                            </div>
                                            <div class="col-12 col-sm-6">
                                                <label class="form-label small mb-1">Custom Text {{ !$isEdit ? '(optional)' : '' }}</label>
                                                <input type="text" name="custom_text" class="form-control" maxlength="255" value="{{ old('custom_text', $timer->custom_text ?? '') }}">
                                            </div>
                                            <div class="col-12 col-sm-6">
                                                <label class="form-label small mb-1">Animated Seconds</label>
                                                <input type="number"
                                                       name="animation_frame_limit"
                                                       class="form-control"
                                                       min="10"
                                                       max="60"
                                                       value="{{ old('animation_frame_limit', $timer->animation_frame_limit ?? 20) }}">
                                                <div class="form-text small">Animated duration for generated GIF (10-60 seconds).</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Dynamic skin customization fields -->
                                <div id="skin_overrides_section" class="mt-2 d-none">
                                    <div id="skin_fields" class="row g-3"></div>
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
                            <h3 class="h6 fw-semibold mb-3">Preview</h3>
                            <div id="timer_preview" class="d-flex align-items-center justify-content-center rounded border bg-light mb-3" style="min-height: 260px;">
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
                                    <p class="text-muted small">Configure your timer settings to see the preview</p>
                                </div>
                            </div>
                            <div id="embed_section" class="mt-3 {{ $isEdit && isset($timer->gif_url) && $timer->gif_url && $timer->status === 'active' ? '' : 'd-none' }}">
                                <h4 class="h6 fw-semibold mb-2">Embed Code</h4>
                                <div class="d-flex gap-2">
                                    @if($isEdit && isset($timer->gif_url) && $timer->gif_url && $timer->status === 'active')
                                        @php($embedUrl = url('/gettimer/'.$timer->user_id.'/'.pathinfo($timer->gif_url, PATHINFO_FILENAME)).'.gif')
                                        <input id="embed_input" type="text" class="form-control form-control-sm" value='<img src="{{ $embedUrl }}" alt="Countdown Timer">' readonly>
                                    @else
                                        <input id="embed_input" type="text" class="form-control form-control-sm" value="" readonly>
                                    @endif
                                    <button id="embed_copy_btn" type="button" class="btn btn-outline-secondary btn-sm">Copy</button>
                                </div>
                            </div>
                            <div id="embed_notice" class="mt-3 alert alert-warning py-2 px-3 small {{ $isEdit && isset($timer->status) && $timer->status !== 'active' ? '' : 'd-none' }}">
                                Activate this timer to generate a shareable embed code.
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

<script>
// Font Awesome (for the change icon)
if (!document.getElementById('fa-cdn')) {
  const link = document.createElement('link');
  link.id = 'fa-cdn';
  link.rel = 'stylesheet';
  link.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css';
  document.head.appendChild(link);
}

function toggleInputs() {
  const type = document.getElementById('timer_type').value;
  document.getElementById('fixed_date_input').classList.toggle('d-none', type !== 'fixed_date');
  document.getElementById('duration_input').classList.toggle('d-none', type !== 'reload_based');
}
document.addEventListener('DOMContentLoaded', toggleInputs);

// Skins loading and dynamic form
async function fetchJson(url) {
  const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
  if (!res.ok) throw new Error('Failed to load ' + url);
  return await res.json();
}

// Bootstrap modal hooks for skin selection

async function loadSkinsIntoModal() {
  const grid = document.getElementById('skin_modal_grid');
  if (!grid) return;
  grid.innerHTML = '<div class="col-12 text-center text-muted small">Loading skins...</div>';
  try {
    const skins = await fetchJson('/api/skins');
    const current = document.getElementById('skin_key').value;
    grid.innerHTML = '';
    skins.forEach(s => {
      const col = document.createElement('div');
      col.className = 'col-6 col-md-4 col-lg-3';
      const card = document.createElement('button');
      card.type = 'button';
      card.className = 'w-100 border rounded p-2 bg-white text-start';
      card.innerHTML = `
        <div class="d-flex align-items-center justify-content-center bg-light border rounded" style="height: 96px;">
          <img src="${s.preview_url}" alt="${s.name}" class="img-fluid" style="max-height: 96px; object-fit: contain;">
        </div>
        <div class="mt-2 small text-truncate">${s.name}</div>
      `;
      if (s.key === current) card.classList.add('border-primary');
      card.addEventListener('click', async () => {
        await selectSkin(s.key);
        const modalEl = document.getElementById('skinSelectModal');
        if (modalEl) {
          const inst = bootstrap.Modal.getInstance(modalEl);
          if (inst) inst.hide();
        }
      });
      col.appendChild(card);
      grid.appendChild(col);
    });
  } catch (e) {
    grid.innerHTML = '<div class="col-12 text-center text-danger small">Failed to load skins</div>';
  }
}

// Horizontal skin selector bar (top of form)
async function loadSkinsIntoScrollBar() {
  const bar = document.getElementById('skin_scroll_bar');
  if (!bar) return;
  bar.innerHTML = '<span class="text-muted small">Loading skins...</span>';

  try {
    const skins = await fetchJson('/api/skins');
    const current = document.getElementById('skin_key').value;
    bar.innerHTML = '';

    // Define helper functions before creating cards
    const getCardWidth = () => {
      const firstCard = bar.querySelector('.skin-card');
      if (!firstCard) return 296; // fallback: 280px card + 16px gap
      // Get actual rendered width including padding and border
      const cardRect = firstCard.getBoundingClientRect();
      const cardWidth = cardRect.width;
      // Get gap from computed style of the container (gap-3 = 1rem = 16px in Bootstrap 5)
      const barStyle = window.getComputedStyle(bar);
      const gap = parseFloat(barStyle.gap) || 16; // fallback to 16px if not found
      return cardWidth + gap;
    };

    skins.forEach(s => {
      const wrapper = document.createElement('div');
      wrapper.className = 'skin-card mine d-flex flex-column align-items-center text-center rounded bg-white flex-shrink-0';
      wrapper.style.width = '280px';
      wrapper.style.cursor = 'pointer';
      wrapper.dataset.skinKey = s.key;

      const preview = document.createElement('div');
      preview.className = 'd-flex align-items-center justify-content-center rounded w-100';
      preview.style.height = '96px';
      preview.style.position = 'relative';

      const img = document.createElement('img');
      img.src = s.preview_url;
      img.alt = s.name;
      img.className = 'img-fluid';
      img.style.maxHeight = '92px';
      img.style.objectFit = 'contain';
      preview.appendChild(img);

      // Create checkmark icon
      const checkmark = document.createElement('i');
      checkmark.className = 'bi bi-check-circle-fill skin-checkmark';
      if (s.key === current) {
        checkmark.classList.add('selected-checkmark');
        wrapper.classList.add('selected-skin');
      }
      preview.appendChild(checkmark);

      // const name = document.createElement('div');
      // name.className = 'small text-truncate w-100';
      // name.textContent = s.name;

      wrapper.appendChild(preview);
      // wrapper.appendChild(name);

      wrapper.addEventListener('click', async () => {
        // Clear previous selection
        bar.querySelectorAll('.skin-card.selected-skin').forEach(el => {
          el.classList.remove('selected-skin');
          const prevCheckmark = el.querySelector('.skin-checkmark');
          if (prevCheckmark) {
            prevCheckmark.classList.remove('selected-checkmark');
          }
        });

        wrapper.classList.add('selected-skin');
        const checkmark = wrapper.querySelector('.skin-checkmark');
        if (checkmark) {
          checkmark.classList.add('selected-checkmark');
        }
        
        // Scroll selected card into view
        setTimeout(() => {
          const cardRect = wrapper.getBoundingClientRect();
          const barRect = bar.getBoundingClientRect();
          const containerWidth = bar.clientWidth;
          
          // Check if card is fully visible
          const cardLeft = cardRect.left - barRect.left;
          const cardRight = cardLeft + cardRect.width;
          
          if (cardLeft < 0) {
            // Card is cut off on the left, scroll to show it
            const targetScroll = bar.scrollLeft + cardLeft - 12;
            bar.scrollTo({ left: Math.max(0, targetScroll), behavior: 'smooth' });
          } else if (cardRight > containerWidth) {
            // Card is cut off on the right, scroll to show it
            const targetScroll = bar.scrollLeft + (cardRight - containerWidth) + 12;
            bar.scrollTo({ left: Math.min(targetScroll, bar.scrollWidth - containerWidth), behavior: 'smooth' });
          }
        }, 100);
        
        await selectSkin(s.key);
      });

      // Hover effect is handled by CSS

      bar.appendChild(wrapper);
    });

    // Wire up left/right arrow buttons to scroll the skin list with snap
    const prevBtn = document.querySelector('.owl-prev');
    const nextBtn = document.querySelector('.owl-next');

    const scrollToCard = (direction) => {
      if (!bar || bar.children.length === 0) return;
      
      const cardWidth = getCardWidth();
      const containerWidth = bar.clientWidth;
      const currentScroll = bar.scrollLeft;
      
      // Calculate how many complete cards fit in the viewport
      const cardsPerView = Math.floor(containerWidth / cardWidth);
      
      let targetScroll;
      if (direction === 'next') {
        // Scroll forward by one card width to show next card(s)
        targetScroll = currentScroll + cardWidth;
      } else {
        // Scroll backward by one card width to show previous card(s)
        targetScroll = currentScroll - cardWidth;
      }
      
      // Snap to card boundary
      targetScroll = Math.round(targetScroll / cardWidth) * cardWidth;
      
      // Ensure we don't scroll beyond boundaries
      const maxScroll = Math.max(0, bar.scrollWidth - containerWidth);
      targetScroll = Math.max(0, Math.min(targetScroll, maxScroll));
      
      bar.scrollTo({ left: targetScroll, behavior: 'smooth' });
    };

    const scrollToSelectedCard = () => {
      const selectedCard = bar.querySelector('.skin-card.selected-skin');
      if (!selectedCard) return;
      
      const cardWidth = getCardWidth();
      const containerWidth = bar.clientWidth;
      const cardRect = selectedCard.getBoundingClientRect();
      const barRect = bar.getBoundingClientRect();
      
      // Check if card is already fully visible
      const cardLeft = cardRect.left - barRect.left + bar.scrollLeft;
      const cardRight = cardLeft + cardRect.width;
      const visibleLeft = bar.scrollLeft;
      const visibleRight = bar.scrollLeft + containerWidth;
      
      // If card is not fully visible, scroll to show it
      if (cardLeft < visibleLeft) {
        // Card is cut off on the left, scroll to show it
        bar.scrollTo({ left: Math.max(0, cardLeft - 12), behavior: 'smooth' });
      } else if (cardRight > visibleRight) {
        // Card is cut off on the right, scroll to show it
        const targetScroll = cardRight - containerWidth + 12;
        bar.scrollTo({ left: Math.min(targetScroll, bar.scrollWidth - containerWidth), behavior: 'smooth' });
      }
    };

    const updateArrowState = () => {
      if (!bar) return;
      const containerWidth = bar.clientWidth;
      const maxScrollLeft = bar.scrollWidth - containerWidth;
      const atStart = bar.scrollLeft <= 1; // small tolerance
      const atEnd = bar.scrollLeft >= maxScrollLeft - 1; // small tolerance
      
      if (prevBtn) {
        if (atStart) {
          prevBtn.classList.add('disabled');
        } else {
          prevBtn.classList.remove('disabled');
        }
      }
      if (nextBtn) {
        if (atEnd) {
          nextBtn.classList.add('disabled');
        } else {
          nextBtn.classList.remove('disabled');
        }
      }
    };

    // Handle snap scrolling after manual scroll (touch/mouse drag)
    let scrollTimeout;
    let isScrolling = false;
    
    bar.addEventListener('scroll', () => {
      clearTimeout(scrollTimeout);
      updateArrowState();
      
      // Only snap if user manually scrolled (not programmatic scroll)
      if (!isScrolling) {
        scrollTimeout = setTimeout(() => {
          // Snap to nearest card boundary
          const cardWidth = getCardWidth();
          const currentScroll = bar.scrollLeft;
          const snappedScroll = Math.round(currentScroll / cardWidth) * cardWidth;
          
          isScrolling = true;
          bar.scrollTo({ left: snappedScroll, behavior: 'smooth' });
          setTimeout(() => { isScrolling = false; }, 300);
        }, 150);
      }
    });

    if (prevBtn) {
      prevBtn.addEventListener('click', () => {
        isScrolling = true;
        scrollToCard('prev');
        setTimeout(() => { isScrolling = false; }, 300);
      });
    }
    if (nextBtn) {
      nextBtn.addEventListener('click', () => {
        isScrolling = true;
        scrollToCard('next');
        setTimeout(() => { isScrolling = false; }, 300);
      });
    }

    // Initialize arrow state and scroll to selected card
    setTimeout(() => {
      updateArrowState();
      scrollToSelectedCard();
    }, 200);
    
    // Update on window resize
    window.addEventListener('resize', () => {
      updateArrowState();
      scrollToSelectedCard();
    });
  } catch (e) {
    bar.innerHTML = '<span class="text-danger small">Failed to load skins</span>';
  }
}

let currentSkinMetadata = null;
let currentHiddenOverrides = {};
let lastSkinOverrides = {};
let gifDirty = false;              // true = changes exist that are NOT in the latest GIF
let suppressNextDirtyFlag = false; // used for initial auto-preview so it doesn't mark dirty
let loadedSkins = new Set();       // Track which skins have been loaded (for new timers only)
let appliedFormDefaults = {};     // Track formDefaults that were applied (to ensure they're saved)

function setupCollapsibles(root) {
  const scope = root || document;
  scope.querySelectorAll('.collapsible-header').forEach(header => {
    if (header.dataset.collapsibleBound === '1') return;
    const targetSelector = header.dataset.target;
    const body = targetSelector ? document.querySelector(targetSelector) : header.nextElementSibling;
    if (!body) return;
    const icon = header.querySelector('.collapse-icon');

    header.style.cursor = 'pointer';
    header.addEventListener('click', () => {
      const isHidden = body.classList.toggle('d-none');
      if (icon) {
        icon.textContent = isHidden ? '+' : '−';
      }
    });
    header.dataset.collapsibleBound = '1';
  });
}

function renderFields(metadata, savedOverrides = {}) {
  currentSkinMetadata = metadata;
  currentHiddenOverrides = {};
  const fieldsWrap = document.getElementById('skin_fields');
  const defaults = metadata.defaults || {};
  const fields = Array.isArray(metadata.fields) ? metadata.fields : [];
  fieldsWrap.innerHTML = '';
  
  // Fields to hide but use defaults for
  // Note: accentColor is NOT hidden - it's needed for controlling tile/box/ring colors
  const hiddenFields = new Set(['fontPath', 'font', 'showDays', 'showHours', 'showMinutes', 'showSeconds', 'layout', 'animation']);
  hiddenFields.forEach(key => {
    if (savedOverrides[key] !== undefined) {
      currentHiddenOverrides[key] = savedOverrides[key];
    }
  });
  
  // Filter out hidden fields
  const visibleFields = fields.filter(field => !hiddenFields.has(field.key));
  
  document.getElementById('skin_overrides_section').classList.toggle('d-none', visibleFields.length === 0);

  const grouped = [
    { key: 'overall', title: 'Overall settings', fields: [] },
    { key: 'timer', title: 'Timer settings', fields: [] },
    { key: 'custom', title: 'Custom text', fields: [] },
    { key: 'final', title: 'Final screen', fields: [] },
    { key: 'extra', title: 'Extra options', fields: [] },
  ];
  const groupMap = grouped.reduce((acc, group) => {
    acc[group.key] = group;
    return acc;
  }, {});

  const smallKeys = new Set(['fontSize','separator','layout','animation','showDays','showHours','showMinutes','showSeconds','width','height','outputWidth','outputHeight']);
  const marginKeys = new Set(['customTextMarginTop','customTextMarginBottom','textMarginTop','textMarginBottom','marginTop','marginBottom','labelMarginTop','labelMarginBottom']);
  const customKeys = new Set(['customtext','custom_text','textposition','text_position','customtextcolor','customtextmargintop','customtextmarginbottom']);
  const timerKeys = new Set(['showdays','showhours','showminutes','showseconds','separator','layout','animation','digitspacing','countdirection','refreshrate','pulsespeed']);
  const finalKeys = new Set(['finalmessage','finalbackground','finaltextcolor','finalscreentext','finalscreencolor']);
  const extraKeys = new Set(['shadowcolor','glowcolor','blurstrength','outlinecolor','outlinewidth']);

  const determineGroup = (field) => {
    const explicit = (field.group || '').toLowerCase();
    if (explicit && groupMap[explicit]) {
      return groupMap[explicit];
    }
    const key = (field.key || '').toLowerCase();
    if (customKeys.has(key)) return groupMap.custom;
    if (finalKeys.has(key)) return groupMap.final;
    if (timerKeys.has(key)) return groupMap.timer;
    if (extraKeys.has(key)) return groupMap.extra;
    return groupMap.overall;
  };

  const createFieldInput = (field, value) => {
    const key = field.key;
    const label = field.label || key;
    const type = field.type || 'text';
    const container = document.createElement('div');
    const isSmall = type === 'color' || type === 'boolean' || smallKeys.has(key);
    const isMarginField = marginKeys.has(key);
    if (isMarginField) {
      container.className = 'col-12 col-sm-4 col-md-3';
    } else {
      container.className = isSmall ? 'col-12 col-sm-6 col-md-4' : 'col-12';
    }
    container.innerHTML = `<label class="form-label small mb-1">${label}</label>`;
    let input;
    if (type === 'color') {
      // Create modern color picker wrapper
      const colorWrapper = document.createElement('div');
      colorWrapper.className = 'modern-color-picker';
      
      // Color swatch
      const swatch = document.createElement('div');
      swatch.className = 'color-picker-swatch';
      const colorValue = /^#/.test(value) ? value : (defaults[key] || '#000000');
      swatch.style.setProperty('--swatch-color', colorValue);
      
      // Hidden native color input
      input = document.createElement('input');
      input.type = 'color';
      input.value = colorValue;
      input.className = 'color-picker-native';
      input.setAttribute('data-key', key);
      
      // Display wrapper
      const displayWrapper = document.createElement('div');
      displayWrapper.className = 'color-picker-input-wrapper';
      
      const display = document.createElement('div');
      display.className = 'color-picker-display';
      
      const hexSpan = document.createElement('span');
      hexSpan.className = 'color-picker-hex';
      hexSpan.textContent = colorValue.toUpperCase();
      
      const icon = document.createElement('i');
      icon.className = 'bi bi-palette color-picker-icon';
      
      display.appendChild(hexSpan);
      display.appendChild(icon);
      displayWrapper.appendChild(display);
      displayWrapper.appendChild(input);
      
      // Update display when color changes
      const updateDisplay = () => {
        const newColor = input.value;
        swatch.style.setProperty('--swatch-color', newColor);
        hexSpan.textContent = newColor.toUpperCase();
      };
      
      input.addEventListener('input', updateDisplay);
      input.addEventListener('change', updateDisplay);
      
      // Make display clickable
      display.addEventListener('click', () => input.click());
      swatch.addEventListener('click', () => input.click());
      
      colorWrapper.appendChild(swatch);
      colorWrapper.appendChild(displayWrapper);
      
      // Replace container content
      container.innerHTML = `<label class="form-label small mb-1">${label}</label>`;
      container.appendChild(colorWrapper);
      
      // Store reference for sync and add event listener
      input.dataset.key = key;
      input.addEventListener('change', syncOverrides);
      return container;
    } else if (type === 'number') {
      input = document.createElement('input');
      input.type = 'number';
      if (field.min != null) input.min = field.min;
      if (field.max != null) input.max = field.max;
      if (field.step != null) input.step = field.step;
      input.value = (value ?? '');
      input.className = 'form-control' + (isMarginField ? ' form-control-sm' : '');
    } else if (type === 'boolean') {
      input = document.createElement('input');
      input.type = 'checkbox';
      input.checked = !!value;
      input.className = 'form-check-input';
    } else if (type === 'select') {
      input = document.createElement('select');
      input.className = 'form-select';
      (field.options || []).forEach(opt => {
        const o = document.createElement('option');
        o.value = opt; o.textContent = opt; if (opt === value) o.selected = true; input.appendChild(o);
      });
    } else {
      input = document.createElement('input');
      input.type = 'text';
      if (field.maxLength != null) input.maxLength = field.maxLength;
      input.value = value ?? '';
      input.className = 'form-control';
    }
    input.dataset.key = key;
    input.addEventListener('change', syncOverrides);
    container.appendChild(input);
    return container;
  };

  visibleFields.forEach(field => {
    const group = determineGroup(field) || groupMap.overall;
    const value = (savedOverrides[field.key] !== undefined) ? savedOverrides[field.key] : defaults[field.key];
    group.fields.push({ field, value });
  });

  grouped.forEach(group => {
    if (!group.fields.length) return;
    const col = document.createElement('div');
    col.className = 'col-12';
    const card = document.createElement('div');
    card.className = 'card border shadow-sm mb-3';
    const header = document.createElement('div');
    const bodyId = `skin_group_${group.key}`;
    header.className = 'card-header bg-light py-2 collapsible-header';
    header.dataset.target = `#${bodyId}`;
    header.innerHTML = `<span class="text-uppercase text-muted fw-semibold small">${group.title}</span><span class="collapse-icon small float-end">−</span>`;
    const body = document.createElement('div');
    body.className = 'card-body collapsible-body';
    body.id = bodyId;
    const row = document.createElement('div');
    row.className = 'row g-3';
    group.fields.forEach(({ field, value }) => {
      row.appendChild(createFieldInput(field, value));
    });
    body.appendChild(row);
    card.appendChild(header);
    card.appendChild(body);
    col.appendChild(card);
    fieldsWrap.appendChild(col);
  });

  // Sync initial state to hidden input
  syncOverrides();
  // Enable collapsible behavior for newly rendered groups
  setupCollapsibles(fieldsWrap);
}

function syncOverrides() {
  const fieldsWrap = document.getElementById('skin_fields');
  const inputs = fieldsWrap.querySelectorAll('[data-key]');
  const data = { ...(currentHiddenOverrides || {}) };
  const defaults = currentSkinMetadata?.defaults || {};
  
  // Fields that are hidden but should always use defaults
  // Note: accentColor is NOT hidden - it's needed for controlling tile/box/ring colors
  const hiddenFields = new Set(['fontPath', 'font', 'showDays', 'showHours', 'showMinutes', 'showSeconds', 'layout', 'animation']);
  
  // For hidden fields:
  // - Font fields: don't store (use skin default)
  // - show* fields: always set to true (always checked)
  hiddenFields.forEach(key => {
    if (key.startsWith('show')) {
      // Always set show* fields to true (always checked)
      data[key] = true;
    }
    // For font fields, don't store anything - will use skin default
  });
  
  inputs.forEach(el => {
    const k = el.dataset.key;
    let val;
    if (el.type === 'checkbox') {
      val = !!el.checked;
    } else if (el.type === 'number') {
      val = el.value === '' ? null : Number(el.value);
    } else {
      val = el.value;
    }
    
    // Store if different from default OR if it's an applied formDefault
    // This ensures formDefaults are always saved, even if they match original defaults
    if (val !== defaults[k] || appliedFormDefaults[k] !== undefined) {
      data[k] = val;
    }
  });
  
  document.getElementById('skin_overrides_input').value = JSON.stringify(data);
  // Remember latest overrides so switching skins applies current user settings
  lastSkinOverrides = data;
  // Generate preview when overrides change
  generatePreviewFrame();
}

let previewTimeout = null;

async function generatePreviewFrame() {
  const form = document.getElementById('timer_form');
  if (!form) return;

  // Clear any pending preview generation
  if (previewTimeout) {
    clearTimeout(previewTimeout);
  }

  // Debounce: wait 300ms after last change before generating preview
  previewTimeout = setTimeout(async () => {
    const previewWrap = document.getElementById('timer_preview');
    const loader = document.getElementById('preview_loader');
    const frameImg = document.getElementById('preview_frame');
    const gifImg = document.getElementById('preview_gif');
    const placeholder = document.getElementById('preview_placeholder');

    // Hide all, show loader
    frameImg.classList.add('d-none');
    gifImg.classList.add('d-none');
    placeholder.classList.add('d-none');
    loader.classList.remove('d-none');

    try {
      const formData = new FormData(form);
      formData.append('skin', document.getElementById('skin_key').value || '');
      formData.append('skin_overrides', document.getElementById('skin_overrides_input').value || '{}');

      // Build URL with optional timer ID
      const timerId = form.dataset.timerId;
      const previewUrl = timerId ? `/timers/${timerId}/preview-frame` : '/timers/preview-frame';
console.log(formData);
formData.set('_method', 'POST');
      const response = await fetch(previewUrl, {
        method: 'POST',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || formData.get('_token')
        },
        body: formData
      });

      if (!response.ok) {
        throw new Error('Failed to generate preview');
      }

      const newTimerId = response.headers.get('X-Timer-Id');
      if (newTimerId) {
        form.dataset.timerId = newTimerId;
      }

      // Response body is plain text containing the URL to the saved PNG.
      // On local, PHP warnings may be printed before the URL, so take the last non-empty line.
      const text = await response.text();
      const lines = text.split('\n').map(l => l.trim()).filter(Boolean);
      const url = lines[lines.length - 1];

      // Hide loader, show frame
      loader.classList.add('d-none');
      frameImg.src = url;
      frameImg.classList.remove('d-none');

      // Mark GIF as out-of-date, unless this was the initial suppressed preview
      if (!suppressNextDirtyFlag) {
        gifDirty = true;
      }
      suppressNextDirtyFlag = false;
    } catch (e) {
      console.error('Preview generation error:', e);
      loader.classList.add('d-none');
      placeholder.classList.remove('d-none');
    }
  }, 300);
}

async function selectSkin(key) {
  document.getElementById('skin_key').value = key;
  document.getElementById('selected_skin_label').textContent = key;
  
  // Update visual selection in scroll bar
  const bar = document.getElementById('skin_scroll_bar');
  if (bar) {
    bar.querySelectorAll('.skin-card').forEach(card => {
      const checkmark = card.querySelector('.skin-checkmark');
      if (card.dataset.skinKey === key) {
        card.classList.add('selected-skin');
        if (checkmark) {
          checkmark.classList.add('selected-checkmark');
        }
      } else {
        card.classList.remove('selected-skin');
        if (checkmark) {
          checkmark.classList.remove('selected-checkmark');
        }
      }
    });
  }
  
  try {
    const form = document.getElementById('timer_form');
    const isEdit = form && form.dataset.isEdit === '1';
    const isFirstLoad = !isEdit && !loadedSkins.has(key);
    
    const meta = await fetchJson(`/skins/${encodeURIComponent(key)}`);
    
    let baseOverrides = lastSkinOverrides || {};
    
    // For new timers, fetch and apply formDefaults on first skin load
    if (isFirstLoad) {
      try {
        const formDefaultsResponse = await fetchJson(`/api/skins/${encodeURIComponent(key)}/form-defaults`);
        if (formDefaultsResponse && formDefaultsResponse.formDefaults && Object.keys(formDefaultsResponse.formDefaults).length > 0) {
          // Store applied formDefaults so they're always saved (even if they match original defaults)
          appliedFormDefaults = { ...formDefaultsResponse.formDefaults };
          // Merge formDefaults into metadata.defaults so they appear in form fields
          // This ensures the form fields show the formDefaults values
          meta.defaults = { ...meta.defaults, ...formDefaultsResponse.formDefaults };
          // Also set as baseOverrides so they're saved
          baseOverrides = { ...formDefaultsResponse.formDefaults };
          // Mark this skin as loaded
          loadedSkins.add(key);
        }
      } catch (e) {
        console.warn('Failed to fetch form defaults:', e);
      }
    } else {
      // Clear appliedFormDefaults when switching to a different skin
      appliedFormDefaults = {};
    }
    
    // When changing skin, apply the latest user overrides (carry settings across skins)
    renderFields(meta, baseOverrides);
    
    // If we applied formDefaults, sync them to the hidden input immediately
    if (isFirstLoad && Object.keys(baseOverrides).length > 0) {
      // Update lastSkinOverrides so they get saved
      lastSkinOverrides = { ...baseOverrides };
      // Small delay to ensure form fields are rendered before syncing
      setTimeout(() => {
        syncOverrides();
      }, 100);
    }
    
    const prevEl = document.getElementById('selected_skin_preview');
    if (meta && meta.preview_url) {
      prevEl.src = meta.preview_url;
      prevEl.classList.remove('d-none');
    }
    // Generate preview frame after skin change
    generatePreviewFrame();
  } catch (e) {
    console.error(e);
  }
}

document.addEventListener('DOMContentLoaded', () => {
  // Initial collapsible setup for static headers (general/customize cards)
  setupCollapsibles(document);

  // Wire up form fields to trigger preview generation
  const form = document.getElementById('timer_form');
  if (form) {
    const previewTriggerFields = [
      'title', 'text_position', 'custom_text', 'status', 'timer_type',
      'target_datetime', 'duration_seconds', 'animation_frame_limit'
    ];
    
    previewTriggerFields.forEach(fieldName => {
      const field = form.querySelector(`[name="${fieldName}"]`);
      if (field) {
        field.addEventListener('change', generatePreviewFrame);
        field.addEventListener('input', generatePreviewFrame);
      }
    });
  }

  (async () => {
    try {
      const current = document.getElementById('skin_key').value;
      // Load saved overrides from hidden input (for edit mode)
      const savedOverrides = (() => {
        try {
          return JSON.parse(document.getElementById('skin_overrides_input').value || '{}');
        } catch(e) {
          return {};
        }
      })();
      
      if (current) {
        const form = document.getElementById('timer_form');
        const isEdit = form && form.dataset.isEdit === '1';
        
        if (isEdit) {
          // Edit mode: load existing skin with saved overrides
          const meta = await fetchJson(`/skins/${encodeURIComponent(current)}`);
          renderFields(meta, savedOverrides);
          // Seed last overrides with saved values so switching skins keeps the user's base settings
          lastSkinOverrides = savedOverrides;
          document.getElementById('selected_skin_label').textContent = current;
          if (meta && meta.preview_url) {
            const prevEl = document.getElementById('selected_skin_preview');
            prevEl.src = meta.preview_url;
            prevEl.classList.remove('d-none');
          }
        } else {
          // New timer: load skin and apply formDefaults if available
          await selectSkin(current);
        }
      }
      // Populate top horizontal skin bar
      if (typeof loadSkinsIntoScrollBar === 'function') {
        await loadSkinsIntoScrollBar();
      }
      
      // Generate initial preview
      suppressNextDirtyFlag = true;
      generatePreviewFrame();
    } catch (e) {
      // swallow to avoid noisy console during navigation
    }
  })();

});

// AJAX save + preview polling
async function ajaxSaveAndPoll() {
  const form = document.getElementById('timer_form');
  const saveBtn = document.getElementById('save_btn');
  if (!form) return;
  saveBtn.addEventListener('click', async () => {
    const isEdit = form.dataset.isEdit === '1';
    
    // Show confirmation dialog
    const confirmResult = await Swal.fire({
      title: 'Generate GIF?',
      text: isEdit
        ? 'This will save your timer changes and generate a new GIF.'
        : 'This will create a new timer and generate its GIF.',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Yes, save it',
      cancelButtonText: 'Cancel'
    });
    
    if (!confirmResult.isConfirmed) {
      return;
    }
    
    // Show loading indicator
    Swal.fire({
      title: isEdit ? 'Updating timer...' : 'Creating timer...',
      text: 'Please wait',
      allowOutsideClick: false,
      allowEscapeKey: false,
      showConfirmButton: false,
      didOpen: () => {
        Swal.showLoading();
      }
    });
    
    saveBtn.disabled = true;
    const formData = new FormData(form);
    // Instruct the backend to generate the GIF as part of save
    formData.append('generate_gif', '1');
    // Ensure _method is present for PUT
    const methodOverride = form.querySelector('input[name="_method"]');
    const isPut = methodOverride && methodOverride.value.toUpperCase() === 'PUT';
    try {
      const res = await fetch(form.action, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        body: formData
      });
      if (!res.ok) {
        const err = await res.json().catch(() => ({}));
        Swal.fire({
          icon: 'error',
          title: 'Save Failed',
          text: err.message || 'Failed to save. Please check your inputs.'
        });
        saveBtn.disabled = false;
        return;
      }
      const json = await res.json();
      const timerId = json.id || form.dataset.timerId;
      if (!timerId) {
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: 'Timer ID not found'
        });
        saveBtn.disabled = false;
        return;
      }
      form.dataset.timerId = timerId;
      
      const previewWrap = document.getElementById('timer_preview');
      const loader = document.getElementById('preview_loader');
      const frameImg = document.getElementById('preview_frame');
      const gifImg = document.getElementById('preview_gif');
      const placeholder = document.getElementById('preview_placeholder');

      // If we have a GIF URL, show it immediately in the preview
      const gifUrl = json.gif_url_with_cache_bust || json.gif_url;
      if (gifUrl && gifImg && loader && frameImg && placeholder) {
        loader.classList.add('d-none');
        frameImg.classList.add('d-none');
        placeholder.classList.add('d-none');
        gifImg.src = gifUrl;
        gifImg.classList.remove('d-none');
      }

      // Update embed section visibility and value based on share availability
      const embedSection = document.getElementById('embed_section');
      const embedInput = document.getElementById('embed_input');
      const embedNotice = document.getElementById('embed_notice');
      if (embedSection && embedInput) {
        if (json.share_available && gifUrl) {
          const match = (gifUrl || '').match(/\/timers\/(\d+)\/([^\/]+)\.gif/);
          if (match) {
            const userId = match[1];
            const filename = match[2];
            const gettimerUrl = `${window.location.origin}/gettimer/${userId}/${filename}.gif`;
            embedInput.value = `<img src="${gettimerUrl}" alt="Countdown Timer">`;
          } else {
            embedInput.value = `<img src="${gifUrl}" alt="Countdown Timer">`;
          }
          embedSection.classList.remove('d-none');
          if (embedNotice) embedNotice.classList.add('d-none');
        } else {
          embedSection.classList.add('d-none');
          if (embedNotice) embedNotice.classList.remove('d-none');
        }
      }

      // Mark GIF as up to date
      gifDirty = false;

      // For create mode: redirect after save
      if (form.dataset.isEdit !== '1') {
        Swal.fire({
          icon: 'success',
          title: 'Timer Created',
          text: 'Your timer and GIF have been created.',
          timer: 1500,
          showConfirmButton: false
        }).then(() => {
          window.location.href = `/dashboard`;
        });
        return;
      }
      
      // For edit mode: show success toast, keep user on page with updated GIF
      Swal.fire({
        icon: 'success',
        title: 'GIF Generated',
        text: 'Your timer has been saved and the GIF has been updated.',
        timer: 1500,
        showConfirmButton: false
      });
      saveBtn.disabled = false;
    } catch (e) {
      console.error(e);
      Swal.fire({
        icon: 'error',
        title: 'Network Error',
        text: 'An error occurred while saving. Please try again.'
      });
      saveBtn.disabled = false;
    }
  });
}

function startPreviewPolling(timerId) {
  const previewWrap = document.getElementById('timer_preview');
  const loader = document.getElementById('preview_loader');
  const frameImg = document.getElementById('preview_frame');
  const gifImg = document.getElementById('preview_gif');
  const placeholder = document.getElementById('preview_placeholder');
  
  // Hide frame, show loader
  if (frameImg) frameImg.classList.add('d-none');
  if (gifImg) gifImg.classList.add('d-none');
  if (placeholder) placeholder.classList.add('d-none');
  if (loader) loader.classList.remove('d-none');
  
  // Update Swal loading message if Swal is already open
  if (Swal.isVisible()) {
    Swal.update({
      title: 'Generating GIF...',
      text: 'Please wait while your timer GIF is being created'
    });
  }
  
  let attempts = 0;
  const maxAttempts = 60; // up to ~2 minutes if interval is 2s
  const interval = setInterval(async () => {
    attempts++;
    try {
      const res = await fetch(`/timers/${encodeURIComponent(timerId)}/status`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      const json = await res.json();
      const form = document.getElementById('timer_form');
      if (form && json.status) {
        form.dataset.timerStatus = json.status;
      }
      const isActive = json.status === 'active';
      const embedSection = document.getElementById('embed_section');
      const embedInput = document.getElementById('embed_input');
      const embedNotice = document.getElementById('embed_notice');
      if (embedSection) embedSection.classList.toggle('d-none', !isActive);
      if (embedNotice) embedNotice.classList.toggle('d-none', isActive);
      if (json.gif_url) {
        clearInterval(interval);
        
        // Close the loading Swal and show success (only if Swal was open)
        if (Swal.isVisible()) {
          Swal.fire({
            icon: 'success',
            title: 'Timer Updated!',
            text: 'Your timer GIF has been generated.',
            timer: 2000,
            showConfirmButton: false
          });
        }
        
        // Re-enable save button
        const saveBtn = document.getElementById('save_btn');
        if (saveBtn) saveBtn.disabled = false;
        
        // Hide loader and frame, show GIF
        if (loader) loader.classList.add('d-none');
        if (frameImg) frameImg.classList.add('d-none');
        if (placeholder) placeholder.classList.add('d-none');
        if (gifImg) {
          const previewSrc = json.gif_url_with_cache_bust || json.gif_url;
          gifImg.src = previewSrc;
          gifImg.classList.remove('d-none');
        }
        // update embed section
        if (embedInput) {
          if (!json.share_available) {
            embedInput.value = '';
          }
        }
        if (embedSection && embedInput) {
          // Extract user_id and filename from gif_url to build gettimer URL
          const gifUrl = json.gif_url || '';
          const match = gifUrl.match(/\/timers\/(\d+)\/([^\/]+)\.gif/);
          if (match) {
            const userId = match[1];
            const filename = match[2];
            const gettimerUrl = `${window.location.origin}/gettimer/${userId}/${filename}.gif`;
            embedInput.value = `<img src="${gettimerUrl}" alt="Countdown Timer">`;
          } else {
            const absUrl = new URL(gifUrl, window.location.origin).href;
            embedInput.value = `<img src="${absUrl}" alt="Countdown Timer">`;
          }
          embedSection.classList.toggle('d-none', !json.share_available);
        }
        // GIF is now up to date with latest changes
        gifDirty = false;
      } else if (attempts >= maxAttempts) {
        clearInterval(interval);
        if (Swal.isVisible()) {
          Swal.fire({
            icon: 'warning',
            title: 'Timeout',
            text: 'GIF generation is taking longer than expected. Please refresh the page.'
          });
        }
        const saveBtn = document.getElementById('save_btn');
        if (saveBtn) saveBtn.disabled = false;
      }
    } catch (e) {
      // ignore transient errors
    }
  }, 2000);
}

ajaxSaveAndPoll();

// Auto-poll on edit page load when GIF not yet available
(function autoPollOnLoad(){
  const form = document.getElementById('timer_form');
  if (!form) return;
  if (form.dataset.isEdit === '1' && form.dataset.hasGif !== '1' && form.dataset.timerId) {
    startPreviewPolling(form.dataset.timerId);
  }
})();

// Bind copy button to use global toast
document.addEventListener('DOMContentLoaded', function(){
  if (!window.copyToClipboard) {
    window.copyToClipboard = function(text){
      if (navigator.clipboard && navigator.clipboard.writeText) {
        return navigator.clipboard.writeText(text);
      }
      return new Promise(function(resolve, reject){
        try {
          var textarea = document.createElement('textarea');
          textarea.value = text;
          textarea.setAttribute('readonly', '');
          textarea.style.position = 'absolute';
          textarea.style.left = '-9999px';
          document.body.appendChild(textarea);
          textarea.select();
          var successful = document.execCommand('copy');
          document.body.removeChild(textarea);
          successful ? resolve() : reject(new Error('Copy command unsuccessful'));
        } catch (err) {
          reject(err);
        }
      });
    };
  }

  var btn = document.getElementById('embed_copy_btn');
  if (!btn) return;
  btn.addEventListener('click', function(){
    var inp = document.getElementById('embed_input');
    if (!inp || !inp.value) return;
    window.copyToClipboard(inp.value).then(function(){
      if (window.showToast) window.showToast('Embed code copied', 'success');
    }).catch(function(){
      if (window.showToast) window.showToast('Copy failed', 'danger');
    });
  });
});
</script>

