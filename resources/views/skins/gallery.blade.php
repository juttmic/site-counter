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
                                        <div class="w-100 position-relative d-flex align-items-center justify-content-center overflow-hidden bg-light border rounded" style="height: 160px;">
                                            <img id="img_{{ $item['key'] }}" src="{{ $item['url'] }}" alt="{{ $item['key'] }} preview" class="img-fluid" style="max-height: 160px; max-width: 100%; object-fit: contain;">
                                            {{-- <button type="button" class="btn btn-light border position-absolute top-0 end-0 m-2 p-2 regenerate-skin-btn" title="Regenerate preview" data-skin-key="{{ $item['key'] }}">
                                                <i class="bi bi-arrow-repeat"></i>
                                            </button> --}}
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
    <script>
    document.addEventListener('click', async function(e){
        const btn = e.target.closest('.regenerate-skin-btn');
        if (!btn) return;
        const key = btn.getAttribute('data-skin-key');
        if (!key) return;
        // Show loader
        Swal.fire({ title: 'Regenerating...', text: key + ' (30s preview)', allowOutsideClick: false, allowEscapeKey: false, showConfirmButton: false, didOpen: () => Swal.showLoading() });
        try {
            const res = await fetch(`{{ url('/skins') }}/${encodeURIComponent(key)}/regenerate`, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') }
            });
            const json = await res.json();
            if (!res.ok || !json.ok) throw new Error(json.message || 'Failed');
            const img = document.getElementById('img_' + key);
            if (img) { img.src = json.url; }
            Swal.fire({ icon: 'success', title: 'Done', timer: 1200, showConfirmButton: false });
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Error', text: err.message || 'Regeneration failed' });
        }
    });
    </script>
</x-app-layout>


