@php
    $isEdit = isset($timer);
    $formAction = $isEdit ? route('timers.update', $timer) : route('timers.store');
    $formMethod = $isEdit ? 'PUT' : 'POST';
@endphp

@if(isset($timer))
<div class="py-1" style="border: 0px solid red; display: flex; justify-content: flex-end; gap: 0.5rem;">
  <a href="{{ route('timers.preview', $timer) }}" class="btn btn-outline-secondary border rounded">Preview Timer</a>
  <button id="save_btn" type="button" class="btn btn-primary">Generate GIF</button>
</div>
@endif
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
                            <div class="d-flex align-items-center gap-2">
                                <button type="button" id="skin_scroll_prev" style="
    display: flex;
    flex-direction: row;
    justify-content: center;
    align-items: center;
    /* color: red; */
    font-weight: bolder;
    font-size: xx-large;
    border: unset !important;
    padding: 0;
    border: 0px solid red !important;
" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-chevron-left me-1"></i>
                                </button>
                                <div class="flex-grow-1 overflow-hidden">
                                    <div id="skin_scroll_bar" class="d-flex gap-3 flex-nowrap overflow-auto scrollbar-hide py-1 w-100">
                                        <!-- Skins will be injected here -->
                                    </div>
                                </div>
                                <button type="button" id="skin_scroll_next" style="
    display: flex;
    flex-direction: row;
    justify-content: center;
    align-items: center;
    /* color: red; */
    font-weight: bolder;
    font-size: xx-large;
    border: unset !important;
    padding: 0;
    border: 0px solid red !important;
" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-chevron-right ms-1"></i>
                                </button>
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

                                <div class="row g-3 mt-1">
                                    <div class="col-12 col-sm-6">
                                        <label class="form-label">Animated Seconds</label>
                                        <input type="number"
                                               name="animation_frame_limit"
                                               class="form-control"
                                               min="10"
                                               max="60"
                                               value="{{ old('animation_frame_limit', $timer->animation_frame_limit ?? 20) }}">
                                        <div class="form-text">Animated duration for generated GIF (10-60 seconds).</div>
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
                    <div class="card h-100">
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
                        @unless($isEdit)
                            <button id="save_btn" type="button" class="btn btn-primary">Generate GIF</button>
                        @endunless
                        {{-- <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">{{ $isEdit ? 'Back' : 'Cancel' }}</a> --}}
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

    skins.forEach(s => {
      const wrapper = document.createElement('div');
      wrapper.className = 'skin-card d-flex flex-column align-items-center text-center border rounded p-2 bg-white flex-shrink-0';
      wrapper.style.width = '220px';
      wrapper.style.cursor = 'pointer';

      const preview = document.createElement('div');
      preview.className = 'd-flex align-items-center justify-content-center bg-light border rounded w-100 mb-2';
      preview.style.height = '96px';

      const img = document.createElement('img');
      img.src = s.preview_url;
      img.alt = s.name;
      img.className = 'img-fluid';
      img.style.maxHeight = '92px';
      img.style.objectFit = 'contain';
      preview.appendChild(img);

      const name = document.createElement('div');
      name.className = 'small text-truncate w-100';
      name.textContent = s.name;

      wrapper.appendChild(preview);
      wrapper.appendChild(name);

      if (s.key === current) {
        wrapper.classList.add('border-primary', 'selected-skin');
      }

      wrapper.addEventListener('click', async () => {
        // Clear previous selection
        bar.querySelectorAll('.skin-card.selected-skin').forEach(el => {
          el.classList.remove('selected-skin', 'border-primary');
        });

        wrapper.classList.add('selected-skin', 'border-primary');
        await selectSkin(s.key);
      });

      wrapper.addEventListener('mouseenter', () => {
        if (!wrapper.classList.contains('selected-skin')) {
          wrapper.classList.add('border-primary');
        }
      });

      wrapper.addEventListener('mouseleave', () => {
        if (!wrapper.classList.contains('selected-skin')) {
          wrapper.classList.remove('border-primary');
        }
      });

      bar.appendChild(wrapper);
    });

    // Wire up left/right arrow buttons to scroll the skin list
    const prevBtn = document.getElementById('skin_scroll_prev');
    const nextBtn = document.getElementById('skin_scroll_next');
    const scrollAmount = 260; // approx width of one card including gap

    const updateArrowState = () => {
      if (!bar) return;
      const maxScrollLeft = bar.scrollWidth - bar.clientWidth - 4; // small tolerance
      const atStart = bar.scrollLeft <= 0;
      const atEnd = bar.scrollLeft >= maxScrollLeft;
      if (prevBtn) prevBtn.disabled = atStart;
      if (nextBtn) nextBtn.disabled = atEnd;
    };

    if (prevBtn) {
      prevBtn.addEventListener('click', () => {
        bar.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
        setTimeout(updateArrowState, 300);
      });
    }
    if (nextBtn) {
      nextBtn.addEventListener('click', () => {
        bar.scrollBy({ left: scrollAmount, behavior: 'smooth' });
        setTimeout(updateArrowState, 300);
      });
    }

    bar.addEventListener('scroll', updateArrowState);
    // Initialize arrow state
    updateArrowState();
  } catch (e) {
    bar.innerHTML = '<span class="text-danger small">Failed to load skins</span>';
  }
}

let currentSkinMetadata = null;
let currentHiddenOverrides = {};
let lastSkinOverrides = {};
let gifDirty = false;              // true = changes exist that are NOT in the latest GIF
let suppressNextDirtyFlag = false; // used for initial auto-preview so it doesn't mark dirty

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
  const hiddenFields = new Set(['fontPath', 'font', 'showDays', 'showHours', 'showMinutes', 'showSeconds', 'layout', 'animation', 'accentColor']);
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
  const hiddenFields = new Set(['fontPath', 'font', 'showDays', 'showHours', 'showMinutes', 'showSeconds', 'layout', 'animation', 'accentColor']);
  
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
    
    // Only store if different from default
    if (val !== defaults[k]) {
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
  try {
    const meta = await fetchJson(`/skins/${encodeURIComponent(key)}`);
    // When changing skin, apply the latest user overrides (carry settings across skins)
    const baseOverrides = lastSkinOverrides || {};
    renderFields(meta, baseOverrides);
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

