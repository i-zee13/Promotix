<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\GuidanceArticle;
use App\Models\Team;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdminPanelBootstrapSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            ['slug' => 'billing', 'name' => 'Billing', 'sort_order' => 10],
            ['slug' => 'support', 'name' => 'Support', 'sort_order' => 20],
            ['slug' => 'account', 'name' => 'Account', 'sort_order' => 30],
            ['slug' => 'verification', 'name' => 'Verification', 'sort_order' => 40],
            ['slug' => 'technical', 'name' => 'Technical', 'sort_order' => 50],
            ['slug' => 'integrations', 'name' => 'Integrations', 'sort_order' => 60],
            ['slug' => 'other', 'name' => 'Other', 'sort_order' => 70],
        ];

        foreach ($departments as $row) {
            Department::query()->updateOrCreate(['slug' => $row['slug']], $row);
        }

        $teams = [
            ['name' => 'Chat Support', 'department' => 'support'],
            ['name' => 'Support', 'department' => 'support'],
            ['name' => 'Sales Team', 'department' => 'account'],
            ['name' => 'Marketing', 'department' => 'account'],
            ['name' => 'Development', 'department' => 'technical'],
            ['name' => 'Design Team', 'department' => 'technical'],
            ['name' => 'Billing Ops', 'department' => 'billing'],
            ['name' => 'Integrations', 'department' => 'integrations'],
        ];

        foreach ($teams as $row) {
            $dept = Department::query()->where('slug', $row['department'])->first();
            Team::query()->updateOrCreate(
                ['slug' => Str::slug($row['name'])],
                [
                    'name' => $row['name'],
                    'department_id' => $dept?->id,
                    'is_active' => true,
                    'description' => 'Operational team — members only via Super Admin assignment.',
                ]
            );
        }

        $articles = [
            [
                'title' => 'Install tracking tag',
                'question_variants' => ['how to install tag', 'wordpress key', 'gtm setup', 'tracking script'],
                'answer' => 'Open Site Management → Domains → Setup. Choose GTM, WordPress Plugin, Direct Installation, or Email my Developer. Each domain has unique Domain / Secret / Auth keys.',
                'steps' => "1. Domains → Setup\n2. Pick install method\n3. Copy keys / snippet\n4. Publish and verify tag status",
                'related_page' => '/admin/domains',
                'department' => 'technical',
                'keywords' => 'tag,gtm,wordpress,install',
            ],
            [
                'title' => 'Pixel Guard setup',
                'question_variants' => ['pixel guard', 'google tag id', 'what is pixel guard'],
                'answer' => 'Pixel Guard lives under Platform Integrate → Google menu → Pixel Guard. Enter the Google Tag / AW ID that matches the domain hostname, then Save.',
                'related_page' => '/admin/integrations',
                'department' => 'integrations',
                'keywords' => 'pixel,guard,google,ads',
            ],
            [
                'title' => 'Audience Exclusion',
                'question_variants' => ['audience exclusion', 'exclude invalid clicks'],
                'answer' => 'Use Detection Panel → Google Exclusion, or Integrations → Open Audience Exclusion. Conversion ID/label must match your Google Ads conversion action.',
                'related_page' => '/admin/paid-marketing/detection-settings',
                'department' => 'integrations',
                'keywords' => 'audience,exclusion,remarketing',
            ],
            [
                'title' => 'Download reports',
                'question_variants' => ['download report', 'export csv', 'monthly report'],
                'answer' => 'Open Settings → Data Reports. Pick date range, report type (Paid Advertising, Analytics, Traffic Control, Detection), format, then Download.',
                'related_page' => '/admin/settings',
                'department' => 'support',
                'keywords' => 'report,csv,export',
            ],
            [
                'title' => 'Billing and invoices',
                'question_variants' => ['invoice', 'billing', 'subscription payment'],
                'answer' => 'Go to Billing for plan, invoices, and payment method. Invoices are generated as HTML/PDF and emailed on purchase/renewal.',
                'related_page' => '/admin/billing',
                'department' => 'billing',
                'keywords' => 'billing,invoice,plan',
            ],
        ];

        foreach ($articles as $article) {
            GuidanceArticle::query()->updateOrCreate(
                ['title' => $article['title']],
                array_merge($article, ['is_published' => true])
            );
        }

        if (class_exists(\App\Models\EmailTemplate::class) && \Illuminate\Support\Facades\Schema::hasTable('email_templates')) {
            $invoice = \App\Support\EmailTemplateDefaults::forKey('invoice_email');
            if ($invoice) {
                \App\Models\EmailTemplate::query()->updateOrCreate(
                    ['key' => 'invoice_email'],
                    [
                        'name' => 'Invoice Email',
                        'subject' => $invoice['subject'],
                        'body' => $invoice['body'],
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
