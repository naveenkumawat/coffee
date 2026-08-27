@extends('layouts.app')

@section('content')
    <div class="mx-auto flex min-h-screen max-w-3xl items-center justify-center px-6 py-20">
        <div class="rounded-[2rem] border border-rose-300/20 bg-white/5 p-10 text-center">
            <p class="text-sm uppercase tracking-[0.35em] text-rose-200">403</p>
            <h1 class="mt-4 text-4xl font-semibold text-white">That area is outside your role.</h1>
            <p class="mt-4 text-stone-300">The foundation is enforcing authorization policies correctly. Sign in with the right admin role or return to the public menu.</p>
            <a href="{{ route('home') }}" class="mt-8 inline-flex rounded-full bg-amber-400 px-6 py-3 font-medium text-stone-950">Return home</a>
        </div>
    </div>
@endsection
