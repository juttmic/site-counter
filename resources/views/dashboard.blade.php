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
    padding-bottom: 0.5rem;"><a href="{{ route('timers.create') }}" class="btn btn-primary text-decoration-none fw-semibold {{ request()->routeIs('timers.create') ? 'text-primary' : 'text-dark' }}" style="color:white !important">Create New</a>
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
                                    <div>
                                      <span class="badge {{ $timer->status === 'active' ? 'bg-success' : 'bg-danger' }}">
                                        {{ ucfirst($timer->status) }}
                                      </span>
                                    </div>
                                    <h5 class="card-title fw-bold text-dark mb-2">{{ $timer->title }}</h5>
                              
                                    <!-- Metadata -->
                                    <div class="small text-muted mb-3">
                                      <div class="mb-1">
                                        @if($timer->timer_type == 'fixed_date' && $timer->target_datetime)
                                          <span class="badge bg-secondary">Ends {{ $timer->target_datetime->format('M d, Y H:i') }}</span>
                                        @else
                                          <span class="badge bg-secondary">Evergreen</span>
                                        @endif
                                      </div>
                                    </div>
                              
                                    <!-- Preview -->
                                    <div class="mt-3 position-relative border rounded overflow-hidden bg-light timer-preview-{{ $timer->id }}" style="height: 160px;">
                                      @if($timer->gif_url)
                                        <img src="{{ url($timer->gif_url_with_cache_bust ?? $timer->gif_url) }}" alt="Timer Preview" class="img-fluid w-100 h-100 object-fit-contain timer-preview-img-{{ $timer->id }}">
                                      @else
                                        <div class="d-flex flex-column align-items-center justify-content-center h-100 text-muted timer-preview-placeholder-{{ $timer->id }}">
                                          <small>No GIF generated</small>
                                        </div>
                                      @endif
                                    </div>
                              
                                    <!-- Actions -->
                                    <div class="mt-3 d-flex justify-content-end align-items-center">
                                      <div class="btn-group" role="group">
                                        @php($embedCopyUrl = $timer->gif_url ? url('/gettimer/'.$timer->user_id.'/'.pathinfo($timer->gif_url, PATHINFO_FILENAME)).'.gif' : null)
                                        <a href="{{ route('timers.preview', $timer) }}" class="btn btn-outline-secondary btn-sm" title="Preview">
                                          <i class="bi bi-eye"></i>
                                        </a>
                                        @if($embedCopyUrl && $timer->status === 'active')
                                          <button type="button" class="btn btn-outline-secondary btn-sm copy-embed-btn copy-embed-btn-{{ $timer->id }}" title="Copy code" data-embed-url="{{ $embedCopyUrl }}" style="margin-left: 2px;">
                                            <i class="bi bi-clipboard"></i>
                                          </button>
                                        @else
                                          <button type="button" class="btn btn-outline-secondary btn-sm copy-embed-btn copy-embed-btn-{{ $timer->id }} d-none" title="Copy code" data-embed-url="" style="margin-left: 2px;">
                                            <i class="bi bi-clipboard"></i>
                                          </button>
                                        @endif
                                        <a href="{{ route('timers.edit', $timer) }}" class="btn btn-outline-primary btn-sm" title="Edit" style="margin-left: 2px;">
                                          <i class="bi bi-pencil"></i>
                                        </a>
                                        @if($timer->needsGifRegeneration())
                                          <button type="button" 
                                                  class="btn btn-outline-warning btn-sm generate-gif-btn" 
                                                  data-timer-id="{{ $timer->id }}"
                                                  data-timer-user-id="{{ $timer->user_id }}"
                                                  data-timer-status="{{ $timer->status }}"
                                                  data-timer-url="{{ route('timers.generate-gif', $timer) }}"
                                                  title="{{ $timer->gif_url ? 'Regenerate GIF' : 'Generate GIF' }}"
                                                  style="margin-left: 2px;">
                                            <span class="btn-content">
                                              <i class="bi bi-play-circle"></i>
                                            </span>
                                            <span class="btn-loader d-none">
                                              <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                            </span>
                                          </button>
                                        @endif
                                        <form action="{{ route('timers.destroy', $timer) }}" method="POST" data-confirm="Delete this counter?" class="d-inline">
                                          @csrf
                                          @method('DELETE')
                                          <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete" style="    border-radius: 0px 5px 5px 0px;
    margin-left: 2px;">
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
        <div class="bg-white rounded p-4 shadow-lg text-center" style="max-width: 400px;">
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
      const activeGenerations = new Set();
      const overlay = document.getElementById('gif-generation-overlay');
      const messageEl = document.getElementById('gif-generation-message');

      // Prevent page unload during generation
      window.addEventListener('beforeunload', function(e) {
        if (activeGenerations.size > 0) {
          e.preventDefault();
          e.returnValue = 'GIF generation is in progress. Are you sure you want to leave?';
          return e.returnValue;
        }
      });

      function showOverlay(timerId) {
        activeGenerations.add(timerId);
        messageEl.textContent = `Generating GIF for timer #${timerId}... Please wait.`;
        overlay.classList.remove('d-none');
        document.body.style.overflow = 'hidden';
      }

      function hideOverlay(timerId) {
        activeGenerations.delete(timerId);
        if (activeGenerations.size === 0) {
          overlay.classList.add('d-none');
          document.body.style.overflow = '';
        } else {
          // Update message for remaining generations
          const remaining = Array.from(activeGenerations);
          messageEl.textContent = `Generating GIF for timer #${remaining[0]}... (${activeGenerations.size} in queue)`;
        }
      }

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

      document.addEventListener('click', function(e) {
        const btn = e.target.closest('.generate-gif-btn');
        if (!btn) return;

        e.preventDefault();
        e.stopPropagation();

        const timerId = btn.getAttribute('data-timer-id');
        const userId = btn.getAttribute('data-timer-user-id');
        const timerStatus = btn.getAttribute('data-timer-status');
        const url = btn.getAttribute('data-timer-url');
        const btnContent = btn.querySelector('.btn-content');
        const btnLoader = btn.querySelector('.btn-loader');

        // Disable button and show loader
        btn.disabled = true;
        btnContent.classList.add('d-none');
        btnLoader.classList.remove('d-none');

        // Show global overlay
        showOverlay(timerId);

        // Make AJAX request
        const formData = new FormData();
        formData.append('_token', document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 
                              document.querySelector('input[name="_token"]')?.value || '');

        fetch(url, {
          method: 'POST',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
          },
          body: formData
        })
        .then(response => {
          if (!response.ok) {
            throw new Error('HTTP error! status: ' + response.status);
          }
          return response.json();
        })
        .then(data => {
          if (data.ok) {
            // Update preview if URL provided
            if (data.gif_url) {
              // Ensure URL is absolute
              const gifUrl = data.gif_url.startsWith('http') ? data.gif_url : (window.location.origin + data.gif_url);
              updatePreview(timerId, gifUrl);
              
              // Hide the generate button since GIF is now generated
              btn.style.display = 'none';
              
              // Show embed copy button if timer is active
              if (timerStatus === 'active') {
                const embedBtn = document.querySelector('.copy-embed-btn-' + timerId);
                if (embedBtn) {
                  // Extract filename from gif_url to build embed URL
                  // gif_url format: /timers/{userId}/{filename}.gif or full URL
                  let gifPath = data.gif_url;
                  if (gifPath.startsWith('http')) {
                    // Extract path from full URL
                    const urlObj = new URL(gifPath);
                    gifPath = urlObj.pathname;
                  }
                  const pathParts = gifPath.split('/');
                  const filenameWithExt = pathParts[pathParts.length - 1]; // e.g., "filename.gif"
                  const filename = filenameWithExt.replace('.gif', ''); // Remove extension
                  
                  // Use userId from button data attribute
                  if (userId && filename) {
                    const embedUrl = window.location.origin + '/gettimer/' + userId + '/' + filename + '.gif';
                    embedBtn.setAttribute('data-embed-url', embedUrl);
                    embedBtn.classList.remove('d-none');
                  }
                }
              }
            }

            // Show success message
            if (window.showToast) {
              window.showToast(data.message || 'GIF generated successfully!', 'success');
            } else {
              alert(data.message || 'GIF generated successfully!');
            }
          } else {
            throw new Error(data.message || 'Failed to generate GIF');
          }
        })
        .catch(error => {
          console.error('GIF generation error:', error);
          if (window.showToast) {
            window.showToast(error.message || 'Failed to generate GIF. Please try again.', 'danger');
          } else {
            alert(error.message || 'Failed to generate GIF. Please try again.');
          }
        })
        .finally(() => {
          // Re-enable button and hide loader
          btn.disabled = false;
          btnContent.classList.remove('d-none');
          btnLoader.classList.add('d-none');
          hideOverlay(timerId);
        });
      });
    })();
    </script>
</x-app-layout>
