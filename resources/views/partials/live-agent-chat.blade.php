{{-- Live agent chat panel (trigger via @click or $dispatch('open-live-agent')) --}}
<div
    x-data="liveAgentChat()"
    x-cloak
    @open-live-agent.window="openPanel()"
    class="contents"
>
    <div
        x-show="open"
        x-transition.opacity
        class="fixed inset-0 z-[300] bg-black/55"
        @click="open = false"
        aria-hidden="true"
    ></div>

    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="translate-y-4 opacity-0"
        x-transition:enter-end="translate-y-0 opacity-100"
        class="fixed bottom-[20px] right-[20px] z-[310] flex h-[min(520px,85vh)] w-[min(360px,calc(100vw-24px))] flex-col overflow-hidden rounded-[12px] border border-[#6400B2]/60 bg-[#101010] shadow-[0_16px_48px_rgba(0,0,0,.55)]"
        role="dialog"
        aria-label="Live agent chat"
    >
        <header class="flex items-center justify-between border-b border-white/10 bg-[#6400B2] px-[14px] py-[12px]">
            <div>
                <p class="text-[13px] font-semibold text-white">Live Agent</p>
                <p class="text-[10px] text-white/75" x-text="agentOnline ? 'Online — typically replies in a few minutes' : 'Connecting…'"></p>
            </div>
            <button type="button" @click="open = false" class="rounded p-1 text-white/80 hover:bg-white/10" aria-label="Close chat">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </header>

        <div class="flex-1 space-y-[10px] overflow-y-auto px-[14px] py-[12px] text-[12px]" id="live-agent-messages">
            <template x-for="(msg, idx) in messages" :key="idx">
                <div :class="msg.from === 'user' ? 'ml-[24px] text-right' : 'mr-[24px]'">
                    <p
                        class="inline-block max-w-full rounded-[8px] px-[10px] py-[8px] text-left"
                        :class="msg.from === 'user' ? 'bg-[#6400B2] text-white' : 'bg-[#1a1a1a] text-white/90 border border-white/10'"
                        x-text="msg.text"
                    ></p>
                </div>
            </template>
        </div>

        <form @submit.prevent="send()" class="border-t border-white/10 p-[12px]">
            <div class="flex gap-[8px]">
                <input
                    x-model="draft"
                    type="text"
                    placeholder="Type your message…"
                    class="h-[36px] flex-1 rounded-[6px] border border-white/15 bg-[#0d0d0d] px-[10px] text-[12px] text-white placeholder:text-white/40 focus:border-[#6400B2] focus:ring-0"
                >
                <button type="submit" class="shrink-0 rounded-[6px] bg-[#6400B2] px-[14px] text-[12px] font-semibold text-white hover:bg-[#7B13C8]">Send</button>
            </div>
        </form>
    </div>
</div>

<script>
function liveAgentChat() {
    return {
        open: false,
        draft: '',
        agentOnline: true,
        messages: [
            { from: 'agent', text: 'Hi! I\'m your PromoTix live agent. Ask about integrations, bot protection, or paid marketing setup.' },
        ],
        openPanel() {
            this.open = true;
            this.$nextTick(() => {
                const el = document.getElementById('live-agent-messages');
                if (el) el.scrollTop = el.scrollHeight;
            });
        },
        send() {
            const text = String(this.draft || '').trim();
            if (!text) return;
            this.messages.push({ from: 'user', text });
            this.draft = '';
            const replies = [
                'Thanks — a specialist will follow up shortly. For Google Ads sync, use Platform Integrate → Sync Ads.',
                'You can review invalid traffic under Bot Protection and Paid Marketing dashboards.',
                'Need help linking a domain? Open Platform Integrate and filter your connected mappings.',
            ];
            const reply = replies[Math.floor(Math.random() * replies.length)];
            setTimeout(() => {
                this.messages.push({ from: 'agent', text: reply });
                this.$nextTick(() => {
                    const el = document.getElementById('live-agent-messages');
                    if (el) el.scrollTop = el.scrollHeight;
                });
            }, 700);
            this.$nextTick(() => {
                const el = document.getElementById('live-agent-messages');
                if (el) el.scrollTop = el.scrollHeight;
            });
        },
    };
}
</script>
