<?php

namespace Tests\Unit;

use App\Models\Domain;
use App\Models\DomainDetectionSetting;
use App\Models\User;
use App\Support\GeoAudienceMatcher;
use PHPUnit\Framework\TestCase;

class GeoAudienceMatcherWorkspaceTest extends TestCase
{
    public function test_workspace_scope_inherits_user_geo_defaults(): void
    {
        $user = new User([
            'workspace_geo_settings' => [
                'out_of_geo_enabled' => true,
                'out_of_geo_audience' => [
                    'rules' => [
                        ['country' => 'CA', 'state' => null, 'city' => null],
                    ],
                ],
            ],
        ]);

        $domain = new Domain(['user_id' => 1]);
        $domain->setRelation('user', $user);

        $settings = new DomainDetectionSetting([
            'geo_rule_scope' => 'workspace',
            'out_of_geo_enabled' => true,
            'out_of_geo_audience' => [
                'rules' => [
                    ['country' => 'US', 'state' => null, 'city' => null],
                ],
            ],
        ]);

        $this->assertTrue(GeoAudienceMatcher::isAllowed($settings, 'CA', null, null, null, $domain));
        $this->assertFalse(GeoAudienceMatcher::isAllowed($settings, 'US', null, null, null, $domain));
    }

    public function test_domain_scope_ignores_workspace_defaults(): void
    {
        $user = new User([
            'workspace_geo_settings' => [
                'out_of_geo_enabled' => true,
                'out_of_geo_audience' => [
                    'rules' => [
                        ['country' => 'CA', 'state' => null, 'city' => null],
                    ],
                ],
            ],
        ]);

        $domain = new Domain(['user_id' => 1]);
        $domain->setRelation('user', $user);

        $settings = new DomainDetectionSetting([
            'geo_rule_scope' => 'domain',
            'out_of_geo_enabled' => true,
            'out_of_geo_audience' => [
                'rules' => [
                    ['country' => 'US', 'state' => null, 'city' => null],
                ],
            ],
        ]);

        $this->assertTrue(GeoAudienceMatcher::isAllowed($settings, 'US', null, null, null, $domain));
        $this->assertFalse(GeoAudienceMatcher::isAllowed($settings, 'CA', null, null, null, $domain));
    }
}
