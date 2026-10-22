<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('is_tools_deliveries', fn (Blueprint $table) => $table->string('channel')->default('support'));
    }

    public function down(): void {}
};
