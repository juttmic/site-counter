<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Skin Previews</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 md:grid-cols-3 gap-6">
                @forelse($items as $item)
                    <div class="bg-white shadow-sm rounded p-3 flex flex-col items-center">
                        <img src="{{ $item['url'] }}" alt="{{ $item['key'] }} preview" class="w-full h-40 object-contain bg-gray-50 border" />
                        <div class="mt-2 text-sm text-gray-800 text-center">{{ $item['key'] }}</div>
                    </div>
                @empty
                    <div class="col-span-full text-center text-gray-500">No previews found. Run <code>php artisan timergif:skins:previews --force</code>.</div>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>


