<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\FeatureFlag;
use App\Models\Plan;
use App\Models\SaasProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PlansController extends Controller
{
    public function index(): View
    {
        return view('super-admin.plans.index', [
            'plans' => Plan::with(['product', 'planFeatures'])->latest('id')->paginate(20),
            'products' => SaasProduct::where('is_active', true)->orderBy('name')->get(),
            'featureFlags' => FeatureFlag::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $plan = Plan::create($data + ['slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(5))]);
        $this->syncFeatureLimits($request, $plan);

        return back()->with('status', 'Plan created.');
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $plan->update($this->validated($request));
        $this->syncFeatureLimits($request, $plan);

        return back()->with('status', 'Plan updated.');
    }

    public function destroy(Plan $plan): RedirectResponse
    {
        $plan->delete();

        return back()->with('status', 'Plan archived.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'saas_product_id' => ['nullable', 'exists:saas_products,id'],
            'name' => ['required', 'string', 'max:255'],
            'tier' => ['required', 'in:basic,pro,premium,enterprise,custom'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'billing_interval' => ['required', 'in:monthly,yearly,custom'],
            'trial_days' => ['nullable', 'integer', 'min:0'],
            'is_custom' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return [
            'saas_product_id' => $data['saas_product_id'] ?? null,
            'name' => $data['name'],
            'tier' => $data['tier'],
            'price_cents' => (int) round(((float) ($data['price'] ?? 0)) * 100),
            'currency' => strtoupper($data['currency']),
            'billing_interval' => $data['billing_interval'],
            'trial_days' => (int) ($data['trial_days'] ?? 0),
            'is_custom' => (bool) ($data['is_custom'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? false),
        ];
    }

    private function syncFeatureLimits(Request $request, Plan $plan): void
    {
        $features = collect(explode("\n", (string) $request->input('features')))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->mapWithKeys(function (string $line): array {
                [$key, $value] = array_pad(explode(':', $line, 2), 2, 'enabled');
                return [Str::slug(trim($key), '_') => trim($value)];
            })
            ->all();

        $plan->feature_limits = $features;
        $plan->save();
    }
}
