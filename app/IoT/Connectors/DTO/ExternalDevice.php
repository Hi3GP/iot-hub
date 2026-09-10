<?php

namespace App\IoT\Connectors\DTO;

/**
 * 平台侧设备（拉取归一化后的中间表示）
 */
class ExternalDevice
{
    public function __construct(
        public string $externalId,
        public string $name,
        public ?string $model = null,
        public ?string $category = null,
        public ?string $parentId = null,
        public ?bool $online = null,
        public ?string $externalKey = null, // 平台产品标识
        public array $meta = [],
    ) {
    }

    public static function make(...$args): self
    {
        return new self(...$args);
    }
}
