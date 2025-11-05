<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold mb-0">Skin Previews</h2>
    </x-slot>

    <div class="py-4 py-md-5">
        <div class="container-fluid">
            <div class="rounded">
                <div class="">
                    <div class="row g-3">
                        @forelse($items as $item)
                            <div class="col-12 col-md-6">
                                <div class="card h-100">
                                    <div class="card-body d-flex flex-column align-items-center">
                                        <div class="w-100 d-flex align-items-center justify-content-center overflow-hidden bg-light border rounded" style="height: 160px;">
                                            <img src="{{ $item['url'] }}" alt="{{ $item['key'] }} preview" class="img-fluid" style="max-height: 160px; max-width: 100%; object-fit: contain;">
                                        </div>
                                        <div class="mt-2 small text-muted text-center">{{ $item['key'] }}</div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="col-12 text-center text-muted">No previews found. Run <code>php artisan timergif:skins:previews --force</code>.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>


