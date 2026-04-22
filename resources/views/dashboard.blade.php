<x-app-layout>
    <x-slot name="header">
        {{-- <div class="d-flex justify-content-between align-items-center">
            <h2 class="fw-semibold mb-0">
                {{ __('Your Counters') }}
            </h2>
        </div> --}}
    </x-slot>
<style>
  
.promotion-box-main {
  padding-inline: 25px;
  margin-bottom: 34px;
  padding-block: 10px;
}
.promotion-box-main .card-body {
  display: flex;
  align-items: center;
}
.promotion-box-main h4{
  margin-bottom: 0;
  width: 115px;
  font-size: 20px;
}
.promotion-box-main ul{
  display: grid;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  padding-left: 0;
  margin: 0;
  grid-template-columns: 1fr 1fr 1fr 1fr 1fr 1fr 1fr;
}
.promotion-box-main ul li{
  display: flex;
  align-items: center;
  justify-content: center;
  border-right: solid 1px #d4d4d4;
}
.promotion-box-main ul li:last-child{
  border-right: 0;
}
.promotion-box-main ul li img{
  width: 35px;
}
.promotion-box-main ul li p{
  margin-bottom: 0;
  margin-left: 15px;
}




@media (max-width: 1440px){
  .promotion-box-main {
    padding-inline: 10px;
  }
}




@media (max-width: 1199px){
  .promotion-box-main .card-body {
      display: block;
    }
    .promotion-box-main h4 {
      margin-bottom: 20px;
    }
  .promotion-box-main ul{
    grid-template-columns: 1fr 1fr 1fr;
  }
  .promotion-box-main ul li{
    margin-bottom: 20px;
    justify-content: flex-start;
    border-right: 0;
  }
}




