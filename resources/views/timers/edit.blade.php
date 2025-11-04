<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Edit Timer</h2>
            <a href="{{ route('timers.preview', $timer) }}" class="px-4 py-2 border rounded">Preview</a>
        </div>
    </x-slot>

    @include('timers._form', ['timer' => $timer])
</x-app-layout>
