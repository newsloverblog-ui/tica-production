<?php

use App\Support\MailTemplateCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One email, one account type: settings for customer-to-organizer upgrades,
 * the upgrade request queue, migration markers and per-table email
 * uniqueness. The cross-table rule lives in App\Services\AccountTypeGuard.
 */
return new class extends Migration
{
    private const TEMPLATES = [
        'organizer_upgrade_received',
        'admin_organizer_upgrade_request',
        'organizer_upgrade_approved',
        'organizer_upgrade_rejected',
    ];

    public function up(): void
    {
        if (Schema::hasTable('basic_settings')) {
            Schema::table('basic_settings', function (Blueprint $table): void {
                if (!Schema::hasColumn('basic_settings', 'customer_organizer_upgrade')) {
                    $table->unsignedTinyInteger('customer_organizer_upgrade')->default(0);
                }
                if (!Schema::hasColumn('basic_settings', 'customer_organizer_upgrade_approval')) {
                    $table->unsignedTinyInteger('customer_organizer_upgrade_approval')->default(1);
                }
            });
        }

        Schema::table('customers', function (Blueprint $table): void {
            if (!Schema::hasColumn('customers', 'migrated_to_organizer_id')) {
                $table->unsignedBigInteger('migrated_to_organizer_id')->nullable()->index();
            }
            if (!Schema::hasColumn('customers', 'migrated_at')) {
                $table->timestamp('migrated_at')->nullable();
            }
        });

        Schema::table('organizers', function (Blueprint $table): void {
            if (!Schema::hasColumn('organizers', 'provider')) {
                $table->string('provider', 30)->nullable();
            }
            if (!Schema::hasColumn('organizers', 'provider_id')) {
                $table->string('provider_id', 191)->nullable();
            }
            if (!Schema::hasColumn('organizers', 'migrated_from_customer_id')) {
                $table->unsignedBigInteger('migrated_from_customer_id')->nullable()->index();
            }
        });

        if (!Schema::hasTable('organizer_upgrade_requests')) {
            Schema::create('organizer_upgrade_requests', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('customer_id');
                $table->string('organisation', 100);
                $table->string('contact_name', 200);
                $table->string('phone', 20);
                $table->string('status', 20)->default('pending');
                $table->text('reason')->nullable();
                $table->unsignedBigInteger('organizer_id')->nullable();
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
                $table->index(['customer_id', 'status'], 'organizer_upgrade_customer_status_idx');
                $table->index(['status', 'created_at'], 'organizer_upgrade_status_created_idx');
            });
        }

        $this->uniqueEmail('customers');
        $this->uniqueEmail('organizers');

        if (Schema::hasTable('mail_templates')) {
            $definitions = MailTemplateCatalog::definitions();
            foreach (self::TEMPLATES as $type) {
                if (!isset($definitions[$type])) {
                    continue;
                }
                DB::table('mail_templates')->insertOrIgnore([
                    'mail_type' => $type,
                    'mail_subject' => $definitions[$type]['subject'],
                    'mail_body' => $definitions[$type]['body'],
                ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('mail_templates')) {
            DB::table('mail_templates')->whereIn('mail_type', self::TEMPLATES)->delete();
        }
        Schema::dropIfExists('organizer_upgrade_requests');
        foreach (['customers', 'organizers'] as $table) {
            $this->dropUniqueEmail($table);
        }
        Schema::table('organizers', function (Blueprint $table): void {
            $table->dropIndex(['migrated_from_customer_id']);
            $table->dropColumn(['provider', 'provider_id', 'migrated_from_customer_id']);
        });
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropIndex(['migrated_to_organizer_id']);
            $table->dropColumn(['migrated_to_organizer_id', 'migrated_at']);
        });
        if (Schema::hasTable('basic_settings')) {
            Schema::table('basic_settings', function (Blueprint $table): void {
                $table->dropColumn(['customer_organizer_upgrade', 'customer_organizer_upgrade_approval']);
            });
        }
    }

    /**
     * A database-level guard against two rows with the same email in one
     * table. Skipped (and logged) when legacy data already holds duplicates:
     * those are listed by `php artisan accounts:duplicates` for an admin.
     */
    private function uniqueEmail(string $table): void
    {
        $duplicates = DB::table($table)
            ->whereNotNull('email')->where('email', '!=', '')
            ->selectRaw('LOWER(TRIM(email)) as normalized_email')
            ->groupByRaw('LOWER(TRIM(email))')
            ->havingRaw('COUNT(*) > 1')
            ->limit(1)
            ->exists();
        if ($duplicates) {
            logger()->warning("Skipped unique email index on {$table}: duplicate emails exist.");
            return;
        }

        $driver = DB::getDriverName();
        try {
            if ($driver === 'pgsql') {
                DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS {$table}_email_lower_unique ON {$table} (LOWER(email)) WHERE email IS NOT NULL AND email <> ''");
            } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
                // MySQL's default collation already compares emails without case.
                if (!$this->indexExists($table, "{$table}_email_account_unique")) {
                    DB::statement("ALTER TABLE `{$table}` ADD UNIQUE INDEX `{$table}_email_account_unique` (`email`)");
                }
            } elseif ($driver === 'sqlite') {
                DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS {$table}_email_lower_unique ON {$table} (LOWER(email)) WHERE email IS NOT NULL AND email <> ''");
            }
        } catch (\Throwable $e) {
            logger()->warning("Could not add unique email index on {$table}.", ['error' => $e->getMessage()]);
        }
    }

    private function dropUniqueEmail(string $table): void
    {
        $driver = DB::getDriverName();
        try {
            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                if ($this->indexExists($table, "{$table}_email_account_unique")) {
                    DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$table}_email_account_unique`");
                }
            } else {
                DB::statement("DROP INDEX IF EXISTS {$table}_email_lower_unique");
            }
        } catch (\Throwable) {
            // Nothing to drop.
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        return collect(Schema::getIndexes($table))->contains(fn (array $i) => $i['name'] === $index);
    }
};
