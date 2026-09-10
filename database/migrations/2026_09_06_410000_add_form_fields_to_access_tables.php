<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 登记表单字段自定义：
 * - access_qr_codes.form_fields：JSON 字段定义 [{label,type,enabled,required}]
 * - visitor_registrations.custom_data：JSON 访客填写的自定义字段值 {label: value}
 * 旧数据由 need_id_card/need_company/need_reason 三个开关回填为 form_fields。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('access_qr_codes', function (Blueprint $table) {
            $table->json('form_fields')->nullable()->after('need_reason')->comment('自定义登记表单字段');
        });

        Schema::table('visitor_registrations', function (Blueprint $table) {
            $table->json('custom_data')->nullable()->after('reason')->comment('自定义字段值 {字段名: 值}');
        });

        // 旧开关配置回填为自定义字段（开启状态原样保留）
        DB::table('access_qr_codes')->orderBy('id')->each(function ($qr) {
            $fields = [
                ['label' => '身份证号', 'type' => 'text', 'enabled' => (bool) $qr->need_id_card, 'required' => true],
                ['label' => '来访单位', 'type' => 'text', 'enabled' => (bool) $qr->need_company, 'required' => true],
                ['label' => '来访事由', 'type' => 'textarea', 'enabled' => (bool) $qr->need_reason, 'required' => true],
            ];

            DB::table('access_qr_codes')
                ->where('id', $qr->id)
                ->update(['form_fields' => json_encode($fields, JSON_UNESCAPED_UNICODE)]);
        });
    }

    public function down(): void
    {
        Schema::table('access_qr_codes', function (Blueprint $table) {
            $table->dropColumn('form_fields');
        });

        Schema::table('visitor_registrations', function (Blueprint $table) {
            $table->dropColumn('custom_data');
        });
    }
};
