<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visitor_registrations', function (Blueprint $table) {
            // 实名认证预留字段（后期接入支付宝/微信）
            $table->string('auth_type', 16)->default('none')->comment('认证方式: none/alipay/wechat')->after('result_message');
            $table->string('auth_status', 16)->default('unverified')->comment('认证状态: unverified/verified/failed')->after('auth_type');
            $table->json('auth_data')->nullable()->comment('认证返回数据')->after('auth_status');
        });
    }

    public function down(): void
    {
        Schema::table('visitor_registrations', function (Blueprint $table) {
            $table->dropColumn(['auth_type', 'auth_status', 'auth_data']);
        });
    }
};