@media (max-width: 767px){
  .promotion-box-main ul{
    grid-template-columns: 1fr 1fr;
  }

}
</style>
    <div class="py-2 py-md-3 pt-0 pt-md-0">
        <div class="container-fluid">
          <div class="" style="display: flex;
    flex-direction: row;
    justify-content: end;
    padding-bottom: 0.5rem;">
    {{-- <a href="{{ route('timers.create') }}" class="btn btn-primary text-decoration-none fw-semibold {{ request()->routeIs('timers.create') ? 'text-primary' : 'text-dark' }}" style="color:white !important">Create New</a> --}}
                                </div>
            <div class="rounded">
              <div class="card shadow-sm border-0 promotion-box-main">
                <div class="card-body ">
                        <h4>Perfect for:</h4>
                        <ul>
                                <li>
                                        <img src="{{ asset("assets/icons/sales.png") }}" alt="Sales" />
                                        <p>Sales </p>
                                </li>
                                <li>
                                        <img src="{{ asset("assets/icons/calendar.png") }}" alt="Calendar" />
                                        <p>Events  </p>
                                </li>
                                <li>
                                        <img src="{{ asset("assets/icons/gift.png") }}" alt="Gift" />
                                        <p>Giveaways  </p>
                                </li>
                                <li>
                                        <img src="{{ asset("assets/icons/startup.png") }}" alt="Startup" />
                                        <p>Launches  </p>
                                </li>
                                <li>
                                        <img src="{{ asset("assets/icons/undo.png") }}" alt="Replay" />
                                        <p>Replays </p>
                                </li>
                                <li>
                                        <img src="{{ asset("assets/icons/trophy.png") }}" alt="Trophy" />
                                        <p>Challenges  </p>
                                </li>
                                <li>
                                        <img src="{{ asset("assets/icons/deadline.png") }}" alt="Deadline" />
                                        <p>Deadlines </p>
                                </li>
                        </ul>
                </div>
        </div>
                <div class="">
                    @if(isset($timers) && $timers->isEmpty())
                        <div class="alert alert-info">No timers yet. Create your first one!</div>
                    @else
                        @php($gifQueueMode = config('app.gif_generation_mode', 'queue') === 'queue')
                        <div class="row g-3" id="dashboard-timers-row">
                            @foreach($timers as $timer)
                            @php($dashboardGifPending = $gifQueueMode && $timer->status === 'active' && ($timer->needsGifRegeneration() || empty($timer->gif_url)))
                            <div class="col-12 col-md-3" data-timer-card data-timer-id="{{ $timer->id }}" @if($dashboardGifPending) data-gif-pending="1" @endif>
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
                                        @if($timer->status === 'active')
                                          @if($timer->timer_type == 'fixed_date' && $timer->target_datetime)
                                            <span class="badge bg-secondary">Timer ends on {{ $timer->target_datetime->format('M d, Y g:i A') }}</span>
                                          @elseif($timer->timer_type == 'reload_based' && $timer->duration_seconds)
                                            <span class="badge bg-secondary">Timer restarts in {{ $timer->duration_seconds }} seconds</span>
                                          @endif
                                        @endif
                                      </div>
                                    </div>
                              
                                    <!-- Preview -->
                                    <div class="mt-3 position-relative border rounded overflow-hidden bg-light timer-preview-{{ $timer->id }}" style="height: 160px;">
                                      @if($timer->gif_url)
                                        <img src="{{ url($timer->gif_url_with_cache_bust ?? $timer->gif_url) }}" alt="Timer Preview" class="img-fluid w-100 h-100 object-fit-contain timer-preview-img-{{ $timer->id }}">
                                        @if($timer->status === 'active' && $timer->needsGifRegeneration())
                                          <div class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center"
                                               style="background: rgba(255,255,255,0.75);">
                                            <small class="text-muted fw-semibold">Counter Updating…</small>
                                          </div>
                                        @endif
                                      @else
                                        @if($timer->status === 'active')
                                          <div class="d-flex flex-column align-items-center justify-content-center h-100 text-muted timer-preview-placeholder-{{ $timer->id }}">
                                            <small>GIF is being generated</small>
                                          </div>
                                        @else
                                          {{-- Inactive + no GIF: show nothing in preview --}}
                                          <div class="h-100 w-100 timer-preview-placeholder-{{ $timer->id }}"></div>
                                        @endif
                                      @endif
                                    </div>
                              
                                    <!-- Actions -->
                                    <div class="mt-3 d-flex align-items-center">
                                      <div class="flex-grow-1"></div>
                                      <div class="btn-group" role="group">
                                        @php($embedCopyUrl = ($timer->gif_url && !$timer->needsGifRegeneration()) ? url('/gettimer/'.$timer->user_id.'/'.pathinfo($timer->gif_url, PATHINFO_FILENAME)).'.gif' : null)
                                        @php($active = $timer->status === 'active')
                                        @php($copyEmbedReady = $active && !$dashboardGifPending && $embedCopyUrl)
                                        @php($copyGifPending = $active && $dashboardGifPending)
                                        <a href="{{ $dashboardGifPending ? '#' : route('timers.preview', $timer) }}"
                                           class="btn btn-outline-secondary btn-sm dashboard-preview-btn {{ $dashboardGifPending ? 'disabled' : '' }}"
                                           data-preview-url="{{ route('timers.preview', $timer) }}"
                                           @if($dashboardGifPending) title="Processing" tabindex="-1" aria-disabled="true" @else title="Preview" @endif
                                        >
                                          <i class="bi bi-eye"></i>
                                        </a>
                                        <button type="button"
                                                class="btn btn-outline-secondary btn-sm copy-embed-code-btn copy-embed-code-btn-{{ $timer->id }} {{ $copyGifPending ? 'disabled' : '' }} {{ ($copyEmbedReady || $copyGifPending) ? '' : 'd-none' }}"
                                                title="{{ $copyGifPending ? 'Processing' : 'Embed code' }}"
                                                data-gif-url="{{ $copyEmbedReady ? $embedCopyUrl : '' }}"
                                                style="margin-left: 2px;"
                                                @if($copyGifPending) disabled aria-disabled="true" @endif
                                        >
                                          <i class="bi bi-clipboard"></i>
                                        </button>
                                        <button type="button"
                                                class="btn btn-outline-secondary btn-sm copy-gif-url-btn copy-gif-url-btn-{{ $timer->id }} {{ $copyGifPending ? 'disabled' : '' }} {{ ($copyEmbedReady || $copyGifPending) ? '' : 'd-none' }}"
                                                title="{{ $copyGifPending ? 'Processing' : 'Copy direct image URL (Suitable for Landingly)' }}"
                                                data-gif-url="{{ $copyEmbedReady ? $embedCopyUrl : '' }}"
                                                style="margin-left: 2px;"
                                                @if($copyGifPending) disabled aria-disabled="true" @endif
                                        >
                                          <i class="bi bi-copy"></i>
                                        </button>
                                        <a href="{{ route('timers.edit', $timer) }}" class="btn btn-outline-primary btn-sm" title="Edit" style="margin-left: 2px;">
                                          <i class="bi bi-pencil"></i>
                                        </a>
                                      </div>
                                      <div class="flex-grow-1 d-flex justify-content-end">
                                        <form action="{{ route('timers.destroy', $timer) }}" method="POST" data-ajax-delete class="d-inline">
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

    <div id="dashboard-gif-poll-config" class="d-none" data-gif-status-url="{{ route('timers.gif-status') }}"></div>
    <script>
    // AJAX delete (capture phase runs before layout's data-confirm handler)
    document.addEventListener('submit', function(e){
      var form = e.target && e.target.matches('form[data-ajax-delete]') ? e.target : null;
      if (!form) return;
      e.preventDefault();
      e.stopImmediatePropagation();
      if (typeof Swal === 'undefined') {
        if (window.confirm('Delete this timer?')) {
          submitDashboardDelete(form);
        }
        return;
      }
      Swal.fire({
        text: 'Delete this timer?',
        icon: 'warning',
        iconHtml: '<i class="fa-solid fa-trash-can"></i>',
        iconColor: '#dc3545',
        showCancelButton: true,
        confirmButtonText: 'Delete',
        cancelButtonText: 'Cancel',
        reverseButtons: true,
        width: 360,
        customClass: {
          popup: 'swal2-confirm-delete',
          confirmButton: 'btn btn-danger',
          cancelButton: 'btn btn-secondary'
        },
        buttonsStyling: false
      }).then(function(result){
        if (result.isConfirmed) submitDashboardDelete(form);
      });
    }, true);

    function submitDashboardDelete(form){
      var btn = form.querySelector('button[type="submit"]');
      if (btn) { btn.disabled = true; }
      fetch(form.action, {
        method: 'POST',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') || {}).content || ''
        },
        body: new FormData(form),
        credentials: 'same-origin'
      }).then(function(res){
        if (res.ok) {
          var col = form.closest('[data-timer-card]');
          if (col) col.remove();
          var row = document.getElementById('dashboard-timers-row');
          if (row && !row.querySelector('[data-timer-card]')) {
            var wrap = row.closest('.rounded');
            if (wrap) {
              wrap.innerHTML = '<div class="alert alert-info">No timers yet. Create your first one!</div>';
            }
          }
          if (window.showToast) window.showToast('Timer deleted', 'success');
          return;
        }
        return res.json().catch(function(){ return {}; }).then(function(err){
          var msg = (err && err.message) ? err.message : 'Could not delete counter';
          if (window.showToast) window.showToast(msg, 'danger');
        });
      }).catch(function(){
        if (window.showToast) window.showToast('Could not delete counter', 'danger');
      }).finally(function(){
        if (btn) { btn.disabled = false; }
      });
    }

    document.addEventListener('click', function(e){
      var prev = e.target.closest('a.dashboard-preview-btn.disabled');
      if (prev) e.preventDefault();
      var copyBtn = e.target.closest('.copy-embed-code-btn, .copy-gif-url-btn');
      if (copyBtn && (copyBtn.disabled || copyBtn.classList.contains('disabled'))) {
        e.preventDefault();
        e.stopPropagation();
      }
    }, true);
    // Copy buttons for dashboard cards
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
      var btn = e.target.closest('.copy-embed-code-btn, .copy-gif-url-btn');
      if (!btn) return;
      if (btn.disabled || btn.classList.contains('disabled')) return;
      var url = btn.getAttribute('data-gif-url');
      if (!url) return;

      if (btn.classList.contains('copy-embed-code-btn')) {
        var code = `<img src="${url}" alt="Countdown Counter">`;
        window.copyToClipboard(code).then(function(){
          if (window.showToast) window.showToast('Embed code copied', 'success');
        }).catch(function(){
          if (window.showToast) window.showToast('Copy failed', 'danger');
        });
        return;
      }

      window.copyToClipboard(url).then(function(){
        if (window.showToast) window.showToast('Direct image URL copied', 'success');
      }).catch(function(){
        if (window.showToast) window.showToast('Copy failed', 'danger');
      });
    });

    // Queue mode: poll only counters on this dashboard page (current pagination slice).
    // Batched GIF status: drop IDs from the request once they are no longer pending in the DOM.
    (function dashboardGifBatchPoll(){
      if (!document.body || document.body.getAttribute('data-gif-generation-mode') !== 'queue') return;
      var pending = [];
      document.querySelectorAll('[data-timer-card][data-gif-pending="1"]').forEach(function(card){
        var id = card.getAttribute('data-timer-id');
        if (id) pending.push(String(id));
      });
      if (!pending.length) return;

      var pollGapMs = 2800;
      var cfg = document.getElementById('dashboard-gif-poll-config');
      var gifStatusUrl = (cfg && cfg.getAttribute('data-gif-status-url')) || '/timers/gif-status';

      function activateDashboardTimerCard(timerId, t) {
        var card = document.querySelector('[data-timer-card][data-timer-id="' + String(timerId) + '"]');
        if (!card) return;
        card.removeAttribute('data-gif-pending');
        var previewA = card.querySelector('.dashboard-preview-btn');
        if (previewA) {
          var pUrl = previewA.getAttribute('data-preview-url');
          previewA.classList.remove('disabled');
          previewA.removeAttribute('tabindex');
          previewA.removeAttribute('aria-disabled');
          if (pUrl) previewA.setAttribute('href', pUrl);
          previewA.setAttribute('title', 'Preview');
        }
        var wrap = card.querySelector('.timer-preview-' + timerId);
        if (wrap && (t.gif_url_with_cache_bust || t.gif_url)) {
          var abs = t.gif_url_with_cache_bust || t.gif_url;
          var ph = wrap.querySelector('.timer-preview-placeholder-' + timerId);
          if (ph) ph.remove();
          var overlay = wrap.querySelector('.position-absolute');
          if (overlay) overlay.remove();
          var img = wrap.querySelector('.timer-preview-img-' + timerId);
          if (!img) {
            wrap.innerHTML = '';
            img = document.createElement('img');
            img.className = 'img-fluid w-100 h-100 object-fit-contain timer-preview-img-' + timerId;
            img.alt = 'Timer Preview';
            wrap.appendChild(img);
          }
          var src = abs.indexOf('http') === 0 ? abs : (window.location.origin + (abs.charAt(0) === '/' ? abs : '/' + abs));
          var sep = src.indexOf('?') >= 0 ? '&' : '?';
          img.src = src + sep + '_t=' + Date.now();
        }
        var embedBtn = card.querySelector('.copy-embed-code-btn-' + timerId);
        var linkBtn = card.querySelector('.copy-gif-url-btn-' + timerId);
        if (t.embed_url && t.status === 'active') {
          [embedBtn, linkBtn].forEach(function(b){
            if (!b) return;
            b.removeAttribute('disabled');
            b.removeAttribute('aria-disabled');
            b.classList.remove('disabled');
            b.setAttribute('data-gif-url', t.embed_url);
            b.classList.remove('d-none');
          });
          if (embedBtn) embedBtn.setAttribute('title', 'Embed code');
          if (linkBtn) linkBtn.setAttribute('title', 'Copy direct image URL (Suitable for Landingly)');
        }
      }

      function tick(){
        if (!pending.length) return;
        if (!gifStatusUrl) return;
        var qs = 'ids=' + encodeURIComponent(pending.join(','));
        fetch(gifStatusUrl + '?' + qs, {
          headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
          .then(function(r){ return r.json(); })
          .then(function(data){
            var timers = data.timers || {};
            pending = pending.filter(function(id){
              var t = timers[id];
              if (t && t.gif_ready) {
                activateDashboardTimerCard(id, t);
                return false;
              }
              return true;
            });
            if (pending.length) setTimeout(tick, pollGapMs);
          })
          .catch(function(){
            if (pending.length) setTimeout(tick, pollGapMs);
          });
      }
      setTimeout(tick, 800);
    })();
    </script>
</x-app-layout>
