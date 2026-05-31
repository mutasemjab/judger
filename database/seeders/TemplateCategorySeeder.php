<?php

namespace Database\Seeders;

use App\Models\Template;
use App\Models\TemplateCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TemplateCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Contracts', 'slug' => 'contracts', 'description' => 'Legal contract templates'],
            ['name' => 'Legal Notices', 'slug' => 'legal-notices', 'description' => 'Official legal notice templates'],
            ['name' => 'Complaints', 'slug' => 'complaints', 'description' => 'Legal complaint templates'],
            ['name' => 'Defense Memos', 'slug' => 'defense-memos', 'description' => 'Defense memorandum templates'],
            ['name' => 'Appeals', 'slug' => 'appeals', 'description' => 'Appeal document templates'],
            ['name' => 'Agreements', 'slug' => 'agreements', 'description' => 'Agreement and settlement templates'],
            ['name' => 'Legal Forms', 'slug' => 'legal-forms', 'description' => 'Standard legal form templates'],
        ];

        foreach ($categories as $category) {
            TemplateCategory::firstOrCreate(['slug' => $category['slug']], $category);
        }

        $contractsCategory = TemplateCategory::where('slug', 'contracts')->first();
        $noticesCategory = TemplateCategory::where('slug', 'legal-notices')->first();

        if ($contractsCategory) {
            $templates = [
                [
                    'title' => 'Service Agreement',
                    'slug' => 'service-agreement',
                    'description' => 'Standard service agreement template',
                    'content' => "SERVICE AGREEMENT\n\nThis Service Agreement (\"Agreement\") is entered into as of {{date}} between:\n\n{{client_name}} (\"Client\")\nand\n{{service_provider}} (\"Service Provider\")\n\nCase Reference: {{case_number}}\nJurisdiction: {{jurisdiction}}\n\n1. SERVICES\nThe Service Provider agrees to provide the following services:\n{{services_description}}\n\n2. PAYMENT\nClient agrees to pay {{payment_amount}} {{payment_currency}} for the services.\n\n3. TERM\nThis Agreement shall commence on {{start_date}} and continue until {{end_date}}.\n\nIN WITNESS WHEREOF, the parties have executed this Agreement as of the date first written above.\n\n_________________________\n{{client_name}}\n\n_________________________\n{{service_provider}}",
                    'variables' => ['date', 'client_name', 'service_provider', 'case_number', 'jurisdiction', 'services_description', 'payment_amount', 'payment_currency', 'start_date', 'end_date'],
                    'is_active' => true,
                ],
            ];

            foreach ($templates as $template) {
                $template['template_category_id'] = $contractsCategory->id;
                Template::firstOrCreate(['slug' => $template['slug']], $template);
            }
        }

        if ($noticesCategory) {
            $templates = [
                [
                    'title' => 'Legal Demand Notice',
                    'slug' => 'legal-demand-notice',
                    'description' => 'Standard demand notice template',
                    'content' => "LEGAL DEMAND NOTICE\n\nDate: {{date}}\n\nTo: {{recipient_name}}\n{{recipient_address}}\n\nFrom: {{sender_name}}\n{{sender_address}}\n\nRe: {{subject}}\nCase Number: {{case_number}}\n\nDear {{recipient_name}},\n\nThis notice is to formally demand {{demand_description}}.\n\nYou are hereby notified that unless {{demand_action}} within {{response_days}} days from the date of this notice, we will be compelled to take legal action.\n\nThis notice is without prejudice to any other rights or remedies available to us.\n\nSincerely,\n\n{{sender_name}}\n{{sender_title}}",
                    'variables' => ['date', 'recipient_name', 'recipient_address', 'sender_name', 'sender_address', 'subject', 'case_number', 'demand_description', 'demand_action', 'response_days', 'sender_title'],
                    'is_active' => true,
                ],
            ];

            foreach ($templates as $template) {
                $template['template_category_id'] = $noticesCategory->id;
                Template::firstOrCreate(['slug' => $template['slug']], $template);
            }
        }
    }
}
