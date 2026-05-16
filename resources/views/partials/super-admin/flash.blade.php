@if (session('status'))
    <div class="mb-[14px] rounded-[8px] border border-emerald-400/40 bg-emerald-500/15 px-[14px] py-[10px] text-[13px] text-emerald-100">
        {{ session('status') }}
    </div>
@endif
@if ($errors->any())
    <div class="mb-[14px] rounded-[8px] border border-rose-400/40 bg-rose-500/15 p-[14px] text-[13px] text-rose-100">
        <ul class="list-disc space-y-1 pl-5">
            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif
