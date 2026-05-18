@props(['title', 'loadMoreRoute', 'rows' => []])

<section class="figma-sa-dash-feed">
    <div class="figma-sa-dash-feed-head">
        <h2 class="figma-sa-dash-feed-title">{{ $title }}</h2>
        <a href="{{ $loadMoreRoute }}" class="figma-sa-dash-feed-more">Load More</a>
    </div>
    <div class="figma-sa-dash-feed-cols">
        <span>Mail</span>
        <span>Date</span>
        <span>Time</span>
        <span>Price</span>
        <span>Status</span>
    </div>
    <div class="figma-sa-dash-feed-rows">
        @forelse ($rows as $row)
            <div class="figma-sa-dash-feed-row">
                <div class="figma-sa-dash-feed-mail">
                    <p class="figma-sa-dash-feed-name">{{ $row['name'] }}</p>
                    <p class="figma-sa-dash-feed-email">{{ $row['email'] }}</p>
                </div>
                <span class="figma-sa-dash-feed-muted">{{ $row['date'] }}</span>
                <span class="figma-sa-dash-feed-muted">{{ $row['time'] }}</span>
                <span class="figma-sa-dash-feed-price">{{ $row['price'] }}</span>
                <span class="figma-sa-dash-feed-status">{{ $row['status'] }}</span>
            </div>
        @empty
            <p class="figma-sa-dash-feed-empty">No records yet.</p>
        @endforelse
    </div>
</section>
