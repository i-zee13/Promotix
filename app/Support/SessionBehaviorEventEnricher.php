<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Merge visit_behavior_events into Traffic Control / Visitor Journey session rows
 * so CTA, phone, form, scroll, and page-change markers match what the tag captured.
 */
class SessionBehaviorEventEnricher
{
    /**
     * @param  list<array<string, mixed>>  $sessions
     * @param  list<int>  $domainIds
     * @return list<array<string, mixed>>
     */
    public function enrich(array $sessions, array $domainIds, Carbon $from, Carbon $to): array
    {
        if ($sessions === [] || $domainIds === [] || ! Schema::hasTable('visit_behavior_events')) {
            return $sessions;
        }

        $sessionIds = [];
        foreach ($sessions as $row) {
            $sid = trim((string) ($row['session_id'] ?? $row['session_key'] ?? ''));
            if ($sid !== '' && ! str_starts_with($sid, 'ip:')) {
                $sessionIds[] = $sid;
            }
        }
        $sessionIds = array_values(array_unique($sessionIds));
        if ($sessionIds === [] || ! Schema::hasColumn('visit_behavior_events', 'session_id')) {
            return $sessions;
        }

        $query = DB::table('visit_behavior_events')
            ->whereIn('domain_id', $domainIds)
            ->whereBetween('occurred_at', [$from->copy()->subDay(), $to->copy()->addDay()])
            ->whereIn('session_id', $sessionIds)
            ->whereIn('event_type', [
                'page_view', 'page_change', 'scroll', 'session_exit',
                'cta_click', 'phone_click', 'tel_click',
                'form_start', 'form_submit', 'form_fill',
                'add_to_cart', 'checkout', 'begin_checkout', 'purchase', 'sale',
            ])
            ->orderBy('occurred_at')
            ->limit(12000);

        $events = $query->get([
            'session_id', 'event_type', 'page_path', 'page_url', 'occurred_at',
            'relative_ms', 'element_text', 'href', 'title', 'tel_number', 'link_type',
            'form_id', 'form_name',
        ]);
        if ($events->isEmpty()) {
            return array_map(fn (array $row) => $this->syncBucketsAndActions($row), $sessions);
        }

        $bySession = $events->groupBy(fn ($e) => (string) ($e->session_id ?? ''));

        foreach ($sessions as $i => $row) {
            $sid = trim((string) ($row['session_id'] ?? $row['session_key'] ?? ''));
            $bucket = $bySession->get($sid);
            if (! $bucket || $bucket->isEmpty()) {
                $sessions[$i] = $this->syncBucketsAndActions($row);

                continue;
            }

            $detail = is_array($row['event_detail'] ?? null) ? $row['event_detail'] : [];
            $timeline = is_array($detail['timeline'] ?? null) ? $detail['timeline'] : [];
            $existingKeys = [];
            foreach ($timeline as $tev) {
                if (is_array($tev)) {
                    $existingKeys[$this->dedupeKey($tev)] = true;
                }
            }

            $start = null;
            try {
                $start = ! empty($row['first_seen_at'])
                    ? Carbon::parse((string) $row['first_seen_at'])
                    : (! empty($row['first_seen']) ? Carbon::parse((string) $row['first_seen']) : null);
            } catch (\Throwable) {
                $start = null;
            }

            $cta = (int) ($row['cta_clicks'] ?? 0);
            $tel = (int) ($row['tel_clicks'] ?? 0);
            $formStarts = (int) ($row['form_starts'] ?? 0);
            $formSubmits = (int) ($row['form_submits'] ?? $row['form_fills'] ?? 0);
            $scrolls = (int) ($row['scroll_events'] ?? 0);
            $pages = is_array($row['pages'] ?? null) ? $row['pages'] : [];
            $added = [];

            foreach ($bucket as $ev) {
                $type = strtolower((string) ($ev->event_type ?? ''));
                $path = trim((string) ($ev->page_path ?? ''));
                if ($path === '' && ! empty($ev->page_url)) {
                    $path = TrafficSourceClassifier::pathFromUrl((string) $ev->page_url);
                }
                $relativeMs = (int) ($ev->relative_ms ?? 0);
                $elapsed = (int) max(0, (int) round($relativeMs / 1000));
                if ($elapsed === 0 && $relativeMs > 0) {
                    $elapsed = 1;
                }
                if ($elapsed === 0 && $start && ! empty($ev->occurred_at)) {
                    try {
                        $elapsed = max(0, (int) $start->diffInSeconds(Carbon::parse((string) $ev->occurred_at), true));
                    } catch (\Throwable) {
                        $elapsed = 0;
                    }
                }

                $label = match ($type) {
                    'scroll' => 'Scroll',
                    'cta_click' => trim((string) ($ev->element_text ?? '')) ?: 'CTA click',
                    'phone_click', 'tel_click' => trim((string) ($ev->element_text ?? '')) ?: 'Phone click',
                    'form_start' => 'Form start',
                    'form_submit', 'form_fill' => 'Form submit',
                    'add_to_cart' => 'Add to cart',
                    'checkout', 'begin_checkout' => 'Checkout',
                    'purchase', 'sale' => 'Purchase',
                    'session_exit' => 'Exit',
                    'page_change' => ($path !== '' ? $path : 'Page change'),
                    default => ($path !== '' ? $path : 'Page view'),
                };
                $kind = match ($type) {
                    'scroll' => 'scroll',
                    'cta_click' => 'cta',
                    'phone_click', 'tel_click' => 'phone',
                    'form_start', 'form_submit', 'form_fill' => 'form',
                    'add_to_cart', 'checkout', 'begin_checkout', 'purchase', 'sale' => 'commerce',
                    'session_exit' => 'exit',
                    default => 'page',
                };
                $normType = match ($type) {
                    'page_view', 'page_change' => 'page',
                    'phone_click', 'tel_click' => 'phone',
                    'form_start', 'form_submit', 'form_fill' => 'form',
                    'cta_click' => 'cta',
                    'scroll' => 'scroll',
                    'session_exit' => 'exit',
                    default => $kind === 'commerce' ? 'cta' : 'page',
                };

                $candidate = [
                    'type' => $normType === 'page' ? ($type === 'page_change' ? 'page_change' : 'page_view') : (
                        $normType === 'phone' ? 'phone_click' : (
                            $normType === 'cta' ? 'cta_click' : (
                                $normType === 'form' ? ($type === 'form_start' ? 'form_start' : 'form_submit') : (
                                    $normType === 'exit' ? 'session_exit' : $type
                                )
                            )
                        )
                    ),
                    'kind' => $kind,
                    'label' => $label,
                    'detail' => $label,
                    'page' => $path !== '' ? $path : '/',
                    'path' => $path !== '' ? $path : '/',
                    'page_url' => trim((string) ($ev->page_url ?? '')) ?: null,
                    't' => $relativeMs > 0 ? $relativeMs : ($elapsed * 1000),
                    'elapsed_sec' => $elapsed,
                    'at' => (string) ($ev->occurred_at ?? ''),
                    'element_text' => trim((string) ($ev->element_text ?? '')) ?: null,
                    'href' => trim((string) ($ev->href ?? '')) ?: null,
                    'tel_number' => trim((string) ($ev->tel_number ?? '')) ?: null,
                    'link_type' => trim((string) ($ev->link_type ?? '')) ?: null,
                    'form_id' => trim((string) ($ev->form_id ?? '')) ?: null,
                    'form_name' => trim((string) ($ev->form_name ?? '')) ?: null,
                ];
                // Journey UI normalizes phone/cta/form/page; keep a display type too.
                $candidate['display_type'] = $normType;

                $dedupe = $this->dedupeKey($candidate);
                if (isset($existingKeys[$dedupe])) {
                    continue;
                }
                $existingKeys[$dedupe] = true;
                $added[] = $candidate;

                if ($path !== '' && ! in_array($path, $pages, true)) {
                    $pages[] = $path;
                }
            }

            if ($added !== []) {
                $mergedTimeline = array_values(array_merge($timeline, $added));
                usort($mergedTimeline, static fn ($a, $b) => ((int) ($a['elapsed_sec'] ?? $a['t'] ?? 0)) <=> ((int) ($b['elapsed_sec'] ?? $b['t'] ?? 0)));
                $detail['timeline'] = array_slice($mergedTimeline, 0, 120);
            }

            $row['event_detail'] = $detail;
            $row['cta_clicks'] = max($cta, (int) collect($bucket)->where('event_type', 'cta_click')->count());
            $row['tel_clicks'] = max($tel, (int) collect($bucket)->whereIn('event_type', ['phone_click', 'tel_click'])->count());
            $row['form_starts'] = max($formStarts, (int) collect($bucket)->where('event_type', 'form_start')->count());
            $row['form_submits'] = max($formSubmits, (int) collect($bucket)->whereIn('event_type', ['form_submit', 'form_fill'])->count());
            $row['form_fills'] = max((int) ($row['form_fills'] ?? 0), $row['form_submits']);
            $row['scroll_events'] = max($scrolls, (int) collect($bucket)->where('event_type', 'scroll')->count());
            if ($pages !== []) {
                $row['pages'] = array_values(array_slice($pages, 0, 40));
                if (empty($row['page_flow']) || $row['page_flow'] === '—') {
                    $row['page_flow'] = implode(' -> ', array_slice($row['pages'], 0, 8));
                }
            }

            $sessions[$i] = $this->syncBucketsAndActions($row);
        }

        return $sessions;
    }

