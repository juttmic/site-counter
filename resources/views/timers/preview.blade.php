<x-app-layout>
    <div class="py-5">
        <div class="container-fluid" style="max-width: 1100px;">

            {{-- Header --}}
            <div class="d-flex flex-wrap align-items-center justify-content-start gap-2 mb-4">
                <div>
                    <h2 class="fw-semibold mb-1">{{ $timer->title }}</h2>
                    {{-- <small class="text-muted">Timer preview</small> --}}
                </div>

                <div class="d-flex gap-2">
                    <a href="{{ route('timers.edit', $timer) }}"
                       class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-pencil"></i>
                    </a>
                    @if($timer->status === 'active')
                        @php(
                            $embedUrl = url('/gettimer/'.$timer->user_id.'/'.pathinfo($timer->gif_url, PATHINFO_FILENAME)).'.gif'
                        )
                    <button id="preview_embed_copy" class="btn btn-outline-primary btn-sm"><i class="bi bi-copy"></i></button>
                            
                                <input
                                    id="preview_embed_input"
                                    type="text"
                                    hidden
                                    class="form-control font-monospace"
                                    style="max-width: 640px;"
                                    value='<img src="{{ $embedUrl }}" alt="Countdown Counter">'
                                    readonly
                                >
                    @else
                    <button id="preview_embed_copy" class="btn btn-outline-primary btn-sm"><i class="bi bi-copy"></i></button>
                    @endif
                </div>
            </div>

            {{-- Preview Area --}}
            @if($timer->gif_url)
                <div class="d-flex justify-content-center mb-4">
                    <div class="bg-light rounded-3 p-3 w-100"
                         >
                        <div class="d-flex align-items-center justify-content-center"
                             style="min-height: 260px;">
                            <img
                                src="{{ url($timer->gif_url_with_cache_bust ?? $timer->gif_url) }}"
                                class="img-fluid"
                                style="max-height: 360px; object-fit: contain;"
                                alt="Timer preview">
                        </div>
                    </div>
                </div>

                {{-- Embed Code --}}
                
            @else
                <div class="alert alert-warning text-center mb-4">
                    No GIF generated yet.
                </div>
            @endif

            {{-- Actions --}}
            <div class="d-flex justify-content-center gap-2 mt-4">
                

                @if($timer->needsGifRegeneration())
                    <form action="{{ route('timers.generate-gif', $timer) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-primary">
                            {{ $timer->gif_url ? 'Regenerate GIF' : 'Generate GIF' }}
                        </button>
                    </form>
                @endif
            </div>

        </div>
    </div>

    {{-- Copy Script --}}
    <script>
        (function () {
            if (!window.copyToClipboard) {
                window.copyToClipboard = function (text) {
                    if (navigator.clipboard?.writeText) {
                        return navigator.clipboard.writeText(text);
                    }
                    return new Promise(function (resolve, reject) {
                        try {
                            const textarea = document.createElement('textarea');
                            textarea.value = text;
                            textarea.setAttribute('readonly', '');
                            textarea.style.position = 'absolute';
                            textarea.style.left = '-9999px';
                            document.body.appendChild(textarea);
                            textarea.select();
                            const success = document.execCommand('copy');
                            document.body.removeChild(textarea);
                            success ? resolve() : reject();
                        } catch (e) {
                            reject(e);
                        }
                    });
                };
            }

            const copyBtn = document.getElementById('preview_embed_copy');
            if (copyBtn) {
                copyBtn.addEventListener('click', function () {
                    const input = document.getElementById('preview_embed_input');
                    if (!input) {window.showToast?.('Activate the counter to get code', 'danger');return;}
                    window.copyToClipboard(input.value).then(function () {
                        window.showToast?.('Embed code copied', 'success');
                    });
                });
            }
        })();
    </script>
</x-app-layout>
