<x-guest-layout>
    <div class="text-center">
        <h1 class="h5 fw-semibold mb-2">This countdown timer is no longer available.</h1>
        <p class="text-muted mb-4">If you’d like to create your own countdown timer, click here to get started.</p>

        <a href="{{ $startUrl ?? url('/login') }}" class="btn btn-primary w-100">
            Get started
        </a>
    </div>
</x-guest-layout>

