{{-- Live agent chat panel (trigger via @click or $dispatch('open-live-agent')) --}}
@php
    $copilotTickets = \App\Support\SupportTicketInbox::copilotChips(auth()->user());
@endphp
<style>
.copilot-tickets {
    display: flex;
    align-items: center;
    gap: 6px;
    overflow-x: auto;
    padding: 8px 12px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    background: #141414;
    scrollbar-width: thin;
}
.copilot-ticket-chip {
    flex-shrink: 0;
    max-width: 118px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    border-radius: 999px;
    border: 1px solid rgba(255, 255, 255, 0.16);
    background: #1f1f1f;
    color: rgba(255, 255, 255, 0.92);
    font-size: 10px;
    font-weight: 600;
    line-height: 1;
    padding: 7px 10px;
    cursor: pointer;
}
.copilot-ticket-chip.is-on,
.copilot-ticket-chip--new {
    background: #FF6600;
    border-color: transparent;
    color: #fff;
}
.copilot-ticket-chip--new { max-width: none; }

/* Keep orange header + white status in light mode (global `header` / `.text-white` flips break this) */
html.light-mode .copilot-panel > header.copilot-header,
.light-mode .copilot-panel > header.copilot-header {
    background: #FF6600 !important;
    background-color: #FF6600 !important;
    color: #ffffff !important;
    border-color: rgba(255, 255, 255, 0.12) !important;
}
html.light-mode .copilot-panel > header.copilot-header .copilot-title,
html.light-mode .copilot-panel > header.copilot-header .copilot-status,
html.light-mode .copilot-panel > header.copilot-header .copilot-close,
.light-mode .copilot-panel > header.copilot-header .copilot-title,
.light-mode .copilot-panel > header.copilot-header .copilot-status,
.light-mode .copilot-panel > header.copilot-header .copilot-close,
html.light-mode .copilot-panel > header.copilot-header [class*='text-white'],
.light-mode .copilot-panel > header.copilot-header [class*='text-white'] {
    color: #ffffff !important;
}
.copilot-status {
    color: rgba(255, 255, 255, 0.92);
    font-size: 10px;
    line-height: 1.3;
    margin-top: 2px;
}
</style>
<div
    x-data="liveAgentChat({
        askUrl: @js(url('/api/admin/guidance/ask')),
        ticketUrl: @js(url('/api/admin/guidance/ticket')),
        ticketsUrl: @js(url('/api/admin/guidance/tickets')),
        csrf: @js(csrf_token()),
        tickets: @js($copilotTickets),
    })"
    x-cloak
    @open-live-agent.window="openPanel()"
    class="contents"
