<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-center">
            <h2 class="fw-semibold mb-0">Preview: {{ $timer->title }}</h2>
        </div>
    </x-slot>

    <div class="py-4 py-md-5">
        <div class="container" style="max-width: 860px;">
            <div class="card">
                <div class="card-body">
                    @if($timer->gif_url)
                        <div class="d-flex justify-content-center mb-3">
                            <div class="d-flex align-items-center justify-content-center overflow-hidden bg-light border rounded" style="min-height: 260px;">
                                <img src="{{ url($timer->gif_url) }}" class="img-fluid" style="max-height: 360px; object-fit: contain;" alt="preview">
                            </div>
                        </div>
                        <div class="text-center">
                            <label class="form-label">Embed Code</label>
                            <div class="d-flex gap-2 justify-content-center">
                                @php($embedUrl = url('/gettimer/'.$timer->user_id.'/'.pathinfo($timer->gif_url, PATHINFO_FILENAME)).'.gif')
                                <input id="preview_embed_input" type="text" class="form-control" style="max-width: 560px;" value='<img src="{{ $embedUrl }}" alt="Countdown Timer">' readonly>
                                <button id="preview_embed_copy" type="button" class="btn btn-outline-secondary">Copy</button>
                            </div>
                        </div>
                    @else
                        <div class="alert alert-warning text-center mb-0">No GIF generated yet.</div>
                    @endif
                    <div class="d-flex justify-content-center mt-4">
                        <a href="{{ route('timers.edit', $timer) }}" class="btn btn-outline-secondary">Back</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function(){
            var copyBtn = document.getElementById('preview_embed_copy');
            if (copyBtn) {
                copyBtn.addEventListener('click', function(){
                    var inp = document.getElementById('preview_embed_input');
                    if (!inp) return;
                    navigator.clipboard.writeText(inp.value).then(function(){
                        if (window.showToast) window.showToast('Embed code copied', 'success');
                    }).catch(function(){});
                });
            }
        })();
    </script>
</x-app-layout>


