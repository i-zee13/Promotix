<?php

namespace Tests\Unit;

use App\Support\SortableRows;
use PHPUnit\Framework\TestCase;

class SortableRowsTest extends TestCase
{
    public function test_sorts_numeric_column_ascending_and_descending(): void
    {
        $rows = [
            ['ip' => '1.1.1.1', 'invalid_clicks' => 5],
            ['ip' => '2.2.2.2', 'invalid_clicks' => 1],
            ['ip' => '3.3.3.3', 'invalid_clicks' => 9],
        ];

        $asc = SortableRows::sort($rows, 'invalid_clicks', 'asc', ['invalid_clicks']);
        $this->assertSame([1, 5, 9], array_column($asc, 'invalid_clicks'));

        $desc = SortableRows::sort($rows, 'invalid_clicks', 'desc', ['invalid_clicks']);
        $this->assertSame([9, 5, 1], array_column($desc, 'invalid_clicks'));
    }

    public function test_sorts_string_column_naturally(): void
    {
        $rows = [
            ['campaign' => 'Campaign 10'],
            ['campaign' => 'Campaign 2'],
            ['campaign' => 'Campaign 1'],
        ];

        $sorted = SortableRows::sort($rows, 'campaign', 'asc');
        $this->assertSame(['Campaign 1', 'Campaign 2', 'Campaign 10'], array_column($sorted, 'campaign'));
    }

    public function test_toggle_direction_keeps_selected_column_visible_state(): void
    {
        [$key, $dir] = SortableRows::toggleDirection('visits', 'visits', 'asc');
        $this->assertSame('visits', $key);
        $this->assertSame('desc', $dir);

        [$key2, $dir2] = SortableRows::toggleDirection('visits', 'invalid_clicks', 'desc');
        $this->assertSame('invalid_clicks', $key2);
        $this->assertSame('asc', $dir2);
    }
}
