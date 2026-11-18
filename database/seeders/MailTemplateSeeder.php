<?php

namespace Database\Seeders;

use App\Support\MailTemplateCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('mail_templates')) return;

        foreach (MailTemplateCatalog::definitions() as $type => $template) {
            DB::table('mail_templates')->insertOrIgnore([
                'mail_type' => $type,
                'mail_subject' => $template['subject'],
                'mail_body' => $template['body'],
            ]);
        }
    }
}
