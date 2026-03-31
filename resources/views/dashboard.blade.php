<x-app-layout>
    <x-slot name="header">
        {{-- <div class="d-flex justify-content-between align-items-center">
            <h2 class="fw-semibold mb-0">
                {{ __('Your Counters') }}
            </h2>
        </div> --}}
    </x-slot>

    <div class="py-2 py-md-3">
        <div class="container-fluid">
          <div class="" style="display: flex;
    flex-direction: row;
    justify-content: end;
    padding-bottom: 0.5rem;">
    {{-- <a href="{{ route('timers.create') }}" class="btn btn-primary text-decoration-none fw-semibold {{ request()->routeIs('timers.create') ? 'text-primary' : 'text-dark' }}" style="color:white !important">Create New</a> --}}
                                </div>
            <div class="rounded">
                <div class="">
                    @if(isset($timers) && $timers->isEmpty())
                        <div class="alert alert-info">No counters yet. Create your first one!</div>
                    @else
                        <div class="row g-3">
                            @foreach($timers as $timer)
                            <div class="col-12 col-md-3">
                                <div class="card shadow-sm border-0 h-100">
                                  <div class="card-body d-flex flex-column justify-content-between">
                                    <!-- Title -->
                                    <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                                      <h5 class="card-title fw-bold text-dark mb-0" style="overflow: hidden; white-space: nowrap; text-overflow: ellipsis;">
                                        {{ $timer->title }}
                                      </h5>
                                      <span class="badge {{ $timer->status === 'active' ? 'bg-success' : 'bg-danger' }} flex-shrink-0">
                                        {{ ucfirst($timer->status) }}
                                      </span>
                                    </div>
                                    
                              
                                    <!-- Metadata -->
                                    <div class="small text-muted mb-3">
                                      <div class="mb-1">
                                        @if($timer->timer_type == 'fixed_date' && $timer->target_datetime)
                                          <span class="badge bg-secondary">On Date {{ $timer->target_datetime->format('M d, Y H:i') }}</span>
                                        @else
                                          <span class="badge bg-secondary">Counter Ends in {{  $timer->duration_seconds }} seconds</span>
                                        @endif
                                      </div>
                                    </div>
                              
                                    <!-- Preview -->
                                    <div class="mt-3 position-relative border rounded overflow-hidden bg-light timer-preview-{{ $timer->id }}" style="height: 160px;">
                                      @if($timer->gif_url)
                                        <img src="{{ url($timer->gif_url_with_cache_bust ?? $timer->gif_url) }}" alt="Timer Preview" class="img-fluid w-100 h-100 object-fit-contain timer-preview-img-{{ $timer->id }}">
                                        @if($timer->needsGifRegeneration())
                                          <div class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center"
                                               style="background: rgba(255,255,255,0.75);">
                                            <small class="text-muted fw-semibold">GIF is being generated…</small>
                                          </div>
                                        @endif
                                      @else
                                        <div class="d-flex flex-column align-items-center justify-content-center h-100 text-muted timer-preview-placeholder-{{ $timer->id }}">
                                          <small>GIF is being created</small>
                                        </div>
                                      @endif
                                    </div>
                              
                                    <!-- Actions -->
                                    <div class="mt-3 d-flex align-items-center">
                                      <div class="flex-grow-1"></div>
                                      <div class="btn-group" role="group">
                                        @php($embedCopyUrl = ($timer->gif_url && !$timer->needsGifRegeneration()) ? url('/gettimer/'.$timer->user_id.'/'.pathinfo($timer->gif_url, PATHINFO_FILENAME)).'.gif' : null)
                                        <a href="{{ route('timers.preview', $timer) }}" class="btn btn-outline-secondary btn-sm" title="Preview">
                                          <i class="bi bi-eye"></i>
                                        </a>
                                        @if($embedCopyUrl && $timer->status === 'active')
                                          <button type="button" class="btn btn-outline-secondary btn-sm copy-embed-btn copy-embed-btn-{{ $timer->id }}" title="Embed code" data-embed-url="{{ $embedCopyUrl }}" style="margin-left: 2px;">
                                            <i class="bi bi-clipboard"></i>
                                          </button>
                                        @else
                                          <button type="button" class="btn btn-outline-secondary btn-sm copy-embed-btn copy-embed-btn-{{ $timer->id }} d-none" title="Embed code" data-embed-url="" style="margin-left: 2px;">
                                            <i class="bi bi-clipboard"></i>
                                          </button>
                                        @endif
                                        <a href="{{ route('timers.edit', $timer) }}" class="btn btn-outline-primary btn-sm" title="Edit" style="margin-left: 2px;">
                                          <i class="bi bi-pencil"></i>
                                        </a>
                                      </div>
                                      <div class="flex-grow-1 d-flex justify-content-end">
                                        <form action="{{ route('timers.destroy', $timer) }}" method="POST" data-confirm="Delete this counter?" class="d-inline">
                                          @csrf
                                          @method('DELETE')
                                          <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete" style="border-radius: 5px; margin-left: 2px;">
                                            <i class="bi bi-trash"></i>
                                          </button>
                                        </form>
                                      </div>
                                    </div>
                                  </div>
                                </div>
                              </div>
                              
                            @endforeach
                        </div>
                        <div class="mt-4">{{ $timers->links("pagination::bootstrap-5") }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    <!-- Global Loader Overlay -->
    <div id="gif-generation-overlay" class="d-none position-fixed top-0 start-0 w-100 h-100 bg-dark bg-opacity-50" style="z-index: 9999;">
      <div class="d-flex flex-column align-items-center justify-content-center h-100">
        <div class="bg-white rounded p-4 shadow-lg text-center gif-generation-panel" style="max-width: 400px;">
          <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
            <span class="visually-hidden">Loading...</span>
          </div>
          <h5 class="mb-2">Generating GIF...</h5>
          <p class="text-muted mb-3" id="gif-generation-message">Please wait while we generate your counter GIF.</p>
          <div class="alert alert-warning mb-0">
            <i class="bi bi-exclamation-triangle"></i> <strong>Please do not close this page</strong> while generation is in progress.
          </div>
        </div>
      </div>
    </div>

    <script>
    // Copy embed code for dashboard cards
    document.addEventListener('click', function(e){
      if (!window.copyToClipboard) {
        window.copyToClipboard = function(text){
          if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text);
          }
          return new Promise(function(resolve, reject){
            try {
              var textarea = document.createElement('textarea');
              textarea.value = text;
              textarea.setAttribute('readonly', '');
              textarea.style.position = 'absolute';
              textarea.style.left = '-9999px';
              document.body.appendChild(textarea);
              textarea.select();
              var successful = document.execCommand('copy');
              document.body.removeChild(textarea);
              successful ? resolve() : reject(new Error('Copy command unsuccessful'));
            } catch (err) {
              reject(err);
            }
          });
        };
      }
      var btn = e.target.closest('.copy-embed-btn');
      if (!btn) return;
      var url = btn.getAttribute('data-embed-url');
      if (!url) return;
      var code = `<img src="${url}" alt="Countdown Counter">`;
      window.copyToClipboard(code).then(function(){
        if (window.showToast) window.showToast('Embed code copied', 'success');
      }).catch(function(){
        if (window.showToast) window.showToast('Copy failed', 'danger');
      });
    });

    // GIF Generation via AJAX
    (function() {
      function updatePreview(timerId, gifUrl) {
        const previewContainer = document.querySelector('.timer-preview-' + timerId);
        if (!previewContainer) return;

        const placeholder = previewContainer.querySelector('.timer-preview-placeholder-' + timerId);
        if (placeholder) {
          placeholder.remove();
        }

        let img = previewContainer.querySelector('.timer-preview-img-' + timerId);
        if (!img) {
          img = document.createElement('img');
          img.className = 'img-fluid w-100 h-100 object-fit-contain timer-preview-img-' + timerId;
          img.alt = 'Timer Preview';
          previewContainer.appendChild(img);
        }

        // Add cache busting
        const separator = gifUrl.includes('?') ? '&' : '?';
        img.src = gifUrl + separator + '_t=' + Date.now();
      }

      async function pollForGif(timerId, timerStatus, userId, maxAttempts = 60) {
        let attempts = 0;
        const embedBtn = document.querySelector('.copy-embed-btn-' + timerId);
        const interval = setInterval(async () => {
          attempts++;
          try {
            const res = await fetch(`/timers/${encodeURIComponent(timerId)}/status`, {
              headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            });
            if (!res.ok) throw new Error('status not ok');
            const json = await res.json();
            const gifUrl = json.gif_url_with_cache_bust || json.gif_url;
            if (gifUrl) {
              clearInterval(interval);
              updatePreview(timerId, gifUrl);
              if (embedBtn && timerStatus === 'active' && userId) {
                const match = (gifUrl || '').match(/\/timers\/(\d+)\/([^\/]+)\.gif/);
                if (match) {
                  const filename = match[2];
                  const embedUrl = window.location.origin + '/gettimer/' + userId + '/' + filename + '.gif';
                  embedBtn.setAttribute('data-embed-url', embedUrl);
                  embedBtn.classList.remove('d-none');
                }
              }
              return;
            }
            if (attempts >= maxAttempts) {
              clearInterval(interval);
            }
          } catch (e) {
            if (attempts >= maxAttempts) {
              clearInterval(interval);
            }
          }
        }, 2000);
      }
    })();
    </script>
</x-app-layout>
