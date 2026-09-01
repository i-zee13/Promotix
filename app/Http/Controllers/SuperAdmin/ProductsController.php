<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\SaasProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
            'icon' => ['nullable', 'file', 'mimes:png,svg', 'max:2048'],
        ]);

        SaasProduct::create([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(5)),
            'description' => $data['description'] ?? null,
            'icon_path' => $this->storeProductIcon($request->file('icon')),
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
            'icon' => ['nullable', 'file', 'mimes:png,svg', 'max:2048'],
            'remove_icon' => ['nullable', 'boolean'],
        ]);

        $settings = $product->settings ?? [];
        $settings['type'] = $data['type'] ?? ($settings['type'] ?? 'tracking');
        if (array_key_exists('usage_limits', $data)) {
            $settings['usage_limits'] = $data['usage_limits'];
        }
        // Preserve portal gate flag — only changeable by seed/migration, not wiped on edit.
        if ($product->gatesCustomerPortal()) {
            $settings['gates_customer_portal'] = true;
        }

        $iconPath = $product->icon_path;

        if ($request->boolean('remove_icon')) {
            $this->deleteProductIcon($iconPath);
            $iconPath = null;
        }

        if ($request->hasFile('icon')) {
            $this->deleteProductIcon($iconPath);
            $iconPath = $this->storeProductIcon($request->file('icon'));
        }

        $product->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'icon_path' => $iconPath,
            'is_active' => (bool) ($data['is_active'] ?? false),
            'settings' => $settings,
        ]);

        SaasProduct::flushPortalCache();

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
        $this->deleteProductIcon($product->icon_path);
        $product->delete();

        return back()->with('status', 'Product archived.');
    }

    private function storeProductIcon(?UploadedFile $file): ?string
    {
        if (! $file) {
            return null;
        }

        return $file->store('product-icons', 'public');
    }

    private function deleteProductIcon(?string $path): void
    {
        $path = trim((string) ($path ?? ''));

        if ($path === '') {
            return;
        }

        Storage::disk('public')->delete($path);
    }
}
