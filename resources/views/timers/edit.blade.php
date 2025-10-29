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
                        <div>
                            <label class="block text-sm font-medium">Skin</label>
                            <select name="skin" class="mt-1 w-full border rounded p-2">
                                @foreach($skins as $skin)
                                    <option value="{{ $skin }}" @selected($timer->skin===$skin)>{{ $skin }}</option>
                                @endforeach
                            </select>
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
                                <input type="text" class="w-full border rounded p-2" value='<img src="{{ $timer->gif_url }}" alt="Countdown Timer">' readonly>
                                <button class="px-3 py-2 border rounded" onclick="navigator.clipboard.writeText(this.previousElementSibling.value)">Copy</button>
                            </div>
                            <div class="mt-3">
                                <img src="{{ $timer->gif_url }}" class="border max-w-full" alt="preview">
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
    </script>
</x-app-layout>


