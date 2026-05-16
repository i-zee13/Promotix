<article class="rounded-[10px] border border-white/15 bg-[#151515] p-[20px]">
    <h2 class="text-[16px] font-semibold text-white">Password</h2>
    <p class="mt-[4px] text-[12px] text-[#a9a9a9]">Use a long, random password to keep your account secure.</p>

    <form method="post" action="{{ route('password.update') }}" class="mt-[16px] space-y-[14px]">
        @csrf
        @method('put')

        <div>
            <label for="update_password_current_password" class="mb-[6px] block text-[12px] font-semibold text-[#a9a9a9]">Current password</label>
            <input id="update_password_current_password" name="current_password" type="password" autocomplete="current-password"
                class="figma-input">
            @foreach ($errors->updatePassword->get('current_password') as $message)
                <p class="mt-[6px] text-[12px] text-rose-300">{{ $message }}</p>
            @endforeach
        </div>

        <div>
            <label for="update_password_password" class="mb-[6px] block text-[12px] font-semibold text-[#a9a9a9]">New password</label>
            <input id="update_password_password" name="password" type="password" autocomplete="new-password"
                class="figma-input">
            @foreach ($errors->updatePassword->get('password') as $message)
                <p class="mt-[6px] text-[12px] text-rose-300">{{ $message }}</p>
            @endforeach
        </div>

        <div>
            <label for="update_password_password_confirmation" class="mb-[6px] block text-[12px] font-semibold text-[#a9a9a9]">Confirm password</label>
            <input id="update_password_password_confirmation" name="password_confirmation" type="password" autocomplete="new-password"
                class="figma-input">
            @foreach ($errors->updatePassword->get('password_confirmation') as $message)
                <p class="mt-[6px] text-[12px] text-rose-300">{{ $message }}</p>
            @endforeach
        </div>

        <div class="flex flex-wrap items-center gap-[12px] pt-[4px]">
            <button type="submit" class="rounded-[6px] bg-[#6400B2] px-[18px] py-[9px] text-[13px] font-semibold text-white hover:bg-[#7a1acc]">Update password</button>
            @if (session('status') === 'password-updated')
                <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2500)" class="text-[12px] text-emerald-300">Password updated.</p>
            @endif
        </div>
    </form>
</article>
