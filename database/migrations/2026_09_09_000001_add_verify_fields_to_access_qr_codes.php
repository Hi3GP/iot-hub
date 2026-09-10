<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('access_qr_codes', function (Blueprint $table) {
            $table->string('verify_mode', 16)->default('none')->comment('验证方式：none不验证/code通行口令/question验证问题')->after('success_message');
            $table->string('verify_code', 64)->nullable()->comment('通行口令')->after('verify_mode');
            $table->string('verify_question', 100)->nullable()->comment('验证问题')->after('verify_code');
            $table->string('verify_answer', 100)->nullable()->comment('正确答案，多个等价答案用 | 分隔')->after('verify_question');
            $table->unsignedSmallInteger('open_cooldown_minutes')->default(5)->comment('同一手机号两次开门最小间隔（分钟），0不限')->after('verify_answer');
            $table->unsignedSmallInteger('daily_open_limit')->default(10)->comment('同一手机号每日开门上限（次），0不限')->after('open_cooldown_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('access_qr_codes', function (Blueprint $table) {
            $table->dropColumn(['verify_mode', 'verify_code', 'verify_question', 'verify_answer', 'open_cooldown_minutes', 'daily_open_limit']);
        });
    }
};
