<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Adopt existing tables in place, without rebuilding or deleting records.
        $this->create('verified_logins', function (Blueprint $t) {
            $t->increments('id');
            $t->string('username', 100)->unique();
            $t->string('badge_id')->unique();
            $t->string('email')->nullable();
        });
        foreach (['departments', 'software', 'security_groups'] as $table) {
            $this->create($table, function (Blueprint $t) {
                $t->increments('id');
                $t->string('name')->unique();
            });
        }
        $this->create('security_groups_users', function (Blueprint $t) {
            $t->increments('id');
            $t->string('user_id');
            $t->unsignedInteger('group_id');
            $t->unique(['user_id', 'group_id']);
        });
        $this->create('requests', function (Blueprint $t) {
            $t->increments('id');
            $t->dateTime('submitted_date')->nullable()->useCurrent();
            $t->longText('message');
            $t->string('state')->nullable()->default('incomplete');
            $t->string('type');
            $t->string('requested_by');
            $t->string('approved_by')->nullable();
            $t->dateTime('completed_date')->nullable();
            $t->string('notes')->nullable();
        });
        $this->create('form_requests', function (Blueprint $t) {
            $t->increments('id');
            $t->string('type', 50);
            $t->unsignedInteger('request_id');
            $t->string('sigSlug')->nullable();
        });
        $this->create('ilab_requests', function (Blueprint $t) {
            $t->increments('id');
            $t->string('forename');
            $t->string('surname');
            $t->dateTime('date_sent')->nullable()->useCurrent();
        });
        $this->create('meetings', function (Blueprint $t) {
            $t->increments('id');
            $t->string('presenter_name');
            $t->string('presenter_name_2')->nullable();
            $t->string('presenting_topic');
            $t->string('meeting_name')->nullable();
            $t->dateTime('created_at')->nullable()->useCurrent();
        });
        $this->create('meeting_users', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('meeting_id');
            $t->string('user_name');
        });
        $this->create('login_log', function (Blueprint $t) {
            $t->increments('id');
            $t->string('badge_id');
            $t->dateTime('time')->nullable()->useCurrent();
        });
        $this->create('data_log', function (Blueprint $t) {
            $t->increments('id');
            $t->string('badge_id');
            $t->string('table_name', 50);
            $t->string('action', 20);
            $t->text('old_value')->nullable();
            $t->text('new_value')->nullable();
            $t->timestamp('change_timestamp')->useCurrent();
        });
        $this->create('session_rememberme', function (Blueprint $t) {
            $t->increments('id');
            $t->string('badge_id');
            $t->string('rememberme', 128)->unique();
            $t->dateTime('expires_at');
            $t->timestamp('created_at')->useCurrent();
        });
    }

    private function create(string $name, Closure $definition): void
    {
        if (! Schema::hasTable($name)) {
            Schema::create($name, $definition);
        }
    }

    public function down(): void
    {
        // Code rollback must never delete legacy records.
    }
};
