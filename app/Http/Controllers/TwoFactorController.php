<?php

namespace App\Http\Controllers;

use App\Models\UserApiKey;
use App\Support\TotpAuthenticator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TwoFactorController extends Controller
{
    public function enable(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user->hasTwoFactorEnabled()) {
            return $this->backToSecurity($request, 'Two-factor authentication is already enabled.', 'two_factor');
        }

        $secret = TotpAuthenticator::generateSecret();
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ])->save();

        $request->session()->put('two_factor.setup_secret', $secret);

        return $this->backToSecurity($request, 'Scan the QR code with your authenticator app, then confirm with a 6-digit code.', 'two_factor');
    }

    public function confirm(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $user = $request->user();
        $secret = (string) ($user->two_factor_secret ?: $request->session()->get('two_factor.setup_secret', ''));
        if ($secret === '') {
            return back()->withErrors(['code' => 'Start setup again.']);
        }

        if (! TotpAuthenticator::verify($secret, (string) $data['code'])) {
            return back()->withErrors(['code' => 'Invalid authenticator code.']);
        }

        $recovery = TotpAuthenticator::generateRecoveryCodes();
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $recovery,
        ])->save();

        $request->session()->forget('two_factor.setup_secret');
        $request->session()->put('auth.two_factor_passed', true);
        $request->session()->flash('two_factor.recovery_codes', $recovery);

        return $this->backToSecurity($request, 'Two-factor authentication enabled. Save your recovery codes now.', 'two_factor');
    }

    public function disable(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ])->save();

        $request->session()->forget(['two_factor.setup_secret', 'auth.two_factor_passed']);

        return $this->backToSecurity($request, 'Two-factor authentication disabled.', 'two_factor');
    }

    public function regenerateRecoveryCodes(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);
        $user = $request->user();
        if (! $user->hasTwoFactorEnabled()) {
            return back()->withErrors(['password' => 'Enable 2FA first.']);
        }

        $recovery = TotpAuthenticator::generateRecoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => $recovery])->save();
        $request->session()->flash('two_factor.recovery_codes', $recovery);

        return $this->backToSecurity($request, 'New recovery codes generated. Save them now.', 'two_factor');
    }

    public function destroyOtherSessions(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        if (Schema::hasTable('sessions')) {
            DB::table('sessions')
                ->where('user_id', $request->user()->id)
                ->where('id', '!=', $request->session()->getId())
                ->delete();
        }

        return $this->backToSecurity($request, 'Logged out of other devices / sessions.', 'sessions');
    }

    public function storeApiKey(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $plain = 'pmx_'.Str::random(40);
        $prefix = substr($plain, 0, 12);

        UserApiKey::query()->create([
            'user_id' => $request->user()->id,
            'name' => $data['name'],
            'token_prefix' => $prefix,
            'token_hash' => hash('sha256', $plain),
        ]);

        $request->session()->flash('api_key.plain', $plain);

        return $this->backToSecurity($request, 'API key created. Copy it now — it will not be shown again.', 'api_keys');
    }

    public function destroyApiKey(Request $request, UserApiKey $apiKey): RedirectResponse
    {
        abort_unless((int) $apiKey->user_id === (int) $request->user()->id, 403);
        $apiKey->delete();

        return $this->backToSecurity($request, 'API key revoked.', 'api_keys');
    }

    private function backToSecurity(Request $request, string $status, string $defaultTab): RedirectResponse
    {
        $tab = trim((string) $request->input('settings_security_tab', $defaultTab));
        if (! in_array($tab, ['two_factor', 'password', 'sessions', 'api_keys', 'login_alerts'], true)) {
            $tab = $defaultTab;
        }

        return back()
            ->with('status', $status)
            ->with('settings.security_tab', $tab);
    }

    /**
     * @return list<array{id: string, ip: ?string, user_agent: ?string, last_activity: ?string, is_current: bool}>
     */
    public static function sessionsFor(Request $request): array
    {
        if (! Schema::hasTable('sessions')) {
            return [];
        }

        $currentId = $request->session()->getId();

        return DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('last_activity')
            ->limit(20)
            ->get()
            ->map(function ($row) use ($currentId) {
                return [
                    'id' => (string) $row->id,
                    'ip' => $row->ip_address ? (string) $row->ip_address : null,
                    'user_agent' => $row->user_agent ? Str::limit((string) $row->user_agent, 80) : null,
                    'last_activity' => isset($row->last_activity)
                        ? date('Y-m-d H:i', (int) $row->last_activity)
                        : null,
                    'is_current' => (string) $row->id === (string) $currentId,
                ];
            })
            ->all();
    }
}
