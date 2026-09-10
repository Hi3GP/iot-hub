<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 园区空间层级：园区-楼栋-楼层-房间-自定义区域
        Schema::create('spaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('spaces')->nullOnDelete();
            $table->string('name');
            $table->string('type')->default('room')->index(); // park|building|floor|room|zone
            $table->unsignedInteger('sort')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        // 平台连接器实例（每条记录 = 一个第三方平台账号配置）
        Schema::create('connectors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('driver')->index(); // local|ezviz|ctwing|lifesmart|mihome
            // 加密数组（encrypted:array cast）→ 存储为加密字符串，必须用 text，MySQL JSON 列会校验失败
            $table->text('credentials')->nullable(); // 加密存储
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        // 设备产品（型号归类 + 统一物模型定义）
        Schema::create('device_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connector_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_key')->nullable(); // 平台侧产品标识
            $table->string('name');
            $table->string('category')->nullable()->index(); // camera|lock|sensor|switch|meter...
            $table->string('model')->nullable();
            $table->json('thing_model')->nullable(); // 统一物模型：属性/事件/命令定义与平台映射
            $table->timestamps();

            $table->unique(['connector_id', 'external_key']);
        });

        // 统一设备表（全平台设备归一化）
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connector_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('space_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('external_id'); // 平台侧设备唯一标识
            $table->string('external_parent_id')->nullable(); // 网关/父设备
            $table->string('model')->nullable();
            $table->string('category')->nullable()->index();
            $table->string('status')->default('unknown')->index(); // online|offline|unknown
            $table->timestamp('status_updated_at')->nullable();
            $table->string('bound_type')->default('pull')->index(); // pull|manual
            $table->boolean('is_active')->default(true);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['connector_id', 'external_id']);
        });

        // 设备属性最新值（统一物模型属性码）
        Schema::create('device_properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->string('code')->index(); // switch|temperature|humidity|battery|defence...
            $table->text('value')->nullable();
            $table->timestamp('reported_at')->nullable();
            $table->timestamps();

            $table->unique(['device_id', 'code']);
        });

        // 设备属性历史
        Schema::create('device_property_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->text('value')->nullable();
            $table->timestamp('reported_at')->index();
        });

        // 设备事件流（告警事件、联动触发源）
        Schema::create('device_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->string('type')->index(); // motion|door_open|offline|alarm...
            $table->json('data')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
        });

        // 告警中心
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->string('level')->default('info')->index(); // info|warning|critical
            $table->string('type')->index(); // offline|threshold|event|linkage...
            $table->string('title');
            $table->text('content')->nullable();
            $table->string('status')->default('active')->index(); // active|acknowledged|resolved
            $table->timestamp('triggered_at')->index();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        // 联动规则（触发器 → 条件 → 动作）
        Schema::create('linkage_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('enabled')->default(true)->index();
            $table->json('triggers'); // [{type:'property_changed'|'event'|'offline', device_id, code, operator, value}]
            $table->json('conditions')->nullable(); // [{type:'property', device_id, code, operator, value}]
            $table->json('actions'); // [{type:'device_command'|'scene'|'alert', device_id, code, value}]
            $table->unsignedInteger('cooldown_seconds')->default(0);
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();
        });

        // 联动执行日志
        Schema::create('linkage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('linkage_rule_id')->constrained()->cascadeOnDelete();
            $table->string('status'); // success|failed|skipped
            $table->text('message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('linkage_logs');
        Schema::dropIfExists('linkage_rules');
        Schema::dropIfExists('alerts');
        Schema::dropIfExists('device_events');
        Schema::dropIfExists('device_property_histories');
        Schema::dropIfExists('device_properties');
        Schema::dropIfExists('devices');
        Schema::dropIfExists('device_products');
        Schema::dropIfExists('connectors');
        Schema::dropIfExists('spaces');
    }
};
