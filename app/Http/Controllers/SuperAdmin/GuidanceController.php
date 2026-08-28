<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\GuidanceArticle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class GuidanceController extends Controller
{
    public function index(Request $request): View
    {
        $articles = GuidanceArticle::query()
            ->with('updater:id,name,email')
            ->when($request->string('department')->toString(), fn ($q, $d) => $q->where('department', $d))
            ->when($request->string('search')->toString(), function ($q, $term): void {
                $q->where(function ($qq) use ($term): void {
                    $qq->where('title', 'like', "%{$term}%")
                        ->orWhere('keywords', 'like', "%{$term}%")
                        ->orWhere('answer', 'like', "%{$term}%");
                });
            })
            ->when($request->has('published'), function ($q) use ($request): void {
                $q->where('is_published', $request->boolean('published'));
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('super-admin.guidance.index', [
            'articles' => $articles,
            'departments' => ['billing', 'support', 'account', 'verification', 'technical', 'integrations', 'other'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $variants = $this->parseVariants($data['question_variants'] ?? null);
        unset($data['question_variants'], $data['image'], $data['remove_image']);

        GuidanceArticle::query()->create([
            ...$data,
            'question_variants' => $variants,
            'image_path' => $this->storeUploadedImage($request->file('image')),
            'is_published' => $request->boolean('is_published', true),
            'last_updated_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Guidance article created.');
    }

    public function update(Request $request, GuidanceArticle $guidance): RedirectResponse
    {
        $data = $this->validated($request);
        $variants = $this->parseVariants($data['question_variants'] ?? null);
        unset($data['question_variants'], $data['image'], $data['remove_image']);

        $imagePath = $guidance->image_path;

        if ($request->boolean('remove_image')) {
            $this->deleteStoredImage($imagePath);
            $imagePath = null;
        }

        if ($request->hasFile('image')) {
            $this->deleteStoredImage($imagePath);
            $imagePath = $this->storeUploadedImage($request->file('image'));
        }

        $guidance->fill([
            ...$data,
            'question_variants' => $variants,
            'image_path' => $imagePath,
            'is_published' => $request->boolean('is_published'),
            'last_updated_by' => $request->user()->id,
        ])->save();

        return back()->with('status', 'Guidance article updated.');
    }

    public function destroy(GuidanceArticle $guidance): RedirectResponse
    {
        $this->deleteStoredImage($guidance->image_path);
        $guidance->delete();

        return back()->with('status', 'Guidance article deleted.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'answer' => ['required', 'string'],
            'steps' => ['nullable', 'string'],
            'related_page' => ['nullable', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:64'],
            'keywords' => ['nullable', 'string', 'max:500'],
            'question_variants' => ['nullable', 'string'],
            'role_visibility' => ['nullable', 'string', 'max:255'],
            'package_visibility' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:5120'],
            'remove_image' => ['nullable', 'boolean'],
        ]);
    }

    /** @return list<string> */
    private function parseVariants(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n|,/', $raw) ?: [])));
    }

    private function storeUploadedImage(?UploadedFile $file): ?string
    {
        if (! $file) {
            return null;
        }

        return $file->store('guidance-articles', 'public');
    }

    private function deleteStoredImage(?string $path): void
    {
        $path = trim((string) ($path ?? ''));

        if ($path === '') {
            return;
        }

        Storage::disk('public')->delete($path);
    }
}
