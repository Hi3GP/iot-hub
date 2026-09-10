<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 用户角色：admin 管理全部数据，user 仅管理自己的数据
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('user')->after('password');
        });

        // 数据归属：每个用户管理自己的平台连接器 / 空间 / 设备 / 联动规则 / 告警
        Schema::table('connectors', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
        });

        Schema::table('spaces', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('connector_id')->constrained('users')->nullOnDelete();
        });

        Schema::table('linkage_rules', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
        });

        Schema::table('alerts', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
        });

        // 存量数据归属回填
        DB::statement('UPDATE devices SET user_id = (SELECT user_id FROM connectors WHERE connectors.id = devices.connector_id) WHERE user_id IS NULL');
        DB::statement('UPDATE alerts SET user_id = (SELECT user_id FROM devices WHERE devices.id = alerts.device_id) WHERE user_id IS NULL');

        // 确保至少存在一个管理员：将最早的注册用户提升为 admin
        if (! DB::table('users')->where('role', 'admin')->exists()) {
            DB::table('users')->orderBy('id')->limit(1)->update(['role' => 'admin']);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });

        foreach (['alerts', 'linkage_rules', 'devices', 'spaces', 'connectors'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropConstrainedForeignId('user_id');
            });
        }
    }
};
