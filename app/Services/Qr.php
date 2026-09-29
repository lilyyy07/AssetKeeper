<?php

namespace App\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class Qr
{
    public static function svg(string $value): string
    {
        return (new Writer(new ImageRenderer(new RendererStyle(240), new SvgImageBackEnd)))->writeString($value);
    }
}
