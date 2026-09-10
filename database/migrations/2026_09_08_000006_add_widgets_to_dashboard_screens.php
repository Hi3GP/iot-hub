<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dashboard_screens', function (Blueprint $table) {
            $table->json('widgets')->nullable()->comment('自定义数据卡片')
                ->after('camera_device_ids');
        });
    }

    public function down(): void
    {
        Schema::table('dashboard_screens', function (Blueprint $table) {
            $table->dropColumn('widgets');
        });
    }
};
