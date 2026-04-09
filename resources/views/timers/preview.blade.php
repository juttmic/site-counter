<x-app-layout>
    <style>
        .dark .prev_btn {
            color: #fff;
            border-color: #fff;
        }
    </style>
    <div class="">
        <div class="container-fluid" style="max-width: 1100px;">

            {{-- Header --}}
            <div class="d-flex flex-wrap align-items-center justify-content-start gap-2 mb-4">
                <div>
                    <h2 class="fw-semibold mb-1">{{ $timer->title }}</h2>
                    {{-- <small class="text-muted">Timer preview</small> --}}
                </div>

                <div class="d-flex gap-2">
                    <a href="{{ route('timers.edit', $timer) }}"
                       class="btn btn-outline-primary btn-sm prev_btn"
                       style="border-radius: 50%;"
                       title="Edit"
                       data-bs-toggle="tooltip"
                       data-bs-placement="top"
                       aria-label="Edit">
                        <i class="bi bi-pencil"></i>
                    </a>
                    <button id="preview_embed_copy"
                            style="border-radius: 50%;"
                            class="btn btn-outline-primary btn-sm prev_btn"
                            title="Embed code"
                            data-bs-toggle="tooltip"
                            data-bs-placement="top"
                            aria-label="Embed code">
                        <i class="bi bi-copy"></i>
                    </button>
                    @if($timer->status === 'active' && !empty($timer->gif_url) && !$timer->needsGifRegeneration())
                        @php($embedUrl = url('/gettimer/'.$timer->user_id.'/'.pathinfo($timer->gif_url, PATHINFO_FILENAME)).'.gif')
                        <input
                            id="preview_embed_input"
                            type="text"
                            hidden
                            class="form-control font-monospace"
                            style="max-width: 640px;"
                            value='<img src="{{ $embedUrl }}" alt="Countdown Counter">'
                            readonly
                        >
                    @endif
                </div>
            </div>

            {{-- Preview Area --}}
            @if(!empty($timer->gif_url))
                @if($timer->needsGifRegeneration())
                    <div class="alert alert-warning text-center mb-3">
                        GIF is being generated. Your counter changes are saved—this preview may still show the previous GIF until the new one is ready.
                    </div>
                @endif
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
                    GIF is being gene. Please refresh in a moment.
                </div>
            @endif

            {{-- Actions --}}
            <div class="d-flex justify-content-center gap-2 mt-4">
                

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
