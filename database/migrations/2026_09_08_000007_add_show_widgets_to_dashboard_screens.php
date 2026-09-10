<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dashboard_screens', function (Blueprint $table) {
            $table->boolean('show_widgets')->default(true)->after('show_alerts')->comment('显示自定义数据卡片');
        });
    }

    public function down(): void
    {
        Schema::table('dashboard_screens', function (Blueprint $table) {
            $table->dropColumn('show_widgets');
        });
    }
};
