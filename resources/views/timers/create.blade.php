<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Create Timer</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
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

                        <div>
                            <label class="block text-sm font-medium">Skin</label>
                            <select name="skin" class="mt-1 w-full border rounded p-2">
                                @foreach($skins as $skin)
                                    <option value="{{ $skin }}">{{ $skin }}</option>
                                @endforeach
                            </select>
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


