{{-- Succesmelding --}}
@if (session('success'))
    <div
        class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200">
        {{ session('success') }}
    </div>
@endif

{{-- Foutmelding --}}
@if (session('error'))
    <div
        class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
        {{ session('error') }}
    </div>
@endif
