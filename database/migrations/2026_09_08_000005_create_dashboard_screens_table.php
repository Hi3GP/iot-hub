<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_screens', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('大屏名称');
            $table->foreignId('space_id')->nullable()->constrained()->nullOnDelete()->comment('关联园区');
            $table->unsignedSmallInteger('layout')->default(4)->comment('画面数: 4|8|16');
            $table->boolean('carousel_enabled')->default(true)->comment('开启轮播');
            $table->unsignedSmallInteger('carousel_interval')->default(10)->comment('轮播间隔（秒）');
            $table->unsignedSmallInteger('refresh_interval')->default(30)->comment('数据刷新间隔（秒）');
            $table->boolean('show_stats')->default(true)->comment('显示统计卡片');
            $table->boolean('show_categories')->default(true)->comment('显示设备分类');
            $table->boolean('show_cameras')->default(true)->comment('显示监控画面');
            $table->boolean('show_alerts')->default(true)->comment('显示告警列表');
            $table->json('camera_device_ids')->nullable()->comment('指定摄像头（空=全部在线摄像头）');
            $table->boolean('is_default')->default(false)->comment('默认大屏');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_screens');
    }
};
