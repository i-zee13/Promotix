<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class PasswordCodeController extends Controller
{
    /**
     * Show the 6-digit code entry screen.
     */
    public function show(Request $request): View|RedirectResponse
    {
        $email = $request->session()->get('password.reset.email');

        if (! $email) {
            return redirect()->route('password.request');
        }

        return view('auth.forgot-password-code', ['email' => $email]);
    }

    /**
     * Validate the submitted 6-digit code and redirect to the password reset form.
     */
    public function verify(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
        ]);

        $email = strtolower($data['email']);
        $code = $data['code'];

        $row = DB::table('password_reset_tokens')->where('email', $email)->first();

        if (! $row || ! Hash::check($code, $row->token)) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['code' => 'That code is invalid or has expired.']);
        }

        if (Carbon::parse($row->created_at)->diffInMinutes(now()) > 60) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['code' => 'That code has expired. Please request a new one.']);
        }

        $request->session()->put('password.reset.email', $email);

        // The code itself acts as the token for the existing NewPasswordController,
        // which validates by Hash::check against the stored password_reset_tokens.token hash.
        return redirect()->route('password.reset', [
            'token' => $code,
            'email' => $email,
        ]);
    }
}
