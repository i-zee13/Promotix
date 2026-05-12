<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset request — generates a 6-digit code,
     * stores its hash in password_reset_tokens, emails it, and redirects to
     * the code-entry screen.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = strtolower($request->input('email'));

        $code = (string) random_int(100000, 999999);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'email' => $email,
                'token' => Hash::make($code),
                'created_at' => now(),
            ]
        );

        $user = User::query()->where('email', $email)->first();

        if ($user) {
            $this->sendCodeEmail($user, $code);
        }

        $request->session()->put('password.reset.email', $email);

        $next = redirect()->route('password.code')
            ->with('status', "We've sent a 6-digit code to {$email}.");

        // For dev / non-mail environments — expose code in the next response
        // so QA can complete the flow without inbox access.
        if (! $this->mailIsConfigured()) {
            $next->with('dev_code', $code);
        }

        return $next;
    }

    private function sendCodeEmail(User $user, string $code): void
    {
        try {
            Mail::raw(
                "Hi {$user->name},\n\nYour Promotix password reset code is: {$code}\n\nThis code expires in 60 minutes. If you did not request a reset, you can safely ignore this email.\n\n— Promotix",
                function ($message) use ($user) {
                    $message->to($user->email)->subject('Your Promotix password reset code');
                }
            );
        } catch (\Throwable $e) {
            Log::warning('Password reset code email failed', [
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function mailIsConfigured(): bool
    {
        $mailer = config('mail.default', 'log');

        return ! in_array($mailer, ['log', 'array', 'null'], true);
    }
}
