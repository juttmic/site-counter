<?php

namespace App\Services\Skins;

use Illuminate\Support\Str;

class SkinRendererFactory
{
    public static function resolve(?string $style): ?SkinRendererInterface
    {
        if (!$style) return null;
        // Convert style (e.g., clean_white_timer) to class name (CleanWhiteTimerRenderer)
        $class = 'App\\Services\\Skins\\Renderers\\' . Str::of($style)->studly() . 'Renderer';
        if (class_exists($class)) {
            $instance = app($class);
            return $instance instanceof SkinRendererInterface ? $instance : null;
        }
        return null;
    }
}


