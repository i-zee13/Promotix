<?php

namespace Tests\Unit;

use App\Http\Controllers\SuperAdmin\SupportPagesController;
use ReflectionMethod;
use Tests\TestCase;

class BrandingColorNormalizeTest extends TestCase
{
    public function test_normalizes_hex_variants(): void
    {
        $method = new ReflectionMethod(SupportPagesController::class, 'normalizeBrandingColor');
        $method->setAccessible(true);
        $controller = app(SupportPagesController::class);

        $this->assertSame('#6400B2', $method->invoke($controller, '#6400b2'));
        $this->assertSame('#6400B2', $method->invoke($controller, '6400b2'));
        $this->assertSame('#FF6600', $method->invoke($controller, '#f60'));
        $this->assertNull($method->invoke($controller, 'not-a-color'));
        $this->assertNull($method->invoke($controller, ''));
    }
}
