<article class="rounded-[10px] border border-white/15 bg-[#151515] p-[20px]">
    <h2 class="text-[16px] font-semibold text-white">Organization</h2>
    <p class="mt-[4px] text-[12px] text-[#a9a9a9]">Company details shown on reports and account context.</p>

    <form method="post" action="{{ route('profile.update') }}" class="mt-[16px] space-y-[14px]">
        @csrf
        @method('patch')

        <div>
            <label for="company_name" class="mb-[6px] block text-[12px] font-semibold text-[#a9a9a9]">Company / organization</label>
            <input id="company_name" name="company_name" type="text" value="{{ old('company_name', $user->company_name) }}" maxlength="255" autocomplete="organization"
                class="figma-input" placeholder="Your company name">
            @foreach ($errors->get('company_name') as $message)
                <p class="mt-[6px] text-[12px] text-rose-300">{{ $message }}</p>
            @endforeach
        </div>

        <div class="flex flex-wrap items-center gap-[12px] pt-[4px]">
            <button type="submit" class="rounded-[6px] bg-[#6400B2] px-[18px] py-[9px] text-[13px] font-semibold text-white hover:bg-[#7a1acc]">Save organization</button>
            @if (session('status') === 'profile-updated')
                <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2500)" class="text-[12px] text-emerald-300">Saved.</p>
            @endif
        </div>
    </form>
</article>
