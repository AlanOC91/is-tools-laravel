<?php

namespace App\Console\Commands;

use App\Services\DirectoryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class CheckInstallation extends Command
{
    protected $signature = 'is-tools:check {--directory : Verify LDAP transport without binding or sending a password}';

    protected $description = 'Check deployment configuration, database, storage and PDF service without sending emails';

    public function handle(DirectoryService $directory): int
    {
        $failed = false;
        $check = function ($label, $ok) use (&$failed) {
            $this->line(($ok ? 'OK   ' : 'FAIL ').$label);
            if (! $ok) {
                $failed = true;
            }
        };
        $check('Application key configured', (bool) config('app.key'));
        $check('Production debug disabled', ! app()->isProduction() || ! config('app.debug'));
        $check('LDAP extension available', extension_loaded('ldap'));
        $check('Private document storage writable', is_writable(config('is-tools.documents_path')));
        foreach (['support_email', 'ilab_email'] as $setting) {
            $check($setting.' configured', (bool) filter_var(config('is-tools.'.$setting), FILTER_VALIDATE_EMAIL));
        }
        $check('Dedicated directory search credentials configured', (bool) config('directory.search_username') && (bool) config('directory.search_password'));
        try {
            DB::connection()->getPdo();
            $check('Database connection', true);
            foreach (['requests', 'meetings', 'verified_logins', 'is_tools_submissions', 'is_tools_deliveries', 'jobs'] as $table) {
                $check('Table '.$table, Schema::hasTable($table));
            }
        } catch (\Throwable $e) {
            $check('Database connection', false);
            $this->error($e->getMessage());
        }
        try {
            $base = preg_replace('~/forms/chromium/convert/html/?$~', '', config('is-tools.gotenberg_url'));
            $check('Gotenberg health', Http::timeout(5)->get(rtrim($base, '/').'/health')->successful());
        } catch (\Throwable) {
            $check('Gotenberg health', false);
        }
        if ($this->option('directory')) {
            try {
                $directory->verifyTransport();
                $check('LDAP transport and configured certificate trust', true);
            } catch (\Throwable $e) {
                $check('LDAP transport', false);
                $this->error($e->getMessage());
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
