<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('access_qr_codes', function (Blueprint $table) {
            // 设备单元支持 JSON 指令值，varchar(64) 容量不足
            // 注意：MySQL 5.7 严格模式下 TEXT 列不能设置默认值，旧行默认值 '1' 已在建表时写入
            $table->text('command_value')->nullable(false)->comment('下发值：按产品物模型（1/0、枚举值、JSON…）')->change();
        });
    }

    public function down(): void
    {
        Schema::table('access_qr_codes', function (Blueprint $table) {
            $table->string('command_value', 64)->default('1')->change();
        });
    }
};
