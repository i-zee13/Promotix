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

    play(canvas, events, onDone) {
        if (!canvas) return () => {};
        const ctx = canvas.getContext('2d');
        const normalized = this.normalize(events);
        const meta = normalized.find((e) => e.type === 'meta') || {};
        const moves = normalized.filter((e) => e.type === 'mousemove');
        const clicks = normalized.filter((e) => e.type === 'click');
        const w = canvas.width;
        const h = canvas.height;
        let frameId = null;
        let startTs = null;
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
        };

        if (!moves.length) {
            drawBase();
            ctx.fillStyle = '#a9a9a9';
            ctx.font = '13px sans-serif';
            ctx.fillText('No movement captured for this session', 20, 40);
            if (onDone) onDone();
            return () => {};
        }

        const step = (ts) => {
            if (!startTs) startTs = ts;
            const elapsed = ts - startTs;
            const visibleMoves = moves.filter((e) => e.t <= elapsed);
            const visibleClicks = clicks.filter((e) => e.t <= elapsed);

            drawBase();

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

            ctx.fillStyle = 'rgba(255,255,255,0.75)';
            ctx.font = '11px sans-serif';
            ctx.fillText(`Replay ${Math.min(100, Math.round((elapsed / duration) * 100))}%`, 12, h - 12);

            if (elapsed < duration + 300) {
                frameId = requestAnimationFrame(step);
            } else if (onDone) {
                onDone();
            }
        };

        frameId = requestAnimationFrame(step);

        return () => {
            if (frameId) cancelAnimationFrame(frameId);
        };
    },
};
</script>
