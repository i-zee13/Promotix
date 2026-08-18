<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\GlobalIpAllowlistEntry;
use App\Support\GlobalIpAllowlist;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class IpAllowlistController extends Controller
{
    public function index(Request $request): View
    {
        $kind = (string) $request->query('kind', '');
        $search = trim((string) $request->query('search', ''));

        $entries = GlobalIpAllowlistEntry::query()
            ->with('createdBy:id,name,email')
            ->when($kind !== '' && in_array($kind, ['provider', 'cidr'], true), fn ($q) => $q->where('kind', $kind))
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($inner) use ($search): void {
                    $inner->where('value', 'like', "%{$search}%")
                        ->orWhere('label', 'like', "%{$search}%")
                        ->orWhere('provider', 'like', "%{$search}%");
                });
            })
            ->orderByRaw("CASE WHEN kind = 'provider' THEN 0 ELSE 1 END")
            ->orderBy('label')
            ->orderByDesc('id')
            ->paginate(min(50, max(10, $request->integer('per_page', 10))))
            ->withQueryString();

        return view('super-admin.settings.whitelist', [
            'entries' => $entries,
            'providers' => array_keys(GlobalIpAllowlist::providerCidrs()),
            'stats' => [
                'providers' => GlobalIpAllowlistEntry::query()->where('kind', 'provider')->where('enabled', true)->count(),
                'ips' => GlobalIpAllowlistEntry::query()->where('kind', 'cidr')->where('enabled', true)->count(),
                'disabled' => GlobalIpAllowlistEntry::query()->where('enabled', false)->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(['provider', 'cidr'])],
            'provider' => ['nullable', 'string', 'max:32'],
            'value' => ['required', 'string', 'max:128'],
            'label' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $value = trim($data['value']);
        $kind = $data['kind'];

        if ($kind === 'provider') {
            $provider = strtolower(trim((string) ($data['provider'] ?: $value)));
            if (! isset(GlobalIpAllowlist::providerCidrs()[$provider])) {
                throw ValidationException::withMessages(['provider' => 'Unknown provider.']);
            }
            $value = $provider;
            $label = $data['label'] ?: ucfirst($provider);
        } else {
            $this->assertIpOrCidr($value);
            $provider = 'custom';
            $label = $data['label'] ?: $value;
        }

        GlobalIpAllowlistEntry::query()->updateOrCreate(
            ['kind' => $kind, 'value' => $value],
            [
                'provider' => $provider,
                'label' => $label,
                'notes' => $data['notes'] ?? null,
                'enabled' => true,
                'created_by_id' => $request->user()?->id,
            ]
        );

        GlobalIpAllowlist::flush();

        return back()->with('status', 'Whitelist entry saved. Matching traffic will not be blocked.');
    }

    public function toggle(GlobalIpAllowlistEntry $entry): RedirectResponse
    {
        $entry->update(['enabled' => ! $entry->enabled]);
        GlobalIpAllowlist::flush();

        return back()->with('status', $entry->enabled
            ? "{$entry->label} is now whitelisted."
            : "{$entry->label} removed from the active whitelist.");
    }

    public function destroy(GlobalIpAllowlistEntry $entry): RedirectResponse
    {
        abort_if($entry->kind === 'provider', 422, 'Built-in providers can be disabled, not deleted.');

        $label = $entry->label ?: $entry->value;
        $entry->delete();
        GlobalIpAllowlist::flush();

        return back()->with('status', "Removed {$label} from the whitelist.");
    }

    private function assertIpOrCidr(string $value): void
    {
        if (str_contains($value, '/')) {
            [$ip, $mask] = explode('/', $value, 2);
            $mask = (int) $mask;
            $isV4 = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
            $isV6 = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
            if (! $isV4 && ! $isV6) {
                throw ValidationException::withMessages(['value' => 'Enter a valid IP or CIDR (e.g. 66.249.88.8 or 66.249.0.0/16).']);
            }
            if ($mask < 0 || $mask > ($isV6 ? 128 : 32)) {
                throw ValidationException::withMessages(['value' => 'CIDR prefix is out of range.']);
            }

            return;
        }

        if (str_contains($value, '*')) {
            if (preg_match('/^[0-9a-fA-F:.]+\\*$/', $value) !== 1) {
                throw ValidationException::withMessages(['value' => 'Wildcard IPs must look like 66.249.88.*']);
            }

            return;
        }

        if (filter_var($value, FILTER_VALIDATE_IP) === false) {
            throw ValidationException::withMessages(['value' => 'Enter a valid IP or CIDR.']);
        }
    }
}
