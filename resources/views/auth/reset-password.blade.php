@extends('layouts.auth')

@section('content')
<div class="min-h-screen flex items-center justify-center p-4 sm:p-6 lg:p-8">
    <div class="w-full max-w-md rounded-2xl bg-gray-200 p-8 shadow-2xl">
        <h1 class="mb-6 text-2xl font-bold text-gray-900">Reset Password</h1>

        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-300 bg-red-100 px-3 py-2 text-sm text-red-700">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('password.store') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <div>
                <label for="email" class="mb-1 block text-sm font-medium text-gray-800">Email</label>
                <input
                    id="email"
                    type="email"
                    name="email"
                    value="{{ old('email', $request->email) }}"
                    required
                    autofocus
                    autocomplete="username"
                    class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-gray-900 outline-none transition focus:border-purple-500 focus:ring-2 focus:ring-purple-500/30"
                >
            </div>

            <div>
                <label for="password" class="mb-1 block text-sm font-medium text-gray-800">Password</label>
                <input
                    id="password"
                    type="password"
                    name="password"
                    required
                    autocomplete="new-password"
                    class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-gray-900 outline-none transition focus:border-purple-500 focus:ring-2 focus:ring-purple-500/30"
                >
            </div>

            <div>
                <label for="password_confirmation" class="mb-1 block text-sm font-medium text-gray-800">Confirm Password</label>
                <input
                    id="password_confirmation"
                    type="password"
                    name="password_confirmation"
                    required
                    autocomplete="new-password"
                    class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-gray-900 outline-none transition focus:border-purple-500 focus:ring-2 focus:ring-purple-500/30"
                >
            </div>

            <button type="submit" class="w-full rounded-xl bg-purple-600 px-4 py-3 font-medium text-white transition hover:bg-purple-700">
                Reset Password
            </button>
        </form>
    </div>
</div>
@endsection
