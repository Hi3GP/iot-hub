<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('access_qr_codes', function (Blueprint $table) {
            // 是否开启短信验证码校验手机号（访客需输入短信验证码才能提交）
            $table->boolean('sms_verify')->default(false)->after('verify_answer');
        });
    }

    public function down(): void
    {
        Schema::table('access_qr_codes', function (Blueprint $table) {
            $table->dropColumn('sms_verify');
        });
    }
};