    /**
     * Rebuild event_detail kind buckets + event_actions chips from timeline + counts.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function syncBucketsAndActions(array $row): array
    {
        $detail = is_array($row['event_detail'] ?? null) ? $row['event_detail'] : [];
        $timeline = is_array($detail['timeline'] ?? null) ? $detail['timeline'] : [];

        $byKind = [
            'cta' => [],
            'phone' => [],
            'tel' => [],
            'form' => [],
            'commerce' => [],
            'page' => [],
            'scroll' => [],
        ];
        foreach ($timeline as $item) {
            if (! is_array($item)) {
                continue;
            }
            $kind = strtolower((string) ($item['kind'] ?? ''));
            $type = strtolower((string) ($item['type'] ?? $item['display_type'] ?? ''));
            if ($kind === '' || $kind === 'exit') {
                $kind = match (true) {
                    in_array($type, ['cta', 'cta_click'], true) => 'cta',
                    in_array($type, ['phone', 'phone_click', 'tel_click'], true) => 'phone',
                    in_array($type, ['form', 'form_start', 'form_submit', 'form_fill'], true) => 'form',
                    $type === 'scroll' => 'scroll',
                    in_array($type, ['add_to_cart', 'checkout', 'purchase', 'sale'], true) => 'commerce',
                    default => 'page',
                };
            }
            if ($kind === 'cta') {
                $byKind['cta'][] = $item;
            } elseif ($kind === 'phone') {
                $byKind['phone'][] = $item;
                $byKind['tel'][] = $item;
            } elseif ($kind === 'form') {
                $byKind['form'][] = $item;
            } elseif ($kind === 'commerce') {
                $byKind['commerce'][] = $item;
            } elseif ($kind === 'scroll') {
                $byKind['scroll'][] = $item;
            } elseif ($kind === 'page') {
                $byKind['page'][] = $item;
            }
        }

        $detail['cta'] = $byKind['cta'];
        $detail['tel'] = $byKind['tel'];
        $detail['phone'] = $byKind['phone'];
        $detail['form'] = $byKind['form'];
        $detail['commerce'] = $byKind['commerce'];
        $detail['pages'] = $byKind['page'] !== [] ? $byKind['page'] : ($detail['pages'] ?? []);
        $detail['scroll'] = $byKind['scroll'];
        $detail['timeline'] = $timeline;
        $row['event_detail'] = $detail;

        // Counts win over empty buckets when timeline lacked typed kinds.
        $cta = max((int) ($row['cta_clicks'] ?? 0), count($byKind['cta']));
        $tel = max((int) ($row['tel_clicks'] ?? 0), count($byKind['phone']));
        $formStarts = max((int) ($row['form_starts'] ?? 0), count(array_filter(
            $byKind['form'],
            static fn ($ev) => str_contains(strtolower((string) ($ev['type'] ?? $ev['label'] ?? '')), 'start')
        )));
        $formFills = max(
            (int) ($row['form_fills'] ?? 0),
            (int) ($row['form_submits'] ?? 0),
            count(array_filter(
                $byKind['form'],
                static function ($ev) {
                    $t = strtolower((string) ($ev['type'] ?? $ev['label'] ?? ''));

                    return str_contains($t, 'submit') || str_contains($t, 'fill');
                }
            ))
        );
        $scrolls = max((int) ($row['scroll_events'] ?? 0), count($byKind['scroll']));
        $pageViews = max((int) ($row['page_views'] ?? 0), count($row['pages'] ?? []), count($byKind['page']));

        $row['cta_clicks'] = $cta;
        $row['tel_clicks'] = $tel;
        $row['form_starts'] = $formStarts;
        $row['form_fills'] = $formFills;
        $row['form_submits'] = $formFills;
        $row['scroll_events'] = $scrolls;
        $row['page_views'] = $pageViews;

        $eventActions = [];
        if ($pageViews > 0) {
            $eventActions[] = ['key' => 'page_view', 'count' => $pageViews];
        }
        if ($cta > 0) {
            $eventActions[] = ['key' => 'cta_click', 'count' => $cta];
        }
        if ($tel > 0) {
            $eventActions[] = ['key' => 'tel_click', 'count' => $tel];
        }
        if ($formStarts > 0) {
            $eventActions[] = ['key' => 'form_start', 'count' => $formStarts];
        }
        if ($formFills > 0) {
            $eventActions[] = ['key' => 'form_submit', 'count' => $formFills];
        }
        if ($scrolls > 0) {
            $eventActions[] = ['key' => 'scroll', 'count' => $scrolls];
        }
        if ((int) ($row['add_to_cart'] ?? 0) > 0) {
            $eventActions[] = ['key' => 'add_to_cart', 'count' => (int) $row['add_to_cart']];
        }
        if ((int) ($row['checkout'] ?? 0) > 0) {
            $eventActions[] = ['key' => 'checkout', 'count' => (int) $row['checkout']];
        }
        if (($row['purchase'] ?? 'No') === 'Yes' || (int) ($row['purchase'] ?? 0) > 0) {
            $eventActions[] = ['key' => 'purchase', 'count' => 1];
        }
        $row['event_actions'] = $eventActions;

        return $row;
    }

    /**
     * @param  array<string, mixed>  $ev
     */
    private function dedupeKey(array $ev): string
    {
        $type = strtolower((string) ($ev['type'] ?? $ev['kind'] ?? $ev['display_type'] ?? ''));
        $path = strtolower(trim((string) ($ev['path'] ?? $ev['page'] ?? $ev['label'] ?? '')));
        $t = (int) ($ev['elapsed_sec'] ?? 0);
        if ($t <= 0) {
            $raw = (int) ($ev['t'] ?? 0);
            $t = $raw >= 1000 ? (int) floor($raw / 1000) : $raw;
        }
        $text = strtolower(trim((string) ($ev['element_text'] ?? $ev['href'] ?? '')));

        return $type.'|'.$path.'|'.$t.'|'.$text;
    }
}
