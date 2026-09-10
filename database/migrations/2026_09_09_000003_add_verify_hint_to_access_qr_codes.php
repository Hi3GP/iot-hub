<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('access_qr_codes', function (Blueprint $table) {
            // 访客输入框的自定义提示文案（验证问题时显示在输入框 placeholder）
            $table->string('verify_hint', 150)->nullable()->after('verify_answer');
        });
    }

    public function down(): void
    {
        Schema::table('access_qr_codes', function (Blueprint $table) {
            $table->dropColumn('verify_hint');
        });
    }
};
