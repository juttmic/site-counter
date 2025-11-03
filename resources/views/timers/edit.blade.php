<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Edit Timer</h2>
            <a href="{{ route('timers.preview', $timer) }}" class="px-4 py-2 border rounded">Preview</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form method="POST" action="{{ route('timers.update', $timer) }}" class="grid grid-cols-1 gap-4">
                        @csrf
                        @method('PUT')
                        <div>
                            <label class="block text-sm font-medium">Title</label>
                            <input type="text" name="title" value="{{ old('title', $timer->title) }}" class="mt-1 w-full border rounded p-2" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium">Status</label>
                            <select name="status" class="mt-1 w-full border rounded p-2">
                                <option value="active" @selected($timer->status==='active')>Active</option>
                                <option value="inactive" @selected($timer->status==='inactive')>Inactive</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium">Timer Type</label>
                            <select name="timer_type" id="timer_type" class="mt-1 w-full border rounded p-2" onchange="toggleInputs()">
                                <option value="fixed_date" @selected($timer->timer_type==='fixed_date')>Fixed Date</option>
                                <option value="reload_based" @selected($timer->timer_type==='reload_based')>Reload Based</option>
                            </select>
                        </div>
                        <div id="fixed_date_input">
                            <label class="block text-sm font-medium">Target DateTime</label>
                            <input type="datetime-local" name="target_datetime" class="mt-1 w-full border rounded p-2" value="{{ optional($timer->target_datetime)->format('Y-m-d\\TH:i') }}">
                        </div>
                        <div class="hidden" id="duration_input">
                            <label class="block text-sm font-medium">Duration (seconds)</label>
                            <input type="number" name="duration_seconds" class="mt-1 w-full border rounded p-2" min="1" max="604800" value="{{ old('duration_seconds', $timer->duration_seconds) }}">
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium">Width (px)</label>
                                <input type="number" name="width" class="mt-1 w-full border rounded p-2" min="120" max="1200" value="{{ old('width', $timer->width) }}" placeholder="e.g. 400">
                            </div>
                            <div>
                                <label class="block text-sm font-medium">Height (px)</label>
                                <input type="number" name="height" class="mt-1 w-full border rounded p-2" min="80" max="800" value="{{ old('height', $timer->height) }}" placeholder="e.g. 160">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium">Skin</label>
                            <input type="hidden" name="skin" id="skin_key" value="{{ $timer->skin }}" />
                            <div id="skin_grid" class="mt-2 grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4"></div>
                            <p class="text-sm text-gray-500 mt-1">Click a preview to select a skin.</p>
                        </div>

                        <div id="skin_overrides_section" class="mt-4 hidden">
                            <label class="block text-sm font-medium">Customize Skin</label>
                            <div id="skin_fields" class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-4"></div>
                            <input type="hidden" name="skin_overrides" id="skin_overrides_input" value='@json($timer->skin_overrides ?? [])' />
                        </div>
                        <div>
                            <label class="block text-sm font-medium">Text Position</label>
                            <select name="text_position" class="mt-1 w-full border rounded p-2">
                                <option value="none" @selected($timer->text_position==='none')>None</option>
                                <option value="top" @selected($timer->text_position==='top')>Top</option>
                                <option value="bottom" @selected($timer->text_position==='bottom')>Bottom</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium">Custom Text</label>
                            <input type="text" name="custom_text" class="mt-1 w-full border rounded p-2" maxlength="255" value="{{ old('custom_text', $timer->custom_text) }}">
                        </div>
                        <div class="flex gap-2">
                            <button class="px-4 py-2 bg-indigo-600 text-white rounded">Save</button>
                            <a href="{{ route('dashboard') }}" class="px-4 py-2 border rounded">Back</a>
                        </div>
                    </form>
                    @if($timer->gif_url)
                        <div class="mt-6">
                            <h3 class="font-semibold mb-2">Embed</h3>
                            <div class="flex gap-2">
                                <input type="text" class="w-full border rounded p-2" value='<img src="{{ url($timer->gif_url) }}" alt="Countdown Timer">' readonly>
                                <button class="px-3 py-2 border rounded" onclick="navigator.clipboard.writeText(this.previousElementSibling.value)">Copy</button>
                            </div>
                            <div class="mt-3">
                                <img src="{{ url($timer->gif_url) }}" class="border max-w-full" alt="preview">
                            </div>
                        </div>
                    @endif
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
      const current = document.getElementById('skin_key').value;
      grid.innerHTML = '';
      skins.forEach(s => {
        const card = document.createElement('button');
        card.type = 'button';
        card.className = 'border rounded p-2 hover:ring-2 hover:ring-indigo-500 text-left';
        card.innerHTML = `
          <img src="${s.preview_url}" alt="${s.name}" class="w-full h-24 object-contain bg-gray-50">
          <div class="mt-2 text-sm">${s.name}</div>
        `;
        if (s.key === current) card.classList.add('ring-2','ring-indigo-600');
        card.addEventListener('click', () => selectSkin(s.key));
        grid.appendChild(card);
      });
    }

    function renderFields(metadata) {
      const fieldsWrap = document.getElementById('skin_fields');
      const defaults = metadata.defaults || {};
      const fields = Array.isArray(metadata.fields) ? metadata.fields : [];
      const saved = (() => { try { return JSON.parse(document.getElementById('skin_overrides_input').value || '{}'); } catch(e) { return {}; } })();
      fieldsWrap.innerHTML = '';
      document.getElementById('skin_overrides_section').classList.toggle('hidden', fields.length === 0);

      fields.forEach(field => {
        const key = field.key;
        const label = field.label || key;
        const type = field.type || 'text';
        const value = (saved[key] !== undefined) ? saved[key] : defaults[key];

        const container = document.createElement('div');
        container.innerHTML = `<label class=\"block text-sm font-medium mb-1\">${label}</label>`;
        let input;
        if (type === 'color') {
          input = document.createElement('input');
          input.type = 'color';
          input.value = /^#/.test(value) ? value : (defaults[key] || '#000000');
          input.className = 'w-20 h-10 p-1 border rounded';
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
          input.className = 'mt-1';
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
        fieldsWrap.appendChild(container);
        container.appendChild(input);
      });

      function syncOverrides() {
        const inputs = fieldsWrap.querySelectorAll('[data-key]');
        const data = {};
        inputs.forEach(el => {
          const k = el.dataset.key;
          if (el.type === 'checkbox') data[k] = !!el.checked;
          else if (el.type === 'number') data[k] = el.value === '' ? null : Number(el.value);
          else data[k] = el.value;
        });
        document.getElementById('skin_overrides_input').value = JSON.stringify(data);
      }

      // initialize
      syncOverrides();
    }

    async function selectSkin(key) {
      document.getElementById('skin_key').value = key;
      try {
        const meta = await fetchJson(`/skins/${encodeURIComponent(key)}`);
        renderFields(meta);
        // highlight selection
        renderSkinGrid(await fetchJson('/skins'));
      } catch (e) {
        console.error(e);
      }
    }

    document.addEventListener('DOMContentLoaded', async () => {
      try {
        const skins = await fetchJson('/skins');
        renderSkinGrid(skins);
        const current = document.getElementById('skin_key').value;
        if (current) {
          const meta = await fetchJson(`/skins/${encodeURIComponent(current)}`);
          renderFields(meta);
        }
      } catch (e) {
        console.error(e);
      }
    });

    async function fetchJson(url) {
      const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      if (!res.ok) throw new Error('Failed to load ' + url);
      return await res.json();
    }
    </script>
</x-app-layout>


