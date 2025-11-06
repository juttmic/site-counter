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
                                <div class="col-12 col-md-6">
                                    <div class="card h-100">
                                        <div class="card-body">
                                            <h5 class="card-title fw-semibold">{{ $timer->title }}</h5>
                                            <div class="small text-muted mb-2">
                                                <div class="mb-1">
                                                    Type: <span class="badge bg-secondary">{{ $timer->timer_type }}</span>
                                                </div>
                                                <div class="mb-1">
                                                    Skin: {{ $timer->skin }}
                                                </div>
                                                <div>
                                                    Status: <span class="badge {{ $timer->status==='active' ? 'bg-success' : 'bg-danger' }}">{{ $timer->status }}</span>
                                                </div>
                                            </div>
                                            <div class="mt-3">
                                                @if($timer->gif_url)
                                                    <div class="d-flex align-items-center justify-content-center overflow-hidden bg-light border rounded" style="height: 160px;">
                                                        @php($embedUrl = url('/gettimer/'.$timer->user_id.'/'.pathinfo($timer->gif_url, PATHINFO_FILENAME)).'.gif')
                                                        <img src="{{ $embedUrl }}" alt="preview" class="img-fluid" style="max-height: 160px; max-width: 100%; object-fit: contain;">
                                                    </div>
                                                @else
                                                    <div class="border rounded bg-light d-flex align-items-center justify-content-center" style="height: 160px;">
                                                        <div class="text-center text-muted">
                                                            <div class="spinner-border spinner-border-sm text-primary mb-2" role="status">
                                                                <span class="visually-hidden">Loading...</span>
                                                            </div>
                                                            <div class="small">Generating GIF...</div>
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                            <div class="mt-3 d-flex gap-2 flex-wrap">
                                                <a class="btn btn-outline-secondary btn-sm" href="{{ route('timers.preview', $timer) }}">Preview</a>
                                                <a class="btn btn-primary btn-sm" href="{{ route('timers.edit', $timer) }}">Edit</a>
                                                <form action="{{ route('timers.destroy', $timer) }}" method="POST" onsubmit="return confirm('Delete this timer?');" class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                                </form>
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
</x-app-layout>
