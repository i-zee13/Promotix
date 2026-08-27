<?php

namespace Tests\Unit;

use App\Support\AudienceExclusionAudiences;
use PHPUnit\Framework\TestCase;

class AudienceExclusionAudiencesTest extends TestCase
{
    public function test_normalizes_and_drops_empty_rows(): void
    {
        $rows = AudienceExclusionAudiences::normalize([
            ['conversion_id' => ' AW-1 ', 'conversion_label' => ' invalid ', 'tag' => 'domain.example', 'domain_id' => 9],
            ['conversion_id' => '', 'conversion_label' => '', 'tag' => ''],
        ]);

        $this->assertCount(1, $rows);
        $this->assertSame('AW-1', $rows[0]['conversion_id']);
        $this->assertSame('invalid', $rows[0]['conversion_label']);
        $this->assertSame(9, $rows[0]['domain_id']);
    }

    public function test_validation_requires_fields(): void
    {
        $errors = AudienceExclusionAudiences::validationErrors([
            ['conversion_id' => '', 'conversion_label' => 'x', 'tag' => 't', 'domain_id' => null],
        ]);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Conversion ID', $errors[0]);
    }

    public function test_empty_list_errors(): void
    {
        $errors = AudienceExclusionAudiences::validationErrors([]);
        $this->assertSame(['Add at least one Conversion ID / Label / Tag row.'], $errors);
    }
}
