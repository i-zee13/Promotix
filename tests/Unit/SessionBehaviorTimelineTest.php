<?php

namespace Tests\Unit;

use App\Support\SessionBehaviorTimeline;
use PHPUnit\Framework\TestCase;

class SessionBehaviorTimelineTest extends TestCase
{
    public function test_builds_named_timeline_from_mixed_events(): void
    {
        $timeline = SessionBehaviorTimeline::fromEvents([
            ['type' => 'meta', 't' => 0, 'url' => '/'],
            ['type' => 'page_view', 't' => 100, 'url' => 'https://example.com/shop', 'title' => 'Shop', 'ts' => 1700000000100],
            ['type' => 'scroll', 't' => 200, 'depth' => 75, 'page_url' => '/shop'],
            ['type' => 'cta_click', 't' => 300, 'element_text' => 'Shop Now', 'href' => '/product', 'link_type' => 'anchor', 'page_url' => 'https://example.com/shop'],
            ['type' => 'phone_click', 't' => 400, 'href' => 'tel:+1555', 'tel_number' => '+1555', 'page_url' => 'https://example.com/shop'],
            ['type' => 'form_start', 't' => 500, 'form_id' => 'lead', 'form_name' => 'Lead'],
            ['type' => 'form_submit', 't' => 600, 'form_id' => 'lead', 'success' => true],
            ['type' => 'add_to_cart', 't' => 700, 'sku' => 'WH-101'],
            ['type' => 'purchase', 't' => 800, 'order_id' => 'ORD-1', 'revenue' => '119.99'],
            ['type' => 'page_change', 't' => 850, 'url' => 'https://example.com/product/wireless-headphones', 'path' => '/product/wireless-headphones'],
            ['type' => 'session_exit', 't' => 900, 'page_url' => 'https://example.com/thank-you', 'path' => '/thank-you'],
        ]);

        $labels = array_column($timeline, 'label');
        $this->assertContains('Session Start', $labels);
        $this->assertContains('Page View', $labels);
        $this->assertContains('Scroll', $labels);
        $this->assertContains('CTA Click', $labels);
        $this->assertContains('Phone Click', $labels);
        $this->assertContains('Form Start', $labels);
        $this->assertContains('Form Submit', $labels);
        $this->assertContains('Add to Cart', $labels);
        $this->assertContains('Purchase', $labels);
        $this->assertContains('Page Change', $labels);
        $this->assertContains('Session Exit', $labels);

        $cta = null;
        foreach ($timeline as $row) {
            if (($row['label'] ?? '') === 'CTA Click') {
                $cta = $row;
                break;
            }
        }
        $this->assertNotNull($cta);
        $this->assertSame('cta', $cta['kind']);
        $this->assertSame('anchor', $cta['link_type']);
        $this->assertSame('https://example.com/shop', $cta['page_url']);
    }

    public function test_ignores_raw_scroll_without_depth_marks(): void
    {
        $timeline = SessionBehaviorTimeline::fromEvents([
            ['type' => 'scroll', 't' => 10, 'x' => 0, 'y' => 40],
            ['type' => 'scroll', 't' => 20, 'depth' => 50],
        ]);

        $this->assertCount(1, $timeline);
        $this->assertSame('Scroll', $timeline[0]['label']);
        $this->assertStringContainsString('50%', $timeline[0]['detail']);
    }
}
