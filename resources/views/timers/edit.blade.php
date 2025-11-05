<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Edit Timer</h2>
            
        </div>
    </x-slot>

    @include('timers._form', ['timer' => $timer])
</x-app-layout>