>
    <div
        x-show="open"
        x-transition.opacity
        class="fixed inset-0 z-[300] bg-black/55"
        @click="closePanel()"
        aria-hidden="true"
    ></div>

    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="translate-y-4 opacity-0"
        x-transition:enter-end="translate-y-0 opacity-100"
        class="copilot-panel fixed bottom-[20px] right-[20px] z-[310] flex h-[min(560px,88vh)] w-[min(380px,calc(100vw-24px))] flex-col overflow-hidden rounded-[12px] border border-[#FF6600]/45 bg-[#0F0F10] shadow-[0_16px_48px_rgba(0,0,0,.55)]"
        role="dialog"
        aria-label="Live agent chat"
        @mousemove="bumpActivity()"
        @keydown="bumpActivity()"
    >
        <header class="copilot-header flex items-center justify-between border-b border-white/10 bg-[#FF6600] px-[14px] py-[12px]">
            <div>
                <p class="copilot-title text-[13px] font-semibold text-white">{{ \App\Support\PortalBrand::name() }} Copilot</p>
                <p class="copilot-status" x-text="mode === 'ticket' ? (activeTicketSubject || 'Support ticket') : (typing ? 'Typing…' : (agentOnline ? 'Online' : 'Connecting…'))"></p>
            </div>
            <button type="button" @click="closePanel()" class="copilot-close rounded p-1 text-white/80 hover:bg-white/10" aria-label="Close chat">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </header>

        <div class="copilot-tickets" x-show="tickets.length > 0">
            <template x-for="ticket in tickets" :key="ticket.id">
                <button
                    type="button"
                    class="copilot-ticket-chip"
                    :class="activeTicketId === ticket.id ? 'is-on' : ''"
                    :title="ticket.subject"
                    @click.stop.prevent="openTicket(ticket)"
                    x-text="ticket.title"
                ></button>
            </template>
            <button type="button" class="copilot-ticket-chip copilot-ticket-chip--new" @click.stop.prevent="startNewChat()">New chat</button>
        </div>

        <div class="min-h-0 flex-1 space-y-[10px] overflow-y-auto px-[14px] py-[12px] text-[12px]" id="live-agent-messages">
            <div class="space-y-[10px]" x-show="mode === 'copilot'">
                <template x-for="(msg, idx) in messages" :key="idx">
                    <div :class="msg.from === 'user' ? 'ml-[24px] text-right' : 'mr-[24px]'">
                        <p
                            x-show="msg.text"
                            class="inline-block max-w-full rounded-[8px] px-[10px] py-[8px] text-left whitespace-pre-wrap"
                            :class="msg.from === 'user' ? 'bg-[#FF6600] text-white' : 'bg-[#1a1a1a] text-white/90 border border-white/10'"
                            x-text="msg.text"
                        ></p>
                        <img
                            x-show="msg.from === 'agent' && msg.image_url"
                            :src="msg.image_url"
                            alt=""
                            class="mt-2 max-h-[180px] max-w-full rounded-[8px] border border-white/10 object-contain"
                            loading="lazy"
                        >
                        <template x-if="msg.from === 'agent' && msg.related_page">
                            <p class="mt-1 text-[10px] text-[#FFB380]">
                                Related: <a :href="msg.related_page" class="underline" x-text="msg.related_page"></a>
                            </p>
                        </template>
                    </div>
                </template>
                <template x-if="offerTicket">
                    <div class="rounded-[8px] border border-amber-400/30 bg-amber-500/10 p-3 text-[11px] text-amber-50">
                        <p class="mb-1 font-medium">No match in the knowledge bank — open a ticket?</p>
                        <p class="mb-2 text-[10px] text-amber-100/70">Include the page/domain, what you expected, and what happened. “Hi” or short greetings are not accepted.</p>
                        <input type="text" x-model="ticketSubject" placeholder="Subject (e.g. Tag not detecting on example.com)" class="mb-2 h-[32px] w-full rounded border border-white/15 bg-[#0d0d0d] px-2 text-[12px] text-white">
                        <textarea x-model="ticketBody" rows="4" placeholder="Describe the issue in detail (what you tried, errors, screenshots description…)" class="mb-2 w-full rounded border border-white/15 bg-[#0d0d0d] px-2 py-1 text-[12px] text-white"></textarea>
                        <p x-show="ticketError" x-text="ticketError" class="mb-2 text-[10px] text-rose-300"></p>
                        <button type="button" @click="createTicket()" class="rounded bg-[#FF6600] px-3 py-1.5 text-[11px] font-semibold text-white disabled:opacity-50" :disabled="ticketBusy">
                            <span x-text="ticketBusy ? 'Creating…' : 'Create support ticket'"></span>
                        </button>
                    </div>
                </template>
            </div>

            <div class="space-y-[10px]" x-show="mode === 'ticket'">
                <p class="text-[11px] font-medium text-white/70" x-text="activeTicketSubject"></p>
                <template x-for="msg in ticketMessages" :key="msg.id">
                    <div :class="msg.is_agent ? 'mr-[24px]' : 'ml-[24px] text-right'">
                        <p class="mb-1 text-[10px] text-white/40" x-text="(msg.is_agent ? (msg.name || 'Support') : 'You') + (msg.when ? (' · ' + msg.when) : '')"></p>
                        <p
                            class="inline-block max-w-full rounded-[8px] px-[10px] py-[8px] text-left whitespace-pre-wrap"
                            :class="msg.is_agent ? 'bg-[#1a1a1a] text-white/90 border border-white/10' : 'bg-[#FF6600] text-white'"
                            x-text="msg.body"
                        ></p>
                    </div>
                </template>
                <p x-show="!ticketMessages.length" class="text-center text-[11px] text-white/45">No messages on this ticket yet.</p>
            </div>
        </div>

        <form x-show="mode === 'copilot'" @submit.prevent="send()" class="border-t border-white/10 p-[12px]">
            <div class="flex gap-[8px]">
                <input
                    x-model="draft"
                    type="text"
                    placeholder="Ask about tracking, invalid clicks, Google Ads…"
                    class="h-[36px] flex-1 rounded-[6px] border border-white/15 bg-[#0d0d0d] px-[10px] text-[12px] text-white placeholder:text-white/40 focus:border-[#FF6600] focus:ring-0"
                    :disabled="typing"
                >
                <button type="submit" class="shrink-0 rounded-[6px] bg-[#FF6600] px-[14px] text-[12px] font-semibold text-white hover:bg-[#ff7a1a]" :disabled="typing">Send</button>
            </div>
            <p class="mt-2 text-[10px] text-white/40">Idle chats close after 3 minutes.</p>
        </form>

        <form x-show="mode === 'ticket'" @submit.prevent="replyTicket()" class="border-t border-white/10 p-[12px]">
            <div class="flex gap-[8px]" x-show="ticketCanReply">
                <input
                    x-model="ticketDraft"
                    type="text"
                    placeholder="Reply to support…"
                    class="h-[36px] flex-1 rounded-[6px] border border-white/15 bg-[#0d0d0d] px-[10px] text-[12px] text-white placeholder:text-white/40 focus:border-[#FF6600] focus:ring-0"
                    :disabled="ticketBusy"
                >
                <button type="submit" class="shrink-0 rounded-[6px] bg-[#FF6600] px-[14px] text-[12px] font-semibold text-white hover:bg-[#ff7a1a]" :disabled="ticketBusy">Send</button>
            </div>
            <p x-show="!ticketCanReply" class="text-[11px] text-white/45">This ticket is closed. Start a new chat if you still need help.</p>
        </form>
    </div>
