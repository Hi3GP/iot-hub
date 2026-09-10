<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('connector_id')->nullable()->constrained()->nullOnDelete();
            $table->string('direction')->index(); // in(上报) | out(下发) | system
            $table->string('type')->index();      // command | data | status | alert | event | sync
            $table->string('title');               // 日志标题
            $table->longText('content')->nullable(); // 详细内容（JSON 或文本）
            $table->string('result')->nullable()->index(); // success | failed | pending
            $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('operator_type')->default('system'); // user | system | device
            $table->string('ip')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->index(['device_id', 'occurred_at']);
            $table->index(['type', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_logs');
    }
};
