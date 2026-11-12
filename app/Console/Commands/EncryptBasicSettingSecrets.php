<?php

namespace App\Console\Commands;

use App\Models\BasicSettings\Basic;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EncryptBasicSettingSecrets extends Command
{
    protected $signature = 'settings:encrypt-secrets
        {--apply : Persist encryption; without this flag the command is a dry run}
        {--json : Emit machine-readable JSON}';

    protected $description = 'Encrypt legacy SMTP, CAPTCHA, and social-login secrets with the application key';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $rows = DB::table('basic_settings')->select(array_merge(['id'], Basic::SECRET_ATTRIBUTES))->orderBy('id')->get();
        $pending = [];

        foreach ($rows as $row) {
            foreach (Basic::SECRET_ATTRIBUTES as $attribute) {
                $value = $row->{$attribute};
                if (is_string($value) && $value !== '' && !Basic::secretIsEncrypted($value)) {
                    $pending[] = ['id' => $row->id, 'attribute' => $attribute, 'value' => $value];
                }
            }
        }

        if ($apply && $pending !== []) {
            DB::transaction(function () use ($pending): void {
                foreach ($pending as $secret) {
                    DB::table('basic_settings')->where('id', $secret['id'])->update([
                        $secret['attribute'] => Basic::encryptSecret($secret['value']),
                    ]);
                }
            });
        }

        $result = [
            'mode' => $apply ? 'apply' : 'dry-run',
            'rows' => $rows->count(),
            'pending_values' => count($pending),
            'encrypted_values' => $apply ? count($pending) : 0,
        ];

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->info(sprintf(
                '%s: %d setting row(s) inspected; %d secret value(s) %s.',
                $apply ? 'Applied' : 'Dry run',
                $result['rows'],
                $result['pending_values'],
                $apply ? 'encrypted' : 'require encryption'
            ));
            if (!$apply && $result['pending_values'] > 0) {
                $this->warn('Review the count, back up the database, then rerun with --apply.');
            }
        }

        return self::SUCCESS;
    }
}
