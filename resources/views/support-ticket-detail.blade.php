@extends('layouts.admin')

@section('title', 'Ticket Detail')
@section('subtitle', '#'.$ticket->id.' · '.$ticket->subject)

@section('content')
<div class="space-y-6"
    x-data="ticketDetail({
        ticketId: {{ $ticket->id }},
        urls: {
            reply: '{{ url('api/admin/tickets/'.$ticket->id.'/reply') }}',
            assign: '{{ url('api/admin/tickets/'.$ticket->id.'/assign') }}',
            escalate: '{{ url('api/admin/tickets/'.$ticket->id.'/escalate') }}',
            close: '{{ url('api/admin/tickets/'.$ticket->id.'/close') }}',
            show: '{{ url('api/admin/tickets/'.$ticket->id) }}',
        },
        csrf: '{{ csrf_token() }}',
        initial: {
            status: '{{ $ticket->status }}',
            priority: '{{ $ticket->priority }}',
            assigned_to_id: {{ $ticket->assigned_to_id ?? 'null' }},
            sla_due_at: '{{ $ticket->sla_due_at?->diffForHumans() ?? 'No SLA' }}',
            messages: @js($ticket->messages->map(fn ($m) => [
                'id' => $m->id,
                'body' => $m->body,
                'name' => $m->user?->name ?? 'System',
                'when' => $m->created_at->diffForHumans(),
                'is_agent' => (bool) $m->is_agent_reply,
            ])->values()),
        },
    })">
    <x-ui.page-header :title="'Ticket #'.$ticket->id" :subtitle="$ticket->subject">
        <x-slot:actions>
            <a href="{{ route('support-system') }}" class="brand-btn-secondary">Back to queue</a>
            <button type="button" class="brand-btn-outline" @click="escalate()" :disabled="loading.escalate">Escalate</button>
            <button type="button" class="brand-btn-danger" @click="close()" :disabled="loading.close">Close</button>
        </x-slot:actions>
    </x-ui.page-header>

    <template x-if="toast.message">
        <div class="rounded-xl2 border px-4 py-3 text-sm"
            :class="toast.type === 'error' ? 'border-rose-500/40 bg-rose-500/10 text-rose-200' : 'border-emerald-500/30 bg-emerald-500/10 text-emerald-200'">
            <span x-text="toast.message"></span>
        </div>
    </template>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <x-ui.card class="xl:col-span-2" title="Conversation" subtitle="Reply composer and ticket timeline">
            <div class="space-y-4">
                <div class="rounded-xl border border-night-700/60 bg-night-800/60 p-4">
                    <p class="text-sm text-night-300">{{ $ticket->requester?->email ?? auth()->user()->email }}</p>
                    <p class="mt-2 text-night-100">{{ $ticket->body ?: 'No initial body provided.' }}</p>
                </div>

                <template x-for="msg in messages" :key="msg.id">
                    <div class="rounded-xl border border-night-700/60 p-4"
                        :class="msg.is_agent ? 'bg-brand-500/10 border-brand-500/30' : 'bg-night-800/60'">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm font-semibold text-night-100" x-text="msg.name"></p>
                            <p class="text-xs text-night-400" x-text="msg.when"></p>
                        </div>
                        <p class="mt-2 whitespace-pre-wrap text-night-200" x-text="msg.body"></p>
                    </div>
                </template>
            </div>

            <form class="mt-6 space-y-3" @submit.prevent="reply()">
                <label class="brand-label">Reply composer</label>
                <textarea x-model="replyBody" rows="4" class="brand-input" placeholder="Write a reply..." required></textarea>
                <div class="flex items-center justify-end gap-2">
                    <button type="submit" class="brand-btn-primary" :disabled="loading.reply || !replyBody.trim()">
                        <span x-show="!loading.reply">Send reply</span>
                        <span x-show="loading.reply">Sending...</span>
                    </button>
                </div>
            </form>
        </x-ui.card>

        <x-ui.card title="Assignment & SLA" subtitle="Agent, priority, and workflow controls">
            <div class="space-y-4">
                <div>
                    <p class="brand-kpi-label">Status</p>
                    <p class="mt-1 text-lg font-semibold text-night-100" x-text="status.charAt(0).toUpperCase() + status.slice(1)"></p>
                </div>
                <div>
                    <p class="brand-kpi-label">Priority</p>
                    <p class="mt-1 text-lg font-semibold text-night-100" x-text="priority.charAt(0).toUpperCase() + priority.slice(1)"></p>
                </div>
                <div>
                    <p class="brand-kpi-label">SLA Due</p>
                    <p class="mt-1 text-lg font-semibold text-night-100" x-text="slaDueAt"></p>
                </div>

                <form class="space-y-3" @submit.prevent="assign()">
                    <label class="brand-label">Assign agent</label>
                    <select x-model="assignedToId" class="brand-select">
                        <option :value="null">Unassigned</option>
                        @foreach ($agents as $agent)
                            <option value="{{ $agent->id }}">{{ $agent->name }} ({{ $agent->email }})</option>
                        @endforeach
                    </select>
                    <button type="submit" class="brand-btn-primary w-full" :disabled="loading.assign">
                        <span x-show="!loading.assign">Save assignment</span>
                        <span x-show="loading.assign">Saving...</span>
                    </button>
                </form>
            </div>
        </x-ui.card>
    </div>
