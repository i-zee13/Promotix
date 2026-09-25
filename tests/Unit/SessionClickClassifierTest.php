<?php

namespace Tests\Unit;

use App\Support\SessionClickClassifier;
use Tests\TestCase;

class SessionClickClassifierTest extends TestCase
{
    public function test_classifies_tel_callto_and_sms_hrefs(): void
    {
        $this->assertTrue(SessionClickClassifier::isTelHref('tel:+15551212'));
        $this->assertTrue(SessionClickClassifier::isTelHref('callto:+15551212'));
        $this->assertTrue(SessionClickClassifier::isTelHref('sms:+15551212'));
        $this->assertFalse(SessionClickClassifier::isTelHref('https://example.com'));
    }

    public function test_classifies_anchor_with_btn_class_as_cta(): void
    {
        $this->assertTrue(SessionClickClassifier::isCtaElement('A', 'btn btn-primary', 'cta-top'));
        $this->assertFalse(SessionClickClassifier::isCtaElement('A', 'nav-link', 'menu-home'));
    }

    public function test_classifies_cta_by_label_copy_without_btn_class(): void
    {
        $this->assertTrue(SessionClickClassifier::isCtaElement('A', 'hero-link', '', [], 'Check Availability'));
        $this->assertTrue(SessionClickClassifier::isCtaElement('A', '', '', [], '', 'https://example.com/order-now'));
        $this->assertFalse(SessionClickClassifier::isCtaElement('A', 'nav-link', '', [], 'Home'));
    }

    public function test_classifies_click_event_without_explicit_flags_using_class(): void
    {
        $classified = SessionClickClassifier::classifyClickEvent([
            'type' => 'click',
            'tag' => 'A',
            'class' => 'btn btn-lg',
            'href' => 'https://example.com/signup',
        ]);

        $this->assertTrue($classified['cta']);
        $this->assertFalse($classified['tel']);
    }

    public function test_classifies_plain_click_with_cta_label_text(): void
    {
        $classified = SessionClickClassifier::classifyClickEvent([
            'type' => 'click',
            'tag' => 'A',
            'class' => 'provider-card',
            'text' => 'See Plans',
            'href' => 'https://example.com/verizon',
        ]);

        $this->assertTrue($classified['cta']);
    }
}
