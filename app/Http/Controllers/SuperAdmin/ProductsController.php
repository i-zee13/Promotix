<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\SaasProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProductsController extends Controller
{
    /** @var list<string> */
    public const PRODUCT_TYPES = ['tracking', 'automation', 'analytics'];

    public function index(Request $request): View
    {
        $perPage = in_array((int) $request->input('per_page'), [10, 25, 50], true)
            ? (int) $request->input('per_page')
            : 10;

        $products = SaasProduct::query()
            ->with(['plans:id,saas_product_id,name,slug'])
            ->withCount('plans')
            ->when($request->string('search')->toString(), function ($q, string $search): void {
                $q->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->when($request->string('status')->toString() === 'active', fn ($q) => $q->where('is_active', true))
            ->when($request->string('status')->toString() === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($request->string('type')->toString(), function ($q, string $type): void {
                if (in_array($type, self::PRODUCT_TYPES, true)) {
                    $q->where('settings->type', $type);
                }
            })
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('super-admin.products.index', [
            'products' => $products,
            'perPage' => $perPage,
            'productTypes' => self::PRODUCT_TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['nullable', 'in:'.implode(',', self::PRODUCT_TYPES)],
            'is_active' => ['nullable', 'boolean'],
        ]);

        SaasProduct::create([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(5)),
            'description' => $data['description'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'settings' => ['type' => $data['type'] ?? 'tracking'],
        ]);

        return back()->with('status', 'Product created.');
    }

    public function update(Request $request, SaasProduct $product): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['nullable', 'in:'.implode(',', self::PRODUCT_TYPES)],
            'is_active' => ['nullable', 'boolean'],
            'usage_limits' => ['nullable', 'string'],
        ]);

        $settings = $product->settings ?? [];
        $settings['type'] = $data['type'] ?? ($settings['type'] ?? 'tracking');
        if (array_key_exists('usage_limits', $data)) {
            $settings['usage_limits'] = $data['usage_limits'];
        }

        $product->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? false),
            'settings' => $settings,
        ]);

        return back()->with('status', 'Product updated.');
    }

    public function duplicate(SaasProduct $product): RedirectResponse
    {
        $copy = $product->replicate(['slug']);
        $copy->name = $product->name.' (Copy)';
        $copy->slug = Str::slug($copy->name).'-'.Str::lower(Str::random(5));
        $copy->save();

        return back()->with('status', "Duplicated “{$product->name}”.");
    }

    public function destroy(SaasProduct $product): RedirectResponse
    {
        $product->delete();

        return back()->with('status', 'Product archived.');
    }
}
