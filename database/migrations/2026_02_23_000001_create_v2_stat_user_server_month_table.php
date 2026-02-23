<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('v2_stat_user_server_month')) {
            Schema::create('v2_stat_user_server_month', function (Blueprint $table) {
                $table->integer('id', true);
                $table->integer('user_id')->index('user_id');
                $table->integer('server_id')->index('server_id');
                $table->char('server_type', 11)->comment('节点类型');
                $table->bigInteger('u');
                $table->bigInteger('d');
                $table->integer('record_at')->index('record_at')->comment('记录时间（月初）');
                $table->integer('created_at');
                $table->integer('updated_at');

                if (config('database.default') !== 'sqlite') {
                    $table->unique(['user_id', 'server_id', 'record_at'], 'user_id_server_id_record_at');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('v2_stat_user_server_month');
    }
};
