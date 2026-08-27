@php
    $tones = [
        'amber' => 'from-amber-500/20 to-orange-500/10 text-amber-100 ring-amber-200/20',
        'emerald' => 'from-emerald-500/20 to-teal-500/10 text-emerald-100 ring-emerald-200/20',
        'blue' => 'from-sky-500/20 to-blue-500/10 text-sky-100 ring-sky-200/20',
    ];
@endphp

<div class="rounded-3xl border border-white/10 bg-gradient-to-br {{ $tones[$tone] ?? $tones['amber'] }} p-6 ring-1 shadow-xl shadow-black/10">
    <p class="text-sm uppercase tracking-[0.3em] text-white/60">{{ $label }}</p>
    <p class="mt-4 text-4xl font-semibold text-white">{{ $value }}</p>
</div>
