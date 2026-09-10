<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_qr_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 32)->unique()->comment('扫码 URL 标识');
            $table->string('name');
            $table->foreignId('device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->string('command_code', 64)->default('switch')->comment('统一物模型属性码');
            $table->string('command_value', 64)->default('1')->comment('下发值：1开/0关/自定义');
            $table->boolean('need_id_card')->default(false);
            $table->boolean('need_company')->default(false);
            $table->boolean('need_reason')->default(true);
            $table->string('success_message')->default('登记成功，门已打开，请通行');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('visitor_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('access_qr_code_id')->constrained('access_qr_codes')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete()->comment('登记码归属人');
            $table->foreignId('device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->string('name');
            $table->string('phone', 32);
            $table->string('id_card', 32)->nullable();
            $table->string('company')->nullable();
            $table->string('reason')->nullable();
            $table->string('status', 16)->default('granted')->comment('granted开门成功/failed开门失败');
            $table->text('result_message')->nullable()->comment('设备返回/错误信息');
            $table->timestamp('opened_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();

            $table->index(['access_qr_code_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitor_registrations');
        Schema::dropIfExists('access_qr_codes');
    }
};
