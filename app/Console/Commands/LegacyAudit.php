<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class LegacyAudit extends Command
{
    protected $signature = 'is-tools:legacy-audit {--snapshot= : Save counts and hashes as a JSON snapshot} {--compare= : Compare the database against a previous snapshot}';

    protected $description = 'Read-only audit of all legacy tables, with before/after record integrity comparison';

    private const TABLES = ['data_log', 'departments', 'form_requests', 'ilab_requests', 'login_log', 'meetings', 'meeting_users', 'requests', 'security_groups', 'security_groups_users', 'session_rememberme', 'software', 'verified_logins'];

    public function handle(): int
    {
        $previous = $this->option('compare') ? json_decode(file_get_contents($this->option('compare')), true, 512, JSON_THROW_ON_ERROR) : null;
        $snapshot = [];
        $failed = false;
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                $this->error('Missing legacy table: '.$table);
                $failed = true;

                continue;
            }
            $columns = $previous[$table]['columns'] ?? Schema::getColumnListing($table);
            $context = hash_init('sha256');
            $count = 0;
            foreach (DB::table($table)->select($columns)->orderBy('id')->cursor() as $row) {
                hash_update($context, json_encode((array) $row, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE)."\n");
                $count++;
            }
            $hash = hash_final($context);
            $snapshot[$table] = compact('columns', 'count', 'hash');
            $same = ! $previous || ($previous[$table]['count'] === $count && hash_equals($previous[$table]['hash'], $hash));
            $this->line($table.': '.$count.' rows'.($previous ? ($same ? ' — unchanged' : ' — CHANGED') : ''));
            if (! $same) {
                $failed = true;
            }
        }
        if ($this->option('snapshot')) {
            if (file_exists($this->option('snapshot'))) {
                $this->error('Snapshot already exists; choose a new filename.');

                return self::FAILURE;
            }
            file_put_contents($this->option('snapshot'), json_encode($snapshot, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            $this->info('Integrity snapshot saved (hashes only; no record contents).');
        }
        if (Schema::hasTable('form_requests')) {
            $missing = 0;
            foreach (DB::table('form_requests')->whereNotNull('sigSlug')->cursor() as $sig) {
                if (! Storage::disk('documents')->exists('signatures/'.$sig->type.'/'.$sig->sigSlug.'.png')) {
                    $missing++;
                }
            }
            $this->line('Referenced signature files not present locally: '.$missing);
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
