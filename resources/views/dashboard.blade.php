<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Your Timers') }}
            </h2>
            <a href="{{ route('timers.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">Create Timer</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @if(isset($timers) && $timers->isEmpty())
                        <div class="p-4 bg-blue-50 text-blue-800 rounded">No timers yet. Create your first one!</div>
                    @else
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            @foreach($timers as $timer)
                                <div class="border rounded p-4">
                                    <div class="font-semibold">{{ $timer->title }}</div>
                                    <div class="text-sm text-gray-600 mt-1">
                                        Type: <span class="px-2 py-0.5 bg-gray-100 rounded">{{ $timer->timer_type }}</span>
                                        <br>Skin: {{ $timer->skin }}
                                        <br>Status: <span class="px-2 py-0.5 rounded {{ $timer->status==='active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">{{ $timer->status }}</span>
                                    </div>
                                    <div class="mt-3">
                                        @if($timer->gif_url)
                                            <img src="{{ url($timer->gif_url) }}" alt="preview" class="border w-full">
                                        @else
                                            <div class="border w-full h-32 bg-gray-100 flex items-center justify-center text-gray-500">
                                                <div class="text-center">
                                                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mx-auto mb-2"></div>
                                                    <div class="text-sm">Generating GIF...</div>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="mt-3 flex gap-2">
                                        <a class="px-3 py-1 border rounded" href="{{ route('timers.preview', $timer) }}">Preview</a>
                                        <a class="px-3 py-1 border rounded bg-indigo-600 text-white" href="{{ route('timers.edit', $timer) }}">Edit</a>
                                        <form action="{{ route('timers.destroy', $timer) }}" method="POST" onsubmit="return confirm('Delete this timer?');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="px-3 py-1 border rounded bg-red-600 text-white">Delete</button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-4">{{ $timers->links() }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
