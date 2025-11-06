@php
    $isEdit = isset($timer);
    $formAction = $isEdit ? route('timers.update', $timer) : route('timers.store');
    $formMethod = $isEdit ? 'PUT' : 'POST';
@endphp

@if(isset($timer))
<div class="py-1" style="border: 0px solid red; display: flex; justify-content: flex-end;">
  <a href="{{ route('timers.preview', $timer) }}" class="btn btn-primary border rounded">Preview Timer</a>
</div>
@endif
<div class="py-4 py-md-3">
    <div class="container-fluid">
        <form id="timer_form" method="POST" action="{{ $formAction }}" data-timer-id="{{ $isEdit ? $timer->id : '' }}" data-is-edit="{{ $isEdit ? '1' : '0' }}" data-has-gif="{{ $isEdit && !empty($timer->gif_url) ? '1' : '0' }}">
            @csrf
            @if($isEdit)
                @method('PUT')
            @endif

            <div class="row g-3">
                <!-- Left column -->
                <div class="col-12 col-md-6">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Title</label>
                                <input type="text" name="title" value="{{ old('title', $timer->title ?? '') }}" class="form-control" required>
                            </div>

                            <div class="row g-3">
                                <div class="col-12 col-sm-6">
                                    <label class="form-label">Text Position</label>
                                    <select name="text_position" class="form-select">
                                        <option value="none" {{ ($isEdit && $timer->text_position === 'none') || !$isEdit ? 'selected' : '' }}>None</option>
                                        <option value="top" {{ $isEdit && $timer->text_position === 'top' ? 'selected' : '' }}>Top</option>
                                        <option value="bottom" {{ $isEdit && $timer->text_position === 'bottom' ? 'selected' : '' }}>Bottom</option>
                                    </select>
                                </div>
                                <div class="col-12 col-sm-6">
                                    <label class="form-label">Custom Text {{ !$isEdit ? '(optional)' : '' }}</label>
                                    <input type="text" name="custom_text" class="form-control" maxlength="255" value="{{ old('custom_text', $timer->custom_text ?? '') }}">
                                </div>
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
                                       value="{{ old('target_datetime', $isEdit && $timer->target_datetime ? $timer->target_datetime->format('Y-m-d\\TH:i') : '') }}">
                            </div>

                            <div id="duration_input" class="mt-3 {{ ($isEdit && $timer->timer_type !== 'reload_based') || !$isEdit ? 'd-none' : '' }}">
                                <label class="form-label">Duration (seconds)</label>
                                <input type="number" name="duration_seconds" class="form-control" min="1" max="604800"
                                       value="{{ old('duration_seconds', $timer->duration_seconds ?? '') }}">
                            </div>

                            <div class="mt-3">
                                <label class="form-label">Selected Skin</label>
                                <input type="hidden" name="skin" id="skin_key" value="{{ $isEdit ? $timer->skin : '' }}" />
                                <div class="mt-2 position-relative d-inline-flex align-items-center gap-2 border rounded p-2 bg-light">
                                    <img id="selected_skin_preview" alt="Selected skin preview" class="border rounded bg-white d-none" style="height: 40px; width: auto;" />
                                    <span id="selected_skin_label" class="small"></span>
                                    
                                </div>
                                <button type="button" id="open_skin_modal" class="btn btn-sm btn-light border" title="Change skin" aria-label="Change skin" data-bs-toggle="modal" data-bs-target="#skinSelectModal">
                                  <i class="fa-solid fa-rotate"></i>
                              </button>
                                <p class="form-text">Click the change icon to pick a different skin.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right column -->
                <div class="col-12 col-md-6">
                    <div class="card h-100">
                        <div class="card-body">
                            <label class="form-label">Customize Skin</label>
                            <div id="skin_overrides_section" class="mt-2 d-none">
                                <div id="skin_fields" class="row g-3"></div>
                            </div>
                            <input type="hidden" name="skin_overrides" id="skin_overrides_input" value="{{ $isEdit ? json_encode($timer->skin_overrides ?? []) : '' }}" />
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="col-12">
                    <div class="d-flex gap-2 flex-wrap">
                        <button id="save_btn" type="button" class="btn btn-primary">Save</button>
                        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">{{ $isEdit ? 'Back' : 'Cancel' }}</a>
                    </div>
                </div>

                <!-- Preview full width -->
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <h3 class="h6 fw-semibold mb-3">Preview</h3>
                            <div id="timer_preview" class="d-flex align-items-center justify-content-center rounded border bg-light mb-3" style="min-height: 400px;">
                                @if($isEdit && isset($timer->gif_url) && $timer->gif_url)
                                    <img src="{{ url($timer->gif_url) }}" class="img-fluid" style="max-height: 400px; object-fit: contain;" alt="Timer preview" id="preview_gif">
                                @else
                                    <div class="text-center text-muted">
                                        @if($isEdit)
                                            <div class="spinner-border spinner-border-sm text-primary mb-2" role="status"><span class="visually-hidden">Loading...</span></div>
                                            <p class="small mb-0">Generating GIF...</p>
                                        @else
                                            <svg class="mb-2" width="48" height="48" fill="currentColor" viewBox="0 0 16 16">
                                                <path d="M8 3.993c-2.21 0-4 1.79-4 4 0 .705.183 1.368.504 1.944l-.837.837A5.002 5.002 0 1 1 13 8a4.978 4.978 0 0 1-1.944.504l-.837-.837c.576-.321 1.239-.504 1.944-.504 2.21 0 4 1.79 4 4s-1.79 4-4 4-4-1.79-4-4c0-.705.183-1.368.504-1.944l-.837-.837A3.993 3.993 0 0 0 8 3.993z"/>
                                            </svg>
                                            <p class="small mb-0">Timer preview will appear here</p>
                                            <p class="text-muted small">Configure your timer settings to see the preview</p>
                                        @endif
                                    </div>
                                @endif
                            </div>
                            <div id="embed_section" class="mt-3 {{ $isEdit && isset($timer->gif_url) && $timer->gif_url ? '' : 'd-none' }}">
                                <h4 class="h6 fw-semibold mb-2">Embed Code</h4>
                                <div class="d-flex gap-2">
                                    @if($isEdit && isset($timer->gif_url) && $timer->gif_url)
                                        @php($embedUrl = url('/gettimer/'.$timer->user_id.'/'.pathinfo($timer->gif_url, PATHINFO_FILENAME)).'.gif')
                                        <input id="embed_input" type="text" class="form-control form-control-sm" value='<img src="{{ $embedUrl }}" alt="Countdown Timer">' readonly>
                                    @else
                                        <input id="embed_input" type="text" class="form-control form-control-sm" value="" readonly>
                                    @endif
                                    <button id="embed_copy_btn" type="button" class="btn btn-outline-secondary btn-sm" onclick="(function(btn){var inp=document.getElementById('embed_input'); if(!inp) return; navigator.clipboard.writeText(inp.value); btn.textContent='Copied!'; setTimeout(function(){btn.textContent='Copy';},1500);})(this)">Copy</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </form>
    </div>
