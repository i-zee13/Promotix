<script>
window.PromotixSessionRecordingPlayer = {
    normalize(events) {
        const rows = Array.isArray(events) ? events : [];
        return rows.map((event) => {
            if (!event || typeof event !== 'object') return null;
            const type = String(event.type || '');
            const t = Number(event.t || 0);
            if (type === 'meta') {
                return { t, type: 'meta', vw: Number(event.vw || event.data?.vw || 0), vh: Number(event.vh || event.data?.vh || 0) };
            }
            const x = event.x ?? event.data?.x;
            const y = event.y ?? event.data?.y;
            if ((type === 'mousemove' || type === 'move') && Number.isFinite(Number(x)) && Number.isFinite(Number(y))) {
                return { t, type: 'mousemove', x: Number(x), y: Number(y) };
            }
            if (type === 'click' && Number.isFinite(Number(x)) && Number.isFinite(Number(y))) {
                return { t, type: 'click', x: Number(x), y: Number(y), tag: String(event.tag || event.data?.tag || '') };
            }
            if (type === 'scroll') {
                return { t, type: 'scroll', x: Number(x || 0), y: Number(y || 0) };
            }
            return null;
        }).filter(Boolean).sort((a, b) => a.t - b.t);
    },

    /**
     * Enhanced player (SR-05): play/pause, speed, seek, event markers.
     * Returns a controller: { stop, pause, resume, setSpeed, seek }
     */
    play(canvas, events, onDone, options = {}) {
        if (!canvas) return { stop() {} };
        const ctx = canvas.getContext('2d');
        const normalized = this.normalize(events);
        const meta = normalized.find((e) => e.type === 'meta') || {};
        const moves = normalized.filter((e) => e.type === 'mousemove');
        const clicks = normalized.filter((e) => e.type === 'click');
        const scrolls = normalized.filter((e) => e.type === 'scroll');
        const w = canvas.width;
        const h = canvas.height;
        let frameId = null;
        let startTs = null;
        let pausedAt = null;
        let pausedElapsed = 0;
        let playing = true;
        let speed = Number(options.speed || 1) || 1;
        let seekMs = 0;
        const duration = Math.max(...normalized.map((e) => e.t), 1);

        const maxX = Math.max(Number(meta.vw || 0), ...moves.map((e) => e.x), ...clicks.map((e) => e.x), 1);
        const maxY = Math.max(Number(meta.vh || 0), ...moves.map((e) => e.y), ...clicks.map((e) => e.y), 1);

        const scale = (x, y) => ({
            x: (x / maxX) * (w - 24) + 12,
            y: (y / maxY) * (h - 24) + 12,
        });

        const drawBase = () => {
            ctx.fillStyle = '#0d0d0d';
            ctx.fillRect(0, 0, w, h);
            ctx.strokeStyle = 'rgba(100,0,178,0.25)';
            ctx.lineWidth = 1;
            for (let x = 0; x < w; x += 40) {
                ctx.beginPath(); ctx.moveTo(x, 0); ctx.lineTo(x, h); ctx.stroke();
            }
            for (let y = 0; y < h; y += 40) {
                ctx.beginPath(); ctx.moveTo(0, y); ctx.lineTo(w, y); ctx.stroke();
            }
            // Event marker rail
            ctx.fillStyle = 'rgba(255,255,255,0.08)';
            ctx.fillRect(0, h - 18, w, 18);
            clicks.forEach((e) => {
                const x = (e.t / duration) * w;
                ctx.fillStyle = 'rgba(255,75,193,0.85)';
                ctx.fillRect(x - 1, h - 18, 2, 18);
            });
            scrolls.forEach((e) => {
                const x = (e.t / duration) * w;
                ctx.fillStyle = 'rgba(56,189,248,0.7)';
                ctx.fillRect(x - 1, h - 18, 2, 10);
            });
        };

        const renderAt = (elapsed) => {
            const visibleMoves = moves.filter((e) => e.t <= elapsed);
            const visibleClicks = clicks.filter((e) => e.t <= elapsed);
            drawBase();

            if (!moves.length && !clicks.length) {
                ctx.fillStyle = '#a9a9a9';
                ctx.font = '13px sans-serif';
                ctx.fillText('No movement captured for this session', 20, 40);
                return;
            }

            if (visibleMoves.length) {
                ctx.strokeStyle = '#B893D8';
                ctx.lineWidth = 2;
                ctx.beginPath();
                visibleMoves.forEach((e, i) => {
                    const p = scale(e.x, e.y);
                    if (i === 0) ctx.moveTo(p.x, p.y);
                    else ctx.lineTo(p.x, p.y);
                });
                ctx.stroke();

                const last = visibleMoves[visibleMoves.length - 1];
                const lp = scale(last.x, last.y);
                ctx.fillStyle = '#6400B2';
                ctx.beginPath();
                ctx.arc(lp.x, lp.y, 7, 0, Math.PI * 2);
                ctx.fill();
                ctx.strokeStyle = '#ffffff';
                ctx.lineWidth = 2;
                ctx.stroke();
            }

            visibleClicks.forEach((e) => {
                const p = scale(e.x, e.y);
                ctx.fillStyle = 'rgba(255,75,193,0.55)';
                ctx.beginPath();
                ctx.arc(p.x, p.y, 10, 0, Math.PI * 2);
                ctx.fill();
            });

            const playhead = (elapsed / duration) * w;
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(playhead - 1, h - 18, 2, 18);

            ctx.fillStyle = 'rgba(255,255,255,0.75)';
            ctx.font = '11px sans-serif';
            const vw = meta.vw || '—';
            const vh = meta.vh || '—';
            ctx.fillText(`Replay ${Math.min(100, Math.round((elapsed / duration) * 100))}% · ${speed}x · ${vw}×${vh}`, 12, h - 24);
        };

        if (!moves.length && !clicks.length) {
            renderAt(0);
            if (onDone) onDone();
            return { stop() {}, pause() {}, resume() {}, setSpeed() {}, seek() {} };
        }

        const step = (ts) => {
            if (!playing) return;
            if (!startTs) startTs = ts - (pausedElapsed / speed);
            const elapsed = Math.min(duration + 300, ((ts - startTs) * speed) + seekMs);
            pausedElapsed = elapsed;
            renderAt(elapsed);

            if (elapsed < duration + 300) {
                frameId = requestAnimationFrame(step);
            } else if (onDone) {
                onDone();
            }
        };

        frameId = requestAnimationFrame(step);

        const controller = {
            stop() {
                playing = false;
                if (frameId) cancelAnimationFrame(frameId);
            },
            pause() {
                if (!playing) return;
                playing = false;
                pausedAt = performance.now();
                if (frameId) cancelAnimationFrame(frameId);
            },
            resume() {
                if (playing) return;
                playing = true;
                startTs = null;
                frameId = requestAnimationFrame(step);
            },
            setSpeed(next) {
                speed = Number(next) || 1;
                startTs = null;
            },
            seek(ms) {
                seekMs = Math.max(0, Math.min(duration, Number(ms) || 0));
                pausedElapsed = seekMs;
                startTs = null;
                renderAt(seekMs);
                if (playing) {
                    if (frameId) cancelAnimationFrame(frameId);
                    frameId = requestAnimationFrame(step);
                }
            },
            get duration() { return duration; },
            get markers() {
                return [
                    ...clicks.map((e) => ({ t: e.t, type: 'click' })),
                    ...scrolls.map((e) => ({ t: e.t, type: 'scroll' })),
                ];
            },
        };

        return controller;
    },
};
</script>