</div>

<script>
function ticketDetail(initial) {
    return {
        ticketId: initial.ticketId,
        urls: initial.urls,
        csrf: initial.csrf,
        status: initial.initial.status,
        priority: initial.initial.priority,
        assignedToId: initial.initial.assigned_to_id,
        slaDueAt: initial.initial.sla_due_at,
        messages: initial.initial.messages,
        replyBody: '',
        loading: { reply: false, assign: false, escalate: false, close: false },
        toast: { message: '', type: 'success' },
        notify(message, type = 'success') {
            this.toast = { message, type };
            setTimeout(() => (this.toast.message = ''), 4000);
        },
        async request(url, method = 'POST', body = null) {
            const opts = {
                method,
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.csrf,
                },
            };
            if (body) {
                opts.headers['Content-Type'] = 'application/json';
                opts.body = JSON.stringify(body);
            }
            const res = await fetch(url, opts);
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.message || 'Request failed');
            return data;
        },
        async reply() {
            if (!this.replyBody.trim()) return;
            this.loading.reply = true;
            try {
                const data = await this.request(this.urls.reply, 'POST', { body: this.replyBody });
                this.messages.push({
                    id: data.reply.id,
                    body: data.reply.body,
                    name: 'You',
                    when: 'just now',
                    is_agent: true,
                });
                this.status = 'waiting';
                this.replyBody = '';
                this.notify('Reply sent.');
            } catch (e) {
                this.notify(e.message, 'error');
            } finally {
                this.loading.reply = false;
            }
        },
        async assign() {
            this.loading.assign = true;
            try {
                const data = await this.request(this.urls.assign, 'PATCH', { assigned_to_id: this.assignedToId || null });
                this.status = data.ticket?.status || this.status;
                this.notify('Assignment updated.');
            } catch (e) {
                this.notify(e.message, 'error');
            } finally {
                this.loading.assign = false;
            }
        },
        async escalate() {
            if (!confirm('Escalate this ticket? Priority will be set to urgent.')) return;
            this.loading.escalate = true;
            try {
                const data = await this.request(this.urls.escalate, 'POST');
                this.status = data.ticket?.status || 'escalated';
                this.priority = data.ticket?.priority || 'urgent';
                this.notify('Ticket escalated.');
            } catch (e) {
                this.notify(e.message, 'error');
            } finally {
                this.loading.escalate = false;
            }
        },
        async close() {
            if (!confirm('Close this ticket?')) return;
            this.loading.close = true;
            try {
                const data = await this.request(this.urls.close, 'POST');
                this.status = data.ticket?.status || 'closed';
                this.notify('Ticket closed.');
            } catch (e) {
                this.notify(e.message, 'error');
            } finally {
                this.loading.close = false;
            }
        },
    };
}
</script>
@endsection
