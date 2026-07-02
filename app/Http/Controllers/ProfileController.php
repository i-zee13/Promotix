<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
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
        return view('profile.edit', [
            'user' => $request->user(),
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
                'skipped' => true,
            ]);
        }

        UserTimezone::assign($user, $data['timezone'], 'browser');

        return response()->json([
            'timezone' => $user->timezone,
            'label' => UserTimezone::headerLabel($user->fresh()),
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
