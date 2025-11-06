<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fw-semibold mb-0">
                {{ __('Your Timers') }}
            </h2>
        </div>
    </x-slot>

    <div class="py-4 py-md-5">
        <div class="container-fluid">
            <div class="rounded">
                <div class="">
                    @if(isset($timers) && $timers->isEmpty())
                        <div class="alert alert-info">No timers yet. Create your first one!</div>
                    @else
                        <div class="row g-3">
                            @foreach($timers as $timer)
                            <div class="col-12 col-md-3">
                                <div class="card shadow-sm border-0 h-100">
                                  <div class="card-body d-flex flex-column justify-content-between">
                                    <!-- Title -->
                                    <h5 class="card-title fw-bold text-dark mb-2">{{ $timer->title }}</h5>
                              
                                    <!-- Metadata -->
                                    <div class="small text-muted mb-3">
                                      <div class="mb-1">
                                        Type:
                                        <span class="badge bg-secondary">{{ $timer->timer_type }}</span>
                                      </div>
                                      <div class="mb-1">
                                        Skin:
                                        <span class="text-dark fw-semibold">{{ $timer->skin }}</span>
                                      </div>
                                      <div>
                                        Status:
                                        <span class="badge {{ $timer->status === 'active' ? 'bg-success' : 'bg-danger' }}">
                                          {{ ucfirst($timer->status) }}
                                        </span>
                                      </div>
                                    </div>
                              
                                    <!-- Preview -->
                                    <div class="mt-3 position-relative border rounded overflow-hidden bg-light" style="height: 160px;">
                                      @if($timer->gif_url)
                                        <img src="{{ url($timer->gif_url) }}" alt="Timer Preview" class="img-fluid w-100 h-100 object-fit-contain">
                                      @else
                                        <div class="d-flex flex-column align-items-center justify-content-center h-100 text-muted">
                                          <div class="spinner-border text-primary mb-2" role="status"></div>
                                          <small>Generating Preview...</small>
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
                                        @if($embedCopyUrl)
                                          <button type="button" class="btn btn-outline-secondary btn-sm copy-embed-btn" title="Copy code" data-embed-url="{{ $embedCopyUrl }}" style="margin-left: 2px;">
                                            <i class="bi bi-clipboard"></i>
                                          </button>
                                        @endif
                                        <a href="{{ route('timers.edit', $timer) }}" class="btn btn-outline-primary btn-sm" title="Edit" style="margin-left: 2px;">
                                          <i class="bi bi-pencil"></i>
                                        </a>
                                        <form action="{{ route('timers.destroy', $timer) }}" method="POST" onsubmit="return confirm('Delete this timer?');" class="d-inline">
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
    <script>
    // Copy embed code for dashboard cards
    document.addEventListener('click', function(e){
      var btn = e.target.closest('.copy-embed-btn');
      if (!btn) return;
      var url = btn.getAttribute('data-embed-url');
      if (!url) return;
      var code = `<img src="${url}" alt="Countdown Timer">`;
      navigator.clipboard.writeText(code).then(function(){
        if (window.showToast) window.showToast('Embed code copied', 'success');
      }).catch(function(){
        if (window.showToast) window.showToast('Copy failed', 'danger');
      });
    });
    </script>
</x-app-layout>
