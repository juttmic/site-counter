<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight dark:text-gray-100">Skin Previews</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 md:grid-cols-3 gap-6">
                @forelse($items as $item)
                    <div class="bg-white shadow-sm rounded p-3 flex flex-col items-center dark:bg-gray-800">
                        <img src="{{ $item['url'] }}" alt="{{ $item['key'] }} preview" class="w-full h-40 object-contain bg-gray-50 border dark:bg-gray-900 dark:border-gray-700" />
                        <div class="mt-2 text-sm text-gray-800 text-center dark:text-gray-200">{{ $item['key'] }}</div>
                    </div>
                @empty
                    <div class="col-span-full text-center text-gray-500 dark:text-gray-300">No previews found. Run <code>php artisan timergif:skins:previews --force</code>.</div>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>