</div>

<!-- Skin Selection Modal (Bootstrap) -->
<div class="modal fade" id="skinSelectModal" tabindex="-1" aria-labelledby="skinSelectModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="skinSelectModalLabel">Choose a Skin</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="skin_modal_grid" class="row g-3"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
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

let currentSkinMetadata = null;

function renderFields(metadata, savedOverrides = {}) {
  currentSkinMetadata = metadata;
  const fieldsWrap = document.getElementById('skin_fields');
  const defaults = metadata.defaults || {};
  const fields = Array.isArray(metadata.fields) ? metadata.fields : [];
  fieldsWrap.innerHTML = '';
  document.getElementById('skin_overrides_section').classList.toggle('d-none', fields.length === 0);

  const smallKeys = new Set(['fontSize','separator','layout','animation','showDays','showHours','showMinutes','showSeconds']);
  fields.forEach(field => {
    const key = field.key;
    const label = field.label || key;
    const type = field.type || 'text';
    // Use saved override if exists, otherwise use skin default
    const value = (savedOverrides[key] !== undefined) ? savedOverrides[key] : defaults[key];

    const container = document.createElement('div');
    const isSmall = type === 'color' || type === 'boolean' || smallKeys.has(key);
    container.className = isSmall ? 'col-12 col-sm-6 col-md-4' : 'col-12';
    container.innerHTML = `<label class="form-label small mb-1">${label}</label>`;
    let input;
    if (type === 'color') {
      input = document.createElement('input');
      input.type = 'color';
      input.value = /^#/.test(value) ? value : (defaults[key] || '#000000');
      input.className = 'form-control form-control-color';
    } else if (type === 'number') {
      input = document.createElement('input');
      input.type = 'number';
      if (field.min != null) input.min = field.min;
      if (field.max != null) input.max = field.max;
      if (field.step != null) input.step = field.step;
      input.value = (value ?? '');
      input.className = 'form-control';
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
    fieldsWrap.appendChild(container);
  });

  // Sync initial state to hidden input
  syncOverrides();
}

function syncOverrides() {
  const fieldsWrap = document.getElementById('skin_fields');
  const inputs = fieldsWrap.querySelectorAll('[data-key]');
  const data = {};
  const defaults = currentSkinMetadata?.defaults || {};
  
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
    
    // Only store if different from default
    if (val !== defaults[k]) {
      data[k] = val;
    }
  });
  
  document.getElementById('skin_overrides_input').value = JSON.stringify(data);
}

