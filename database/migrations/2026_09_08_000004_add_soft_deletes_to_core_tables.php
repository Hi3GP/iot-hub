<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'devices',
            'device_products',
            'linkage_rules',
            'access_qr_codes',
            'visitor_registrations',
            'spaces',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->softDeletes()->after('updated_at');
                });
            }
        }
    }

    public function down(): void
    {
        $tables = [
            'devices',
            'device_products',
            'linkage_rules',
            'access_qr_codes',
            'visitor_registrations',
            'spaces',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropSoftDeletes();
                });
            }
        }
    }
};
