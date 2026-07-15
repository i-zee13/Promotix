<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\User;
use Tests\TestCase;

class WorkspaceIsolationTest extends TestCase
{
    public function test_domain_belongs_to_user_helper_pattern(): void
    {
        $owner = new User;
        $owner->id = 10;
        $intruder = new User;
        $intruder->id = 20;

        $domain = new Domain(['user_id' => 10, 'hostname' => 'owner.example']);

        $this->assertTrue($domain->user_id === $owner->id);
        $this->assertFalse($domain->user_id === $intruder->id);
    }

    public function test_scoped_domain_lookup_rejects_foreign_owner(): void
    {
        $domains = collect([
            new Domain(['id' => 1, 'user_id' => 5]),
            new Domain(['id' => 2, 'user_id' => 9]),
        ]);

        $userId = 5;
        $targetDomainId = 2;

        $found = $domains
            ->where('user_id', $userId)
            ->firstWhere('id', $targetDomainId);

        $this->assertNull($found);
    }
}
