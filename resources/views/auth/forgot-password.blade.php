@extends('layouts.auth')

@section('content')
<div class="min-h-screen flex items-center justify-center p-4 sm:p-6 lg:p-8">
    <div class="w-full max-w-md rounded-2xl bg-gray-200 p-8 shadow-2xl">
        <h1 class="mb-2 text-2xl font-bold text-gray-900">Forgot Password</h1>
        <p class="mb-6 text-sm text-gray-700">
            Enter your email and we will send you a reset link.
        </p>

        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-300 bg-red-100 px-3 py-2 text-sm text-red-700">
                {{ $errors->first() }}
            </div>
        @endif

        @if (session('status'))
            <div class="mb-4 rounded-lg border border-emerald-300 bg-emerald-100 px-3 py-2 text-sm text-emerald-700">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
            @csrf

            <div>
                <label for="email" class="mb-1 block text-sm font-medium text-gray-800">Email</label>
                <input
                    id="email"
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    required
                    autofocus
                    class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-gray-900 outline-none transition focus:border-purple-500 focus:ring-2 focus:ring-purple-500/30"
                >
            </div>

            <button type="submit" class="w-full rounded-xl bg-purple-600 px-4 py-3 font-medium text-white transition hover:bg-purple-700">
                Email Password Reset Link
            </button>
        </form>
    </div>
</div>
@endsection
