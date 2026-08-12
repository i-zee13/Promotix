@extends('layouts.admin')

@section('title', 'Account settings')

@section('content')
<div class="brand-page-bg min-h-[calc(100vh-49px)]" x-data="{ showDelete: @json($errors->userDeletion->isNotEmpty()) }">
    <section class="w-full px-[12px] pb-[32px] pt-[28px] sm:px-[18px] xl:px-[24px] xl:pt-[56px]">
        <h1 class="mb-[6px] text-[28px] font-semibold text-[#a9a9a9] sm:text-[36px]">Account settings</h1>
        <p class="mb-[24px] text-[13px] text-[#8c8787]">Manage profile, security, and organization details.</p>

        <div class="grid items-start gap-[16px] xl:grid-cols-12">
            <div class="space-y-[16px] xl:col-span-7">
                <div id="profile-information-section">
                    @include('profile.partials.update-profile-information-form')
                </div>
                @include('profile.partials.organization-form')
                <div id="password-security-section">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            <div class="space-y-[16px] xl:col-span-5">
                <div id="security-controls-section">
                    @include('profile.partials.security-controls')
                </div>
                <div id="login-history-section">
                    @include('profile.partials.login-history')
                </div>
                @include('profile.partials.delete-user-form')
            </div>
        </div>
    </section>

    <div x-show="showDelete" x-cloak class="fixed inset-0 z-[80] flex items-center justify-center bg-black/70 p-4" @keydown.escape.window="showDelete = false">
        <div class="w-full max-w-[440px] rounded-[10px] border border-white/20 bg-[#151515] p-[22px] shadow-[0_0_24px_rgba(100,0,179,.35)]" @click.outside="showDelete = false">
            <h2 class="text-[18px] font-semibold text-white">Delete account?</h2>
            <p class="mt-[8px] text-[13px] text-[#a9a9a9]">
                This permanently removes your account and data. Enter your password to confirm.
            </p>
            <form method="post" action="{{ route('profile.destroy') }}" class="mt-[16px]">
                @csrf
                @method('delete')
                <label for="password" class="mb-[6px] block text-[12px] font-semibold text-[#a9a9a9]">Password</label>
                <input id="password" name="password" type="password" required autocomplete="current-password"
                    class="figma-input"
                    placeholder="Your password">
                @foreach ($errors->userDeletion->get('password') as $message)
                    <p class="mt-[6px] text-[12px] text-rose-300">{{ $message }}</p>
                @endforeach
                <div class="mt-[18px] flex flex-wrap justify-end gap-[10px]">
                    <button type="button" @click="showDelete = false" class="rounded-[6px] border border-white/25 px-[14px] py-[9px] text-[13px] font-medium text-white hover:bg-white/5">Cancel</button>
                    <button type="submit" class="rounded-[6px] border border-rose-400/50 bg-rose-500/15 px-[14px] py-[9px] text-[13px] font-semibold text-rose-200 hover:bg-rose-500/25">Delete account</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
