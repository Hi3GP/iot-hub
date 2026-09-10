<?php

namespace Database\Seeders;

use App\Models\Connector;
use App\Models\Space;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 本地设备连接器（园区自有设备手动登记入口）
        Connector::firstOrCreate(
            ['driver' => 'local', 'name' => '本地设备'],
            ['is_active' => true]
        );

        // 园区空间层级示例
        if (! Space::exists()) {
            $park = Space::create(['name' => '示例园区', 'type' => 'park']);
            $building = Space::create(['name' => 'A 栋', 'type' => 'building', 'parent_id' => $park->id]);
            $floor = Space::create(['name' => '1 层', 'type' => 'floor', 'parent_id' => $building->id]);
            Space::create(['name' => '大厅', 'type' => 'room', 'parent_id' => $floor->id]);
        }
    }
}
