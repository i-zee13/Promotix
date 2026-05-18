@props(['label', 'value', 'progress' => 60])

<article class="figma-sa-dash-kpi">
    <p class="figma-sa-dash-kpi-label">{{ $label }}</p>
    <p class="figma-sa-dash-kpi-value">{{ $value }}</p>
    <div class="figma-sa-dash-kpi-bar" aria-hidden="true">
        <span style="width: {{ min(100, max(8, (int) $progress)) }}%"></span>
    </div>
</article>
