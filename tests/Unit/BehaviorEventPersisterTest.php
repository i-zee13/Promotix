<?php

namespace Tests\Unit;

use App\Support\BehaviorEventPersister;
use PHPUnit\Framework\TestCase;

class BehaviorEventPersisterTest extends TestCase
{
    public function test_extracts_typed_behavior_rows(): void
    {
        $rows = BehaviorEventPersister::extractRows([
            ['type' => 'cta_click', 't' => 100, 'ts' => 1700000000100, 'href' => '/buy', 'element_text' => 'Buy', 'page_url' => 'https://ex.com/a', 'session_id' => 's1', 'visitor_id' => 'v1', 'tag' => 'A'],
            ['type' => 'phone_click', 't' => 200, 'href' => 'tel:+1999', 'tel_number' => '+1999', 'page_url' => 'https://ex.com/a'],
            ['type' => 'form_submit', 't' => 300, 'form_id' => 'lead', 'success' => 0, 'page_url' => 'https://ex.com/a'],
            ['type' => 'scroll', 't' => 400, 'y' => 10],
            ['type' => 'scroll', 't' => 450, 'depth' => 50, 'page_url' => 'https://ex.com/a'],
            ['type' => 'page_change', 't' => 500, 'url' => 'https://ex.com/b', 'path' => '/b'],
            ['type' => 'session_exit', 't' => 600, 'page_url' => 'https://ex.com/b', 'path' => '/b'],
            ['type' => 'mousemove', 't' => 10, 'x' => 1, 'y' => 2],
        ], 9, 88, 77, 's1', 'v1');

        $types = array_column($rows, 'event_type');
        $this->assertSame(['cta_click', 'phone_click', 'form_submit', 'scroll', 'page_change', 'session_exit'], $types);
        $this->assertSame('Buy', $rows[0]['element_text']);
        $this->assertSame('anchor', $rows[0]['link_type']);
        $this->assertSame('+1999', $rows[1]['tel_number']);
        $this->assertFalse($rows[2]['success']);
        $this->assertSame(50, $rows[3]['scroll_depth']);
    }
}
