<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use App\Services\TimerGifGenerator;

class SkinController extends Controller
{
    public function index()
    {
        $dir = resource_path('timerskins');
        $skins = [];
        foreach (File::files($dir) as $file) {
            if (strtolower($file->getExtension()) !== 'json') continue;
            $key = pathinfo($file->getFilename(), PATHINFO_FILENAME);
            $json = json_decode(File::get($file->getPathname()), true) ?: [];
            $skins[] = [
                'key' => $key,
                'name' => $json['name'] ?? $key,
                'preview_url' => url('/skin-previews/' . $key . '.gif'),
            ];
        }
        return response()->json($skins);
    }

    public function show(string $key)
    {
        $path = resource_path('timerskins/' . $key . '.json');
        if (!File::exists($path)) {
            return response()->json(['message' => 'Skin not found'], 404);
        }
        $json = json_decode(File::get($path), true) ?: [];
        $fields = $json['fields'] ?? $this->inferFieldsFromSkin($json);
        // Ensure new optional fields (e.g., separatorColor) are exposed when present but missing in provided fields
        $fields = $this->ensureExtraFields($json, $fields);
        // Only expose editable parts and defaults
        $exposed = [
            'key' => $key,
            'name' => $json['name'] ?? $key,
            'defaults' => $json,
            'fields' => $fields,
            'preview_url' => url('/skin-previews/' . $key . '.gif'),
        ];
        return response()->json($exposed);
    }

    public function gallery()
    {
        $dir = public_path('skin-previews');
        $files = collect(glob($dir . DIRECTORY_SEPARATOR . '*.gif'));
        $items = $files->map(function ($path) {
            $file = pathinfo($path, PATHINFO_BASENAME);
            $key = pathinfo($path, PATHINFO_FILENAME);
            return [
                'key' => $key,
                'url' => url('skin-previews/' . $file),
            ];
        });
        return view('skins.gallery', ['items' => $items]);
    }

    public function regenerate(string $key, TimerGifGenerator $generator)
    {
        $jsonPath = resource_path('timerskins/' . $key . '.json');
        if (!File::exists($jsonPath)) {
            return response()->json(['message' => 'Skin not found'], 404);
        }
        try {
            $outDir = public_path('skin-previews');
            File::ensureDirectoryExists($outDir);
            $outPath = $outDir . DIRECTORY_SEPARATOR . $key . '.gif';
            // Force 30-second preview regeneration
            $generator->renderSkinGifPreview($key, $outPath, 30);
            $url = url('/skin-previews/' . $key . '.gif') . '?v=' . time();
            return response()->json(['ok' => true, 'url' => $url]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'Failed to regenerate: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Generate a reasonable default list of editable fields based on common skin keys.
     */
    private function inferFieldsFromSkin(array $skin): array
    {
        $fields = [];

        $add = function(string $key, string $label, string $type, array $extra = []) use (&$fields, $skin) {
            if (array_key_exists($key, $skin)) {
                $fields[] = array_merge(['key' => $key, 'label' => $label, 'type' => $type], $extra);
            }
        };

        $add('width', 'Width', 'number', ['min' => 100, 'max' => 2000, 'step' => 10]);
        $add('height', 'Height', 'number', ['min' => 50, 'max' => 1000, 'step' => 5]);
        $add('background', 'Background Color', 'color');
        $add('textColor', 'Text Color', 'color');
        $add('accentColor', 'Accent Color', 'color');
        $add('separatorColor', 'Separator Color', 'color');
        $add('fontSize', 'Font Size', 'number', ['min' => 8, 'max' => 200, 'step' => 1]);
        $add('fontPath', 'Font', 'font');
        $add('showDays', 'Show Days', 'boolean');
        $add('showHours', 'Show Hours', 'boolean');
        $add('showMinutes', 'Show Minutes', 'boolean');
        $add('showSeconds', 'Show Seconds', 'boolean');
        $add('separator', 'Separator', 'text', ['maxLength' => 5]);
        // Optional fields we want to expose when present
        if (!empty($skin['layout'])) {
            $options = is_array($skin['layout']) ? $skin['layout'] : ['horizontal','vertical'];
            $fields[] = ['key' => 'layout', 'label' => 'Layout', 'type' => 'select', 'options' => $options];
        }
        if (!empty($skin['animation'])) {
            $options = is_array($skin['animation']) ? $skin['animation'] : ['none','fade','slide'];
            $fields[] = ['key' => 'animation', 'label' => 'Animation', 'type' => 'select', 'options' => $options];
        }

        return $fields;
    }

    private function ensureExtraFields(array $skin, array $fields): array
    {
        $have = [];
        foreach ($fields as $f) {
            if (isset($f['key'])) { $have[$f['key']] = true; }
        }
        if (array_key_exists('separatorColor', $skin) && !isset($have['separatorColor'])) {
            $fields[] = ['key' => 'separatorColor', 'label' => 'Separator Color', 'type' => 'color'];
        }
        // Always allow a custom text color control, even if not present in defaults
        if (!isset($have['customTextColor'])) {
            $fields[] = ['key' => 'customTextColor', 'label' => 'Custom Text Color', 'type' => 'color'];
        }
        // Custom text margins (top/bottom only)
        if (!isset($have['customTextMarginTop']))    { $fields[] = ['key' => 'customTextMarginTop',    'label' => 'Text Margin Top',    'type' => 'number', 'min' => 0, 'max' => 200, 'step' => 1]; }
        if (!isset($have['customTextMarginBottom'])) { $fields[] = ['key' => 'customTextMarginBottom', 'label' => 'Text Margin Bottom', 'type' => 'number', 'min' => 0, 'max' => 200, 'step' => 1]; }
        // Output canvas width/height (overall GIF)
        if (!isset($have['outputWidth']))  { $fields[] = ['key' => 'outputWidth',  'label' => 'Output Width (GIF)',  'type' => 'number', 'min' => 50, 'max' => 3000, 'step' => 1]; }
        if (!isset($have['outputHeight'])) { $fields[] = ['key' => 'outputHeight', 'label' => 'Output Height (GIF)', 'type' => 'number', 'min' => 50, 'max' => 3000, 'step' => 1]; }
        // Final message and colors
        if (!isset($have['finalMessage']))     { $fields[] = ['key' => 'finalMessage',     'label' => "Final Message",      'type' => 'text',   'maxLength' => 64]; }
        if (!isset($have['finalBackground']))  { $fields[] = ['key' => 'finalBackground',  'label' => 'Final Background',  'type' => 'color']; }
        if (!isset($have['finalTextColor']))   { $fields[] = ['key' => 'finalTextColor',   'label' => 'Final Text Color',   'type' => 'color']; }
        // Border customization
        if (!isset($have['borderWidth']))      { $fields[] = ['key' => 'borderWidth',      'label' => 'Border Width',      'type' => 'number', 'min' => 0, 'max' => 50, 'step' => 1]; }
        if (!isset($have['borderColor']))      { $fields[] = ['key' => 'borderColor',      'label' => 'Border Color',      'type' => 'color']; }
        // Optional emoji font to improve emoji rendering in EmojiBadge skin
        if (!isset($have['emojiFontPath']))    { $fields[] = ['key' => 'emojiFontPath',    'label' => 'Emoji Font (optional)', 'type' => 'font']; }
        return $fields;
    }
}


