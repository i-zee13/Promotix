<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\CurrencyRate;
use App\Support\AccountCurrency;
use App\Support\CurrencyConverter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CurrencyRatesController extends Controller
{
    public function index(Request $request): View
    {
        $perPage = in_array((int) $request->input('per_page'), [10, 25, 50, 100], true)
            ? (int) $request->input('per_page')
            : 25;

        $rates = CurrencyRate::query()
            ->when($request->string('search')->toString(), function ($q, string $search): void {
                $q->where(function ($inner) use ($search): void {
                    $inner->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('country_name', 'like', "%{$search}%")
                        ->orWhere('country_code', 'like', "%{$search}%");
                });
            })
            ->when($request->string('status')->toString() === 'active', fn ($q) => $q->where('is_active', true))
            ->when($request->string('status')->toString() === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('code')
            ->paginate($perPage)
            ->withQueryString();

        return view('super-admin.currency-rates.index', [
            'rates' => $rates,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        CurrencyRate::query()->create($data);
        CurrencyConverter::forgetCache();

        return back()->with('success', 'Currency rate added.');
    }

    public function update(Request $request, CurrencyRate $currencyRate): RedirectResponse
    {
        $data = $this->validated($request, $currencyRate->id);
        $currencyRate->update($data);
        CurrencyConverter::forgetCache();

        return back()->with('success', 'Currency rate updated.');
    }

    public function destroy(CurrencyRate $currencyRate): RedirectResponse
    {
        if (strtoupper($currencyRate->code) === 'USD') {
            return back()->with('error', 'USD base rate cannot be deleted.');
        }

        $currencyRate->delete();
        CurrencyConverter::forgetCache();

        return back()->with('success', 'Currency rate removed.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'size:3', 'unique:currency_rates,code'.($ignoreId ? ','.$ignoreId : '')],
            'country_code' => ['nullable', 'string', 'max:2'],
            'country_name' => ['nullable', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:120'],
            'symbol' => ['nullable', 'string', 'max:16'],
            'units_per_usd' => ['required', 'numeric', 'min:0.000001'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['code'] = AccountCurrency::normalize($data['code']);
        $data['country_code'] = strtoupper(trim((string) ($data['country_code'] ?? ''))) ?: null;
        $data['is_active'] = $request->boolean('is_active', true);
        if (empty($data['symbol'])) {
            $data['symbol'] = AccountCurrency::symbol($data['code']);
        }

        return $data;
    }
}
