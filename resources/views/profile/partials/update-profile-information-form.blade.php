<article class="rounded-[10px] border border-white/15 bg-[#151515] p-[20px]">
    <h2 class="text-[16px] font-semibold text-white">Profile information</h2>
    <p class="mt-[4px] text-[12px] text-[#a9a9a9]">Update your name and email address.</p>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-[16px] space-y-[14px]">
        @csrf
        @method('patch')

        <div>
            <label for="name" class="mb-[6px] block text-[12px] font-semibold text-[#a9a9a9]">Name</label>
            <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required autofocus autocomplete="name"
                class="figma-input">
            @foreach ($errors->get('name') as $message)
                <p class="mt-[6px] text-[12px] text-rose-300">{{ $message }}</p>
            @endforeach
        
        </div>

        <div>
            <label for="email" class="mb-[6px] block text-[12px] font-semibold text-[#a9a9a9]">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required autocomplete="username"
                class="figma-input">
            @foreach ($errors->get('email') as $message)
                <p class="mt-[6px] text-[12px] text-rose-300">{{ $message }}</p>
            @endforeach

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div class="mt-[10px] rounded-[8px] border border-amber-400/30 bg-amber-500/10 px-[12px] py-[10px] text-[12px] text-amber-100">
                    <p>Your email is not verified.</p>
                    <button form="send-verification" type="submit" class="mt-[6px] font-semibold text-white underline hover:text-amber-50">
                        Resend verification email
                    </button>
                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-[6px] text-emerald-300">A new verification link has been sent.</p>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-[12px] pt-[4px]">
            <button type="submit" class="rounded-[6px] bg-[#6400B2] px-[18px] py-[9px] text-[13px] font-semibold text-white hover:bg-[#7a1acc]">Save changes</button>
            @if (session('status') === 'profile-updated')
                <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2500)" class="text-[12px] text-emerald-300">Saved.</p>
            @endif
        </div>
    </form>
</article>
