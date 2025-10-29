<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Preview: {{ $timer->title }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @if($timer->gif_url)
                        <div class="mb-4">
                            <img src="{{ url($timer->gif_url) }}" class="border max-w-full" alt="preview">
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium mb-1">Embed Code</label>
                            <input type="text" class="w-full border rounded p-2" value='<img src="{{ url($timer->gif_url) }}" alt="Countdown Timer">' readonly>
                        </div>
                    @else
                        <div class="p-4 bg-yellow-50 text-yellow-800 rounded">No GIF generated yet.</div>
                    @endif

                    <a href="{{ route('timers.edit', $timer) }}" class="px-4 py-2 border rounded">Back</a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>


