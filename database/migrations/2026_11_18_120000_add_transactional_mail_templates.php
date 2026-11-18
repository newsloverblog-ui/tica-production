<?php

use App\Support\MailTemplateCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
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

    public function down(): void
    {
        if (Schema::hasTable('mail_templates')) {
            DB::table('mail_templates')->whereIn('mail_type', array_keys(MailTemplateCatalog::definitions()))->delete();
        }
    }
};
