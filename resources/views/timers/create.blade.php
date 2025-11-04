<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Create Timer</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Left: Form -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <form method="POST" action="{{ route('timers.store') }}" class="grid grid-cols-1 gap-4">
                        @csrf

                        <div>
                            <label class="block text-sm font-medium">Title</label>
                            <input type="text" name="title" value="{{ old('title') }}" class="mt-1 w-full border rounded p-2" required>
                        </div>

                        <div>
                            <label class="block text-sm font-medium">Status</label>
                            <select name="status" class="mt-1 w-full border rounded p-2">
                                <option value="active" selected>Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium">Timer Type</label>
                            <select name="timer_type" id="timer_type" class="mt-1 w-full border rounded p-2" onchange="toggleInputs()">
                                <option value="fixed_date">Fixed Date</option>
                                <option value="reload_based">Reload Based</option>
                            </select>
                        </div>

                        <div id="fixed_date_input">
                            <label class="block text-sm font-medium">Target DateTime</label>
                            <input type="datetime-local" name="target_datetime" class="mt-1 w-full border rounded p-2">
                        </div>

                        <div class="hidden" id="duration_input">
                            <label class="block text-sm font-medium">Duration (seconds)</label>
                            <input type="number" name="duration_seconds" class="mt-1 w-full border rounded p-2" min="1" max="604800">
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium">Width (px)</label>
                                <input type="number" name="width" class="mt-1 w-full border rounded p-2" min="120" max="1200" placeholder="e.g. 400">
                            </div>
                            <div>
                                <label class="block text-sm font-medium">Height (px)</label>
                                <input type="number" name="height" class="mt-1 w-full border rounded p-2" min="80" max="800" placeholder="e.g. 160">
                            </div>
                        </div>

						<div>
							<label class="block text-sm font-medium">Skin</label>
							<input type="hidden" name="skin" id="skin_key" />
							<div class="mt-2 flex items-center gap-3">
								<button type="button" id="toggle_skin_selector" class="px-3 py-1 border rounded text-sm">Choose Skin</button>
								<span id="selected_skin_label" class="text-sm text-gray-600"></span>
								<img id="selected_skin_preview" alt="Selected skin preview" class="h-10 w-auto hidden border rounded bg-gray-50" />
							</div>
							<div id="skin_grid" class="mt-2 hidden w-full flex flex-wrap gap-2" style="flex-wrap: wrap;"></div>
							<p class="text-xs text-gray-500 mt-1">Click a preview to select a skin.</p>
						</div>

                        <div id="skin_overrides_section" class="mt-4 hidden">
                            <label class="block text-sm font-medium">Customize Skin</label>
                            <div id="skin_fields" class="mt-2 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3"></div>
                            <input type="hidden" name="skin_overrides" id="skin_overrides_input" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium">Text Position</label>
                            <select name="text_position" class="mt-1 w-full border rounded p-2">
                                <option value="none" selected>None</option>
                                <option value="top">Top</option>
                                <option value="bottom">Bottom</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium">Custom Text (optional)</label>
                            <input type="text" name="custom_text" class="mt-1 w-full border rounded p-2" maxlength="255">
                        </div>

                        <div class="flex gap-2">
                            <button class="px-4 py-2 bg-indigo-600 text-white rounded">Save</button>
                            <a href="{{ route('dashboard') }}" class="px-4 py-2 border rounded">Cancel</a>
                        </div>
                    </form>
                    </div>
                </div>

                <!-- Right: Preview -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4">Preview</h3>
                        <div id="timer_preview" class="flex items-center justify-center min-h-[400px] bg-gray-50 border-2 border-dashed border-gray-300 rounded-lg">
                            <div class="text-center text-gray-500">
                                <svg class="mx-auto h-12 w-12 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <p class="text-sm">Timer preview will appear here</p>
                                <p class="text-xs mt-2">Configure your timer settings to see the preview</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    function toggleInputs() {
      const type = document.getElementById('timer_type').value;
      document.getElementById('fixed_date_input').classList.toggle('hidden', type !== 'fixed_date');
      document.getElementById('duration_input').classList.toggle('hidden', type !== 'reload_based');
    }
    document.addEventListener('DOMContentLoaded', toggleInputs);

    // Skins loading and dynamic form
    async function fetchJson(url) {
      const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      if (!res.ok) throw new Error('Failed to load ' + url);
      return await res.json();
    }

    function renderSkinGrid(skins) {
      const grid = document.getElementById('skin_grid');
      grid.innerHTML = '';
      skins.forEach(s => {
        const card = document.createElement('div');
        card.className = 'shrink-0 grow-0 box-border border rounded p-2 cursor-pointer transition hover:ring-2 hover:ring-indigo-500 hover:shadow-md';
        card.style.flexBasis = 'calc(50% - 0.25rem)';
        card.style.cursor = "pointer";
        card.innerHTML = `
          <img src="${s.preview_url}" alt="${s.name}" class="w-auto max-w-full h-24 mx-auto object-contain bg-gray-50">
          <div class="mt-1 text-xs truncate text-center">${s.name}</div>
        `;
        card.addEventListener('click', () => selectSkin(s.key));
        grid.appendChild(card);
      });
    }

    let currentSkinMetadata = null;

    function renderFields(metadata, savedOverrides = {}) {
      currentSkinMetadata = metadata;
      const fieldsWrap = document.getElementById('skin_fields');
      const defaults = metadata.defaults || {};
      const fields = Array.isArray(metadata.fields) ? metadata.fields : [];
      fieldsWrap.innerHTML = '';
      document.getElementById('skin_overrides_section').classList.toggle('hidden', fields.length === 0);

      const smallKeys = new Set(['fontSize','separator','layout','animation','showDays','showHours','showMinutes','showSeconds']);
      fields.forEach(field => {
        const key = field.key;
        const label = field.label || key;
        const type = field.type || 'text';
        // Use saved override if exists, otherwise use skin default
        const value = (savedOverrides[key] !== undefined) ? savedOverrides[key] : defaults[key];

        const container = document.createElement('div');
        const isSmall = type === 'color' || type === 'boolean' || smallKeys.has(key);
        container.className = isSmall ? 'col-span-1' : 'sm:col-span-2 md:col-span-3';
        container.innerHTML = `<label class="block text-xs font-medium mb-1">${label}</label>`;
        let input;
        if (type === 'color') {
          input = document.createElement('input');
          input.type = 'color';
          input.value = /^#/.test(value) ? value : (defaults[key] || '#000000');
          input.className = 'w-16 h-8 p-0.5 border rounded';
        } else if (type === 'number') {
          input = document.createElement('input');
          input.type = 'number';
          if (field.min != null) input.min = field.min;
          if (field.max != null) input.max = field.max;
          if (field.step != null) input.step = field.step;
          input.value = (value ?? '');
          input.className = 'mt-1 w-full border rounded p-2';
        } else if (type === 'boolean') {
          input = document.createElement('input');
          input.type = 'checkbox';
          input.checked = !!value;
          input.className = 'mt-1 h-4 w-4';
        } else if (type === 'select') {
          input = document.createElement('select');
          input.className = 'mt-1 w-full border rounded p-2';
          (field.options || []).forEach(opt => {
            const o = document.createElement('option');
            o.value = opt; o.textContent = opt; if (opt === value) o.selected = true; input.appendChild(o);
          });
        } else {
          input = document.createElement('input');
          input.type = 'text';
          if (field.maxLength != null) input.maxLength = field.maxLength;
          input.value = value ?? '';
          input.className = 'mt-1 w-full border rounded p-2';
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
        // When changing skin, reset to new skin's defaults (no saved overrides)
        renderFields(meta, {});
        // collapse grid after selection and show selected preview
        document.getElementById('skin_grid').classList.add('hidden');
        const prevEl = document.getElementById('selected_skin_preview');
        if (meta && meta.preview_url) {
          prevEl.src = meta.preview_url;
          prevEl.classList.remove('hidden');
        }
      } catch (e) {
        console.error(e);
      }
    }

    document.addEventListener('DOMContentLoaded', async () => {
      try {
        const skins = await fetchJson('/skins');
        renderSkinGrid(skins);
        if (skins.length) selectSkin(skins[0].key);
        document.getElementById('selected_skin_label').textContent = skins.length ? skins[0].key : '';
        document.getElementById('toggle_skin_selector').addEventListener('click', () => {
          document.getElementById('skin_grid').classList.toggle('hidden');
        });
      } catch (e) {
        console.error(e);
      }
    });
    </script>
</x-app-layout>


