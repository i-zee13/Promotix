@extends('layouts.super-admin')

@section('title', 'Guidance Knowledge Base')

@section('content')
<x-super-admin.page title="Guidance Knowledge Base" subtitle="Shared answers for dashboard chatbot and website help. Admin is the source of truth.">
    @include('partials.super-admin.flash')

    <div class="grid gap-4 lg:grid-cols-[1.1fr_0.9fr]">
        <section class="rounded-[10px] border border-white/12 bg-[#1a1a1a] p-4">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-[15px] font-semibold text-white">Published articles</h2>
                <form method="GET" class="flex flex-wrap gap-2">
                    <input type="search" name="search" value="{{ request('search') }}" placeholder="Search…" class="figma-sa-settings-input !w-[180px] !py-2">
                    <select name="department" class="figma-sa-settings-input !w-[140px] !py-2">
                        <option value="">All departments</option>
                        @foreach ($departments as $dept)
                            <option value="{{ $dept }}" @selected(request('department') === $dept)>{{ ucfirst($dept) }}</option>
                        @endforeach
                    </select>
                    <button class="figma-sa-btn figma-sa-btn-outline !px-3 !py-2 text-sm">Filter</button>
                </form>
            </div>

            <div class="space-y-3">
                @forelse ($articles as $article)
                    <article class="rounded-[8px] border border-white/10 bg-black/25 p-3">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <h3 class="text-[14px] font-semibold text-white">{{ $article->title }}</h3>
                                <p class="mt-1 text-[11px] text-white/50">
                                    {{ $article->department ? ucfirst($article->department) : 'General' }}
                                    · {{ $article->is_published ? 'Published' : 'Draft' }}
                                    @if ($article->related_page)
                                        · <span class="text-[#FFB380]">{{ $article->related_page }}</span>
                                    @endif
                                </p>
                            </div>
                            <form method="POST" action="{{ route('super-admin.guidance.destroy', $article) }}" onsubmit="return confirm('Delete this article?')">
                                @csrf
                                @method('DELETE')
                                <button class="text-[11px] text-rose-300 hover:text-rose-200">Delete</button>
                            </form>
                        </div>
                        <p class="mt-2 line-clamp-3 text-[12px] text-white/75">{{ $article->answer }}</p>
                        @if ($article->imageUrl())
                            <img
                                src="{{ $article->imageUrl() }}"
                                alt=""
                                class="mt-2 max-h-[120px] max-w-full rounded-[6px] border border-white/10 object-contain"
                                loading="lazy"
                            >
                        @endif
                        <details class="mt-2">
                            <summary class="cursor-pointer text-[11px] text-[#FFB380]">Edit</summary>
                            <form method="POST" action="{{ route('super-admin.guidance.update', $article) }}" enctype="multipart/form-data" class="mt-2 grid gap-2">
                                @csrf
                                @method('PUT')
                                <input name="title" value="{{ $article->title }}" class="figma-sa-settings-input" required>
                                <textarea name="answer" rows="4" class="figma-sa-settings-input" required>{{ $article->answer }}</textarea>
                                <textarea name="steps" rows="3" class="figma-sa-settings-input" placeholder="Steps">{{ $article->steps }}</textarea>
                                <div class="grid gap-1">
                                    <label class="text-[11px] text-white/60">Article image (optional)</label>
                                    @if ($article->imageUrl())
                                        <img src="{{ $article->imageUrl() }}" alt="" class="max-h-[100px] max-w-full rounded-[6px] border border-white/10 object-contain">
                                        <label class="flex items-center gap-2 text-[12px] text-white/70">
                                            <input type="checkbox" name="remove_image" value="1"> Remove current image
                                        </label>
                                    @endif
                                    <input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp" class="figma-sa-settings-input !py-2 file:mr-3 file:rounded file:border-0 file:bg-white/10 file:px-3 file:py-1 file:text-[11px] file:text-white">
                                    <p class="text-[10px] text-white/45">JPG, PNG, GIF or WebP · max 5 MB</p>
                                </div>
                                <input name="related_page" value="{{ $article->related_page }}" class="figma-sa-settings-input" placeholder="/admin/...">
                                <input name="keywords" value="{{ $article->keywords }}" class="figma-sa-settings-input" placeholder="keywords">
                                <textarea name="question_variants" rows="2" class="figma-sa-settings-input" placeholder="question variants, one per line">{{ implode("\n", $article->question_variants ?? []) }}</textarea>
                                <select name="department" class="figma-sa-settings-input">
                                    <option value="">General</option>
                                    @foreach ($departments as $dept)
                                        <option value="{{ $dept }}" @selected($article->department === $dept)>{{ ucfirst($dept) }}</option>
                                    @endforeach
                                </select>
                                <label class="flex items-center gap-2 text-[12px] text-white/70">
                                    <input type="checkbox" name="is_published" value="1" @checked($article->is_published)> Published
                                </label>
                                <button class="figma-sa-btn figma-sa-btn-primary !px-3 !py-2 text-sm">Save article</button>
                            </form>
                        </details>
                    </article>
                @empty
                    <p class="text-[13px] text-white/55">No guidance articles yet. Create one on the right.</p>
                @endforelse
            </div>

            <div class="mt-4">{{ $articles->links() }}</div>
        </section>

        <section class="rounded-[10px] border border-white/12 bg-[#1a1a1a] p-4">
            <h2 class="mb-3 text-[15px] font-semibold text-white">Add guidance article</h2>
            <form method="POST" action="{{ route('super-admin.guidance.store') }}" enctype="multipart/form-data" class="grid gap-2">
                @csrf
                <input name="title" class="figma-sa-settings-input" placeholder="Title" required>
                <textarea name="answer" rows="5" class="figma-sa-settings-input" placeholder="Answer" required></textarea>
                <textarea name="steps" rows="3" class="figma-sa-settings-input" placeholder="Steps (optional)"></textarea>
                <div class="grid gap-1">
                    <label class="text-[11px] text-white/60">Article image (optional)</label>
                    <input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp" class="figma-sa-settings-input !py-2 file:mr-3 file:rounded file:border-0 file:bg-white/10 file:px-3 file:py-1 file:text-[11px] file:text-white">
                    <p class="text-[10px] text-white/45">JPG, PNG, GIF or WebP · max 5 MB. Shown in guidance chatbot answers.</p>
                </div>
                <input name="related_page" class="figma-sa-settings-input" placeholder="Related page path e.g. /admin/domains">
                <input name="keywords" class="figma-sa-settings-input" placeholder="keywords,comma,separated">
                <textarea name="question_variants" rows="3" class="figma-sa-settings-input" placeholder="Question variants (one per line)"></textarea>
                <select name="department" class="figma-sa-settings-input">
                    <option value="">General</option>
                    @foreach ($departments as $dept)
                        <option value="{{ $dept }}">{{ ucfirst($dept) }}</option>
                    @endforeach
                </select>
                <label class="flex items-center gap-2 text-[12px] text-white/70">
                    <input type="checkbox" name="is_published" value="1" checked> Published
                </label>
                <button class="figma-sa-btn figma-sa-btn-primary">Create article</button>
            </form>
            <p class="mt-4 text-[11px] leading-relaxed text-white/50">
                Dashboard + website chatbot both read published articles through Guidance Service.
                Low-confidence answers offer a ticket form. 3-minute chat inactivity closes the session.
            </p>
        </section>
    </div>
</x-super-admin.page>
@endsection