</div>

<script>
function liveAgentChat(config) {
    return {
        open: false,
        draft: '',
        agentOnline: true,
        typing: false,
        sessionId: null,
        offerTicket: false,
        ticketSubject: '',
        ticketBody: '',
        ticketError: '',
        ticketDepartment: 'support',
        ticketBusy: false,
        lastActivityAt: Date.now(),
        idleTimer: null,
        mode: 'copilot',
        tickets: Array.isArray(config.tickets) ? config.tickets : [],
        activeTicketId: null,
        activeTicketSubject: '',
        ticketMessages: [],
        ticketCanReply: false,
        ticketDraft: '',
        welcome: 'Hi — I’m the Clickronix Copilot. Ask about tracking, Google Ads connection, invalid clicks, Advanced View, analytics, domains, or billing. Answers come from the product knowledge bank (no OpenAI key). Say “agent” if you want a specialist.',
        messages: [],
        init() {
            this.messages = [{ from: 'agent', text: this.welcome }];
            const params = new URLSearchParams(window.location.search);
            if (params.get('open_copilot') === '1') {
                this.$nextTick(() => {
                    this.openPanel();
                    const id = Number(params.get('ticket') || 0);
                    if (id > 0) this.openTicket(id);
                });
            }
        },
        openPanel() {
            this.open = true;
            this.bumpActivity();
            this.startIdleWatch();
            this.loadTickets();
            this.$nextTick(() => this.scrollMessages());
        },
        closePanel() {
            this.open = false;
            this.stopIdleWatch();
        },
        bumpActivity() {
            this.lastActivityAt = Date.now();
        },
        startIdleWatch() {
            this.stopIdleWatch();
            this.idleTimer = setInterval(() => {
                if (!this.open) return;
                if (Date.now() - this.lastActivityAt >= 3 * 60 * 1000) {
                    this.messages.push({ from: 'agent', text: 'Chat closed due to 3 minutes of inactivity. Open again anytime.' });
                    this.closePanel();
                }
            }, 15000);
        },
        stopIdleWatch() {
            if (this.idleTimer) {
                clearInterval(this.idleTimer);
                this.idleTimer = null;
            }
        },
        scrollMessages() {
            const el = document.getElementById('live-agent-messages');
            if (el) el.scrollTop = el.scrollHeight;
        },
        headers() {
            return {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': config.csrf,
            };
        },
        async loadTickets() {
            if (!config.ticketsUrl) return;
            try {
                const res = await fetch(config.ticketsUrl, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });
                const data = await res.json();
                if (res.ok && Array.isArray(data.tickets) && data.tickets.length) {
                    this.tickets = data.tickets;
                }
            } catch (e) {}
        },
        startNewChat() {
            this.mode = 'copilot';
            this.activeTicketId = null;
            this.activeTicketSubject = '';
            this.ticketMessages = [];
            this.ticketDraft = '';
            this.offerTicket = false;
            this.ticketError = '';
            this.messages = [{ from: 'agent', text: this.welcome }];
            this.bumpActivity();
            this.$nextTick(() => this.scrollMessages());
        },
        showTicket(ticket) {
            this.mode = 'ticket';
            this.activeTicketId = ticket.id;
            this.activeTicketSubject = ticket.subject || ticket.title || 'Support ticket';
            this.ticketMessages = Array.isArray(ticket.messages) ? ticket.messages : [];
            this.ticketCanReply = Boolean(ticket.can_reply);
            this.offerTicket = false;
            this.ticketDraft = '';
        },
        openTicket(ticketOrId) {
            this.bumpActivity();
            const chip = (ticketOrId && typeof ticketOrId === 'object')
                ? ticketOrId
                : this.tickets.find((t) => Number(t.id) === Number(ticketOrId));
            if (chip) {
                this.showTicket(chip);
                this.$nextTick(() => this.scrollMessages());
            }
            const id = Number(chip?.id ?? ticketOrId);
            if (id > 0) this.refreshTicket(id);
        },
        async refreshTicket(id) {
            this.ticketBusy = true;
            try {
                const res = await fetch(config.ticketsUrl + '/' + id, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });
                const data = await res.json();
                if (res.ok && data.ok && data.ticket) {
                    this.showTicket(data.ticket);
                }
            } catch (e) {}
            this.ticketBusy = false;
            this.$nextTick(() => this.scrollMessages());
        },
        async replyTicket() {
            const text = String(this.ticketDraft || '').trim();
            if (!text || !this.activeTicketId || this.ticketBusy || !this.ticketCanReply) return;
            this.ticketBusy = true;
            this.bumpActivity();
            try {
                const res = await fetch(config.ticketsUrl + '/' + this.activeTicketId + '/reply', {
                    method: 'POST',
                    headers: this.headers(),
                    body: JSON.stringify({ body: text }),
                });
                const data = await res.json();
                if (res.ok && data.ok) {
                    this.ticketDraft = '';
                    this.ticketMessages = data.ticket.messages || [];
                    this.ticketCanReply = Boolean(data.ticket.can_reply);
                    this.loadTickets();
                }
            } catch (e) {}
            this.ticketBusy = false;
            this.$nextTick(() => this.scrollMessages());
        },
        async send() {
            if (this.mode !== 'copilot') return;
            const text = String(this.draft || '').trim();
            if (!text || this.typing) return;
            this.messages.push({ from: 'user', text });
            this.draft = '';
            this.offerTicket = false;
            this.bumpActivity();
            this.typing = true;
            this.$nextTick(() => this.scrollMessages());

            let payload = null;
            let errMsg = null;
            try {
                const res = await fetch(config.askUrl, {
                    method: 'POST',
                    headers: this.headers(),
                    body: JSON.stringify({
                        message: text,
                        session_id: this.sessionId,
                        channel: 'dashboard',
                    }),
                });
                payload = await res.json();
                if (!res.ok || !payload.ok) {
                    errMsg = payload.message || 'Could not reach guidance service.';
                }
            } catch (e) {
                errMsg = 'Network error talking to guidance.';
            }

            const delay = Math.max(500, Number(payload?.ux_delay_ms || 500));
            await new Promise((r) => setTimeout(r, delay));

            this.typing = false;
            if (errMsg) {
                this.messages.push({ from: 'agent', text: errMsg });
                // Service errors can escalate — but never prefill with a greeting.
                this.openTicketOffer(text);
            } else {
                if (payload.session_id) this.sessionId = payload.session_id;
                this.messages.push({
                    from: 'agent',
                    text: payload.answer || 'No answer returned.',
                    related_page: payload.related_page || null,
                    image_url: payload.image_url || null,
                });
                this.ticketDepartment = payload.department || 'support';
                const source = String(payload.source || '');
                const shouldOffer = Boolean(payload.offer_ticket)
                    && source !== 'clarifying'
                    && source !== 'knowledge_bank'
                    && source !== 'guidance_article';
                if (shouldOffer) {
                    this.openTicketOffer(text);
                }
            }
            this.bumpActivity();
            this.$nextTick(() => this.scrollMessages());
        },
        openTicketOffer(userMessage) {
            this.offerTicket = true;
            this.ticketError = '';
            const msg = String(userMessage || '').trim();
            // Only prefill when the message already looks like a real issue.
            if (this.isSubstantialTicketText(msg)) {
                this.ticketSubject = msg.slice(0, 120);
                this.ticketBody = msg;
            } else {
                this.ticketSubject = '';
                this.ticketBody = '';
            }
        },
        isSubstantialTicketText(text) {
            const t = String(text || '').trim().toLowerCase();
            if (t.length < 40) return false;
            const trivial = ['hi', 'hii', 'hello', 'hey', 'ok', 'test', 'help', 'thanks', 'thank you'];
            const flat = t.replace(/[!?.]+$/g, '');
            if (trivial.includes(flat)) return false;
            const words = t.split(/\W+/).filter((w) => w.length > 2);
            return words.length >= 6;
        },
        ticketDraftError(subject, body) {
            const s = String(subject || '').trim();
            const b = String(body || '').trim();
            if (s.length < 8) return 'Subject needs at least 8 characters.';
            if (b.length < 40) return 'Description needs at least 40 characters with real detail.';
            const trivial = ['hi', 'hii', 'hello', 'hey', 'ok', 'test', 'help', 'thanks'];
            const flatS = s.toLowerCase().replace(/[!?.]+$/g, '');
            const flatB = b.toLowerCase().replace(/[!?.]+$/g, '');
            if (trivial.includes(flatS) || trivial.includes(flatB)) {
                return 'A greeting is not enough — describe the product issue.';
            }
            if (flatS === flatB && b.split(/\s+/).length < 12) {
                return 'Expand the description beyond the subject line.';
            }
            return '';
        },
        async createTicket() {
            if (this.ticketBusy) return;
            const subject = String(this.ticketSubject || '').trim();
            const body = String(this.ticketBody || '').trim();
            const err = this.ticketDraftError(subject, body);
            if (err) {
                this.ticketError = err;
                return;
            }
            this.ticketError = '';
            this.ticketBusy = true;
            try {
                const res = await fetch(config.ticketUrl, {
                    method: 'POST',
                    headers: this.headers(),
                    body: JSON.stringify({
                        session_id: this.sessionId,
                        subject,
                        body,
                        department: this.ticketDepartment || 'support',
                    }),
                });
                const data = await res.json();
                if (res.ok && data.ok) {
                    this.messages.push({
                        from: 'agent',
                        text: 'Ticket ' + (data.ticket_number || data.ticket_id) + ' created. Use the chips above to open it — this is a support thread, not Copilot.',
                    });
                    this.offerTicket = false;
                    this.loadTickets();
                    if (data.ticket_id) this.openTicket(data.ticket_id);
                } else {
                    this.ticketError = data.message || 'Could not create ticket.';
                    this.messages.push({ from: 'agent', text: data.message || 'Could not create ticket.' });
                }
            } catch (e) {
                this.ticketError = 'Ticket request failed.';
                this.messages.push({ from: 'agent', text: 'Ticket request failed.' });
            }
            this.ticketBusy = false;
            this.bumpActivity();
            this.$nextTick(() => this.scrollMessages());
        },
    };
}
</script>
