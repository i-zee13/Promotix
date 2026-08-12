<article class="rounded-[10px] border border-white/15 bg-[#151515] p-[20px]">
    <h2 class="text-[16px] font-semibold text-white">Profile information</h2>
    <p class="mt-[4px] text-[12px] text-[#a9a9a9]">Update your photo, name, email, and timezone.</p>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <div class="mt-[16px] flex flex-wrap items-center gap-[14px] rounded-[8px] border border-white/10 bg-black/20 p-[14px]">
        <div class="flex h-[56px] w-[56px] shrink-0 items-center justify-center overflow-hidden rounded-full border border-[#6400B2]/70 bg-white/10">
            @include('partials.user-avatar', ['avatarUser' => $user, 'avatarTextClass' => 'text-[20px] font-semibold leading-none text-white/90'])
        </div>
        <div class="min-w-0 flex-1">
            <p class="text-[13px] font-semibold text-white">Profile photo</p>
            <p class="mt-[2px] text-[11px] text-[#8c8787]">
                @if (filled($user->avatar_path))
                    Custom upload
                @elseif (filled($user->google_avatar_url))
                    From Google / Gmail
                @else
                    No photo yet — upload one, or connect Google to use your Gmail picture
                @endif
            </p>
            <div class="mt-[10px] flex flex-wrap items-center gap-[8px]">
                <form method="post" action="{{ route('profile.avatar.update') }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-[8px]">
                    @csrf
                    <label class="cursor-pointer rounded-[6px] border border-white/20 bg-white/5 px-[12px] py-[7px] text-[12px] font-semibold text-white hover:bg-white/10">
                        Change photo
                        <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp,image/gif" class="sr-only" onchange="this.form.submit()">
                    </label>
                </form>
                @if (filled($user->avatar_path))
                    <form method="post" action="{{ route('profile.avatar.destroy') }}" onsubmit="return confirm('Remove custom photo? Google picture will show again if available.');">
                        @csrf
                        @method('delete')
                        <button type="submit" class="rounded-[6px] border border-rose-400/40 px-[12px] py-[7px] text-[12px] font-semibold text-rose-200 hover:bg-rose-500/10">Remove</button>
                    </form>
                @endif
            </div>
            @foreach ($errors->get('avatar') as $message)
                <p class="mt-[6px] text-[12px] text-rose-300">{{ $message }}</p>
            @endforeach
            @if (session('status') === 'avatar-updated')
                <p class="mt-[6px] text-[12px] text-emerald-300">Photo updated.</p>
            @endif
            @if (session('status') === 'avatar-removed')
                <p class="mt-[6px] text-[12px] text-emerald-300">Custom photo removed.</p>
            @endif
        </div>
    </div>

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

        <div id="timezone-settings">
            <label for="profile-timezone" class="mb-[6px] block text-[12px] font-semibold text-[#a9a9a9]">Profile timezone</label>
            @php
                $selectedTimezone = old('timezone', $user->timezone ?: \App\Support\UserTimezone::forUser($user));
                $groupedTimezones = \App\Support\UserTimezone::groupedOptions();
            @endphp
            <select id="profile-timezone" name="timezone" class="figma-input">
                <optgroup label="Common">
                    @foreach (\App\Support\UserTimezone::COMMON as $tz)
                        <option value="{{ $tz }}" @selected($selectedTimezone === $tz)>{{ $tz }}</option>
                    @endforeach
                </optgroup>
                @foreach ($groupedTimezones as $region => $zones)
                    <optgroup label="{{ $region }}">
                        @foreach ($zones as $tz)
                            @continue(in_array($tz, \App\Support\UserTimezone::COMMON, true))
                            <option value="{{ $tz }}" @selected($selectedTimezone === $tz)>{{ $tz }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
            <p class="mt-[6px] text-[11px] text-[#8c8787]">
                Currently: {{ $user->timezone ?: 'Not set yet' }}
                @if ($user->timezone_source)
                    · {{ \App\Support\UserTimezone::sourceLabel($user->timezone_source) }}
                @endif
            </p>
            @foreach ($errors->get('timezone') as $message)
                <p class="mt-[6px] text-[12px] text-rose-300">{{ $message }}</p>
            @endforeach
        </div>

        <div>
            <label for="profile-reporting-timezone" class="mb-[6px] block text-[12px] font-semibold text-[#a9a9a9]">Dashboard reporting timezone</label>
            @php
                $selectedReporting = old('reporting_timezone', $user->reporting_timezone ?: \App\Support\UserTimezone::REPORTING_PROFILE);
            @endphp
            <select id="profile-reporting-timezone" name="reporting_timezone" class="figma-input">
                <option value="{{ \App\Support\UserTimezone::REPORTING_PROFILE }}" @selected($selectedReporting === \App\Support\UserTimezone::REPORTING_PROFILE)>
                    My profile timezone (visits &amp; calendar)
                </option>
                <option value="{{ \App\Support\UserTimezone::REPORTING_UTC }}" @selected($selectedReporting === \App\Support\UserTimezone::REPORTING_UTC)>
                    UTC
                </option>
                <option value="{{ \App\Support\UserTimezone::REPORTING_GOOGLE }}" @selected($selectedReporting === \App\Support\UserTimezone::REPORTING_GOOGLE)>
                    Google Ads account timezone (best match with Google clicks)
                </option>
            </select>
            <p class="mt-[6px] text-[11px] text-[#8c8787]">
                Google click totals always use your linked Google Ads account timezone. Visit/tag data uses the option above.
            </p>
            @foreach ($errors->get('reporting_timezone') as $message)
                <p class="mt-[6px] text-[12px] text-rose-300">{{ $message }}</p>
            @endforeach
        </div>

        <div class="flex flex-wrap items-center gap-[12px] pt-[4px]">
            <button type="submit" class="rounded-[6px] bg-[#6400B2] px-[18px] py-[9px] text-[13px] font-semibold text-white hover:bg-[#7a1acc]">Save changes</button>
            @if (session('status') === 'profile-updated')
                <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2500)" class="text-[12px] text-emerald-300">Saved.</p>
            @endif
        </div>
    </form>
</article>
