<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('verified_logins', 'remember_token')) {
            Schema::table('verified_logins', fn (Blueprint $t) => $t->rememberToken());
        }
        if (! Schema::hasColumn('verified_logins', 'display_name')) {
            Schema::table('verified_logins', fn (Blueprint $t) => $t->string('display_name')->nullable());
        }
        Schema::create('is_tools_submissions', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('request_id')->unique();
            $t->string('form');
            $t->json('payload');
            $t->string('signature_path')->nullable();
            $t->string('pdf_path')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });
        Schema::create('is_tools_deliveries', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('request_id')->index();
            $t->string('recipient');
            $t->string('cc')->nullable();
            $t->string('bcc')->nullable();
            $t->string('subject');
            $t->string('state')->default('pending')->index();
            $t->unsignedInteger('attempts')->default(0);
            $t->timestamp('sent_at')->nullable();
            $t->text('last_error')->nullable();
            $t->timestamps();
        });
        Schema::create('is_tools_audit', function (Blueprint $t) {
            $t->id();
            $t->string('actor')->nullable();
            $t->string('action');
            $t->string('subject')->nullable();
            $t->json('details')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });
        Schema::create('jobs', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('queue')->index();
            $t->longText('payload');
            $t->unsignedTinyInteger('attempts');
            $t->unsignedInteger('reserved_at')->nullable();
            $t->unsignedInteger('available_at');
            $t->unsignedInteger('created_at');
        });
        Schema::create('failed_jobs', function (Blueprint $t) {
            $t->id();
            $t->string('uuid')->unique();
            $t->text('connection');
            $t->text('queue');
            $t->longText('payload');
            $t->longText('exception');
            $t->timestamp('failed_at')->useCurrent();
        });
    }

    public function down(): void
    {
        // Preserve operational history and tokens during a code rollback.
    }
};
