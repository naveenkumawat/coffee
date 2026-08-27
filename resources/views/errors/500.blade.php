@extends('layouts.app')

@section('content')
    <div class="mx-auto flex min-h-screen max-w-3xl items-center justify-center px-6 py-20">
        <div class="rounded-[2rem] border border-white/10 bg-white/5 p-10 text-center">
            <p class="text-sm uppercase tracking-[0.35em] text-sky-200">500</p>
            <h1 class="mt-4 text-4xl font-semibold text-white">Something unexpected happened.</h1>
            <p class="mt-4 text-stone-300">The application logs the request context and exception details, so this failure should be traceable without exposing sensitive internals to users.</p>
            <a href="{{ route('home') }}" class="mt-8 inline-flex rounded-full bg-amber-400 px-6 py-3 font-medium text-stone-950">Return home</a>
        </div>
    </div>
@endsection
