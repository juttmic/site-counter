<?php

namespace App\Services\Skins;

interface SkinRendererInterface
{
    /**
     * Render style-specific background/decorations into the existing GD image resource.
     * Implementations should NOT render the time text unless they also override renderTimeText.
     */
    public function renderStyle($im, array $skin, int $width, int $height, int $bgCol, int $textCol, int $accentCol, int $secondsRemaining): void;

    /**
     * Optionally render the time text. Return true if handled; false to let default time renderer run.
     */
    public function renderTimeText($im, int $secondsRemaining, array $skin, int $width, int $height, int $textCol, int $accentCol): bool;
}


