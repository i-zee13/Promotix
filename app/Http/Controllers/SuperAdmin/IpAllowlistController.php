<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\GlobalIpAllowlistEntry;
use App\Support\GlobalIpAllowlist;
use App\Support\GlobalIpBlocklist;
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
        $listType = (string) $request->query('list', '');
        $search = trim((string) $request->query('search', ''));

        $entries = GlobalIpAllowlistEntry::query()
            ->with('createdBy:id,name,email')
            ->when($kind !== '' && in_array($kind, ['provider', 'cidr', 'asn'], true), fn ($q) => $q->where('kind', $kind))
            ->when($listType !== '' && in_array($listType, ['allow', 'block'], true), function ($q) use ($listType): void {
                if ($listType === 'allow') {
                    $q->where(function ($inner): void {
                        $inner->where('list_type', 'allow')->orWhereNull('list_type');
                    });
                } else {
                    $q->where('list_type', 'block');
                }
            })
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($inner) use ($search): void {
                    $inner->where('value', 'like', "%{$search}%")
                        ->orWhere('label', 'like', "%{$search}%")
                        ->orWhere('provider', 'like', "%{$search}%");
                });
            })
            ->orderByRaw("CASE WHEN kind = 'provider' THEN 0 ELSE 1 END")
            ->orderByRaw("CASE WHEN COALESCE(list_type, 'allow') = 'allow' THEN 0 ELSE 1 END")
            ->orderBy('label')
            ->orderByDesc('id')
            ->paginate(min(50, max(10, $request->integer('per_page', 10))))
            ->withQueryString();

        $allowProviders = GlobalIpAllowlistEntry::query()
            ->where('kind', 'provider')
            ->where('enabled', true)
            ->where(fn ($q) => $q->where('list_type', 'allow')->orWhereNull('list_type'))
            ->count();
        $blockProviders = GlobalIpAllowlistEntry::query()
            ->where('kind', 'provider')
            ->where('enabled', true)
            ->where('list_type', 'block')
            ->count();
        $allowIps = GlobalIpAllowlistEntry::query()
            ->where('kind', 'cidr')
            ->where('enabled', true)
            ->where(fn ($q) => $q->where('list_type', 'allow')->orWhereNull('list_type'))
            ->count();
        $blockIps = GlobalIpAllowlistEntry::query()
            ->where('kind', 'cidr')
            ->where('enabled', true)
            ->where('list_type', 'block')
            ->count();

        return view('super-admin.settings.whitelist', [
            'entries' => $entries,
            'providers' => array_keys(GlobalIpAllowlist::providerCidrs()),
            'stats' => [
                'allow_providers' => $allowProviders,
                'block_providers' => $blockProviders,
                'allow_ips' => $allowIps,
                'block_ips' => $blockIps,
                'disabled' => GlobalIpAllowlistEntry::query()->where('enabled', false)->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(['provider', 'cidr', 'asn'])],
            'list_type' => ['required', Rule::in(['allow', 'block'])],
            'provider' => ['nullable', 'string', 'max:32'],
            'value' => ['required', 'string', 'max:128'],
            'label' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $value = trim($data['value']);
        $kind = $data['kind'];
        $listType = $data['list_type'];

        if ($kind === 'provider') {
            $provider = strtolower(trim((string) ($data['provider'] ?: $value)));
            if (! isset(GlobalIpAllowlist::providerCidrs()[$provider])) {
                throw ValidationException::withMessages(['provider' => 'Unknown provider.']);
            }
            $value = $provider;
            $label = $data['label'] ?: ucfirst($provider);
        } elseif ($kind === 'asn') {
            $asn = GlobalIpAllowlist::normalizeAsn($value);
            if ($asn === null) {
                throw ValidationException::withMessages(['value' => 'Enter a valid ASN number (e.g. 15169 or AS15169).']);
            }
            $value = (string) $asn;
            $provider = 'custom';
            $label = $data['label'] ?: ('AS'.$asn);
        } else {
            $this->assertIpOrCidr($value);
            $provider = 'custom';
            $label = $data['label'] ?: $value;
        }

        GlobalIpAllowlistEntry::query()->updateOrCreate(
            ['kind' => $kind, 'value' => $value],
            [
                'list_type' => $listType,
                'provider' => $provider,
                'label' => $label,
                'notes' => $data['notes'] ?? null,
                'enabled' => true,
                'created_by_id' => $request->user()?->id,
            ]
        );

        GlobalIpAllowlist::flush();
        GlobalIpBlocklist::flushCaches();

        $msg = $listType === 'block'
            ? 'Blocklist entry saved. Matching traffic will be blocked across all domains.'
            : 'Whitelist entry saved. Matching traffic will not be blocked.';

        return back()->with('status', $msg);
    }

    /**
     * Set a provider (or entry) to whitelist, blocklist, or off.
     */
    public function setMode(Request $request, GlobalIpAllowlistEntry $entry): RedirectResponse
    {
        $data = $request->validate([
            'mode' => ['required', Rule::in(['allow', 'block', 'off'])],
        ]);

        $mode = $data['mode'];
        if ($mode === 'off') {
            $entry->update(['enabled' => false]);
        } else {
            $entry->update([
                'enabled' => true,
                'list_type' => $mode,
            ]);
        }

        GlobalIpAllowlist::flush();
        GlobalIpBlocklist::flushCaches();

        $label = $entry->label ?: $entry->value;
        $status = match ($mode) {
            'allow' => "{$label} is now whitelisted — matching IPs will show as allowed.",
            'block' => "{$label} is now blocklisted — matching IPs will be blocked.",
            default => "{$label} is off (neither whitelist nor blocklist).",
        };

        return back()->with('status', $status);
    }

    public function toggle(GlobalIpAllowlistEntry $entry): RedirectResponse
    {
        $entry->update(['enabled' => ! $entry->enabled]);
        GlobalIpAllowlist::flush();
        GlobalIpBlocklist::flushCaches();

        $listLabel = $entry->isBlockList() ? 'blocklist' : 'whitelist';

        return back()->with('status', $entry->enabled
            ? "{$entry->label} is now on the {$listLabel}."
            : "{$entry->label} removed from the active {$listLabel}.");
    }

    public function destroy(GlobalIpAllowlistEntry $entry): RedirectResponse
    {
        abort_if($entry->kind === 'provider', 422, 'Built-in providers can be disabled, not deleted.');

        $label = $entry->label ?: $entry->value;
        $entry->delete();
        GlobalIpAllowlist::flush();
        GlobalIpBlocklist::flushCaches();

        return back()->with('status', "Removed {$label}.");
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
