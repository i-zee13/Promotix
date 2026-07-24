<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Services\LoginHistoryLogger;
use App\Support\UserTimezone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        $user = $request->user();

        // New users often stay logged-in from signup/OTP and never hit /login —
        // still show this session in history.
        LoginHistoryLogger::ensureCurrentSession($user, $request, 'session');

        return view('profile.edit', [
            'user' => $user,
            'loginHistories' => $user->loginHistories()->limit(40)->get(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        if (! empty($validated['timezone']) && UserTimezone::isValid($validated['timezone'])) {
            $validated['timezone_source'] = 'manual';
        } else {
            unset($validated['timezone']);
        }

        if (empty($validated['reporting_timezone']) || ! in_array($validated['reporting_timezone'], UserTimezone::REPORTING_MODES, true)) {
            unset($validated['reporting_timezone']);
        }

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    public function syncTimezone(Request $request): JsonResponse
    {
        $data = $request->validate([
            'timezone' => ['required', 'timezone:all'],
        ]);

        $user = $request->user();
        if (($user->timezone_source ?? '') === 'manual') {
            return response()->json([
                'timezone' => $user->timezone,
                'label' => UserTimezone::headerLabel($user),
                'skipped' => true,
            ]);
        }

        $already = ($user->timezone === $data['timezone']) && (($user->timezone_source ?? '') === 'browser');
        if (! $already) {
            UserTimezone::assign($user, $data['timezone'], 'browser');
            $user->refresh();
        }

        return response()->json([
            'timezone' => $user->timezone,
            'label' => UserTimezone::headerLabel($user),
            'skipped' => $already,
        ]);
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
