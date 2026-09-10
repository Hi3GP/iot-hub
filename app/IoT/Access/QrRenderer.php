<?php

namespace App\IoT\Access;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;

/**
 * 二维码渲染（服务端 SVG，无外部依赖）
 */
class QrRenderer
{
    public static function svg(string $data, int $size = 240): string
    {
        return (new Builder(
            writer: new SvgWriter(),
            data: $data,
            size: $size,
            margin: 8,
        ))->build()->getString();
    }

    public static function dataUri(string $data, int $size = 240): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode(self::svg($data, $size));
    }
}