async function selectSkin(key) {
  document.getElementById('skin_key').value = key;
  document.getElementById('selected_skin_label').textContent = key;
  try {
    const meta = await fetchJson(`/skins/${encodeURIComponent(key)}`);
    // When changing skin, reset to new skin's defaults (overrides were for old skin)
    renderFields(meta, {});
    const prevEl = document.getElementById('selected_skin_preview');
    if (meta && meta.preview_url) {
      prevEl.src = meta.preview_url;
      prevEl.classList.remove('d-none');
    }
  } catch (e) {
    console.error(e);
  }
}

document.addEventListener('DOMContentLoaded', () => {
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
        // Edit mode: load existing skin with saved overrides
        const meta = await fetchJson(`/skins/${encodeURIComponent(current)}`);
        renderFields(meta, savedOverrides);
        document.getElementById('selected_skin_label').textContent = current;
        if (meta && meta.preview_url) {
          const prevEl = document.getElementById('selected_skin_preview');
          prevEl.src = meta.preview_url;
          prevEl.classList.remove('d-none');
        }
      }
      // Load skins when modal is shown
      const modalEl = document.getElementById('skinSelectModal');
      if (modalEl) {
        modalEl.addEventListener('shown.bs.modal', loadSkinsIntoModal);
      }
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
    console.log('saveBtn clicked');
    saveBtn.disabled = true;
    const formData = new FormData(form);
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
        alert(err.message || 'Failed to save. Please check your inputs.');
        saveBtn.disabled = false;
        return;
      }
      const json = await res.json();
      const timerId = json.id || form.dataset.timerId;
      if (!timerId) {
        saveBtn.disabled = false;
        return;
      }
      form.dataset.timerId = timerId;
      // Start polling for gif_url
      if (form.dataset.isEdit !== '1') {
        window.location.href = `/dashboard`;
        return;
      }
      startPreviewPolling(timerId);
    } catch (e) {
      console.error(e);
      alert('Network error while saving.');
    } finally {
      saveBtn.disabled = false;
    }
  });
}

function startPreviewPolling(timerId) {
  const previewWrap = document.getElementById('timer_preview');
  if (previewWrap) {
    previewWrap.innerHTML = '<div class="text-center text-gray-500"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mx-auto mb-2"></div><p class="text-sm">Generating GIF...</p></div>';
  }
  let attempts = 0;
  const maxAttempts = 60; // up to ~2 minutes if interval is 2s
  const interval = setInterval(async () => {
    attempts++;
    try {
      const res = await fetch(`/timers/${encodeURIComponent(timerId)}/status`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      const json = await res.json();
      if (json.gif_url) {
        clearInterval(interval);
        if (previewWrap) {
          previewWrap.innerHTML = `<img src="${json.gif_url}" class="max-w-full max-h-[400px] object-contain" alt="Timer preview" />`;
        }
        // update embed section
        const embedSection = document.getElementById('embed_section');
        const embedInput = document.getElementById('embed_input');
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
          embedSection.classList.remove('d-none');
        }
      } else if (attempts >= maxAttempts) {
        clearInterval(interval);
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
</script>

