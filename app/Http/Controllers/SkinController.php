<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use App\Services\TimerGifGenerator;
use App\Services\SkinFileHelper;

class SkinController extends Controller
{
    public function index()
    {
        $skins = [];
        foreach (SkinFileHelper::listFiles() as $jsonPath) {
            $key = pathinfo($jsonPath, PATHINFO_FILENAME);
            $json = SkinFileHelper::load($jsonPath);
            if ($json === null) {
                continue;
            }
            $skins[] = [
                'key' => $key,
                'name' => $json['name'] ?? $key,
                'preview_url' => $this->skinPreviewUrl($key),
            ];
        }
        return response()->json($skins);
    }

    public function show(string $key)
    {
        $path = SkinFileHelper::resolvePath($key);
        if ($path === null) {
            return response()->json(['message' => 'Skin not found'], 404);
        }
        $json = SkinFileHelper::load($path);
        if ($json === null) {
            return response()->json(['message' => 'Skin not found'], 404);
        }
        // Default border to 1 only when the skin file omits it; 0 means no border
        if (!array_key_exists('borderWidth', $json)) {
            $json['borderWidth'] = 1;
        }
        if (!array_key_exists('borderColor', $json) || empty($json['borderColor'])) {
            $json['borderColor'] = '#FFFFFF';
        }
        
        $fields = $json['fields'] ?? $this->inferFieldsFromSkin($json);
        // Ensure new optional fields (e.g., separatorColor) are exposed when present but missing in provided fields
        $fields = $this->ensureExtraFields($json, $fields);
        // Only expose editable parts and defaults (without formDefaults applied)
        $exposed = [
            'key' => $key,
            'name' => $json['name'] ?? $key,
            'defaults' => $json,
            'fields' => $fields,
            'preview_url' => $this->skinPreviewUrl($key),
        ];
        return response()->json($exposed);
    }

    /**
     * Get form defaults for a skin (formDefaults object)
     * This is called when a skin is first loaded on a new timer
     */
    public function getFormDefaults(string $key)
    {
        $path = SkinFileHelper::resolvePath($key);
        if ($path === null) {
            return response()->json(['message' => 'Skin not found'], 404);
        }
        $json = SkinFileHelper::load($path);
        if ($json === null) {
            return response()->json(['message' => 'Skin not found'], 404);
        }
        $formDefaults = [];
        
        // Extract formDefaults object if it exists
        if (isset($json['formDefaults']) && is_array($json['formDefaults'])) {
            $formDefaults = $json['formDefaults'];
        }
        
        // Also check for individual form* fields
        foreach ($json as $keyName => $value) {
            if (strpos($keyName, 'form') === 0 && $keyName !== 'formDefaults') {
                // Remove "form" prefix and add to formDefaults
                $targetKey = lcfirst(substr($keyName, 4)); // Remove "form" prefix
                if ($targetKey) {
                    $formDefaults[$targetKey] = $value;
                }
            }
        }
        
        return response()->json([
            'key' => $key,
            'formDefaults' => $formDefaults,
        ]);
    }

    public function gallery()
    {
        $dir = public_path('skin-previews');
        $files = collect(glob($dir . DIRECTORY_SEPARATOR . '*.gif'));
        
        $items = $files->map(function ($path) {
            $file = pathinfo($path, PATHINFO_BASENAME);
            $key = pathinfo($path, PATHINFO_FILENAME);
            if (!SkinFileHelper::exists($key)) {
                return null;
            }
            return [
                'key' => $key,
                'url' => $this->skinPreviewUrl($key),
            ];
        })->filter();
        
        return view('skins.gallery', ['items' => $items]);
    }

    public function regenerate(string $key, TimerGifGenerator $generator)
    {
        if (!SkinFileHelper::exists($key)) {
            return response()->json(['message' => 'Skin not found'], 404);
        }
        try {
            $outDir = public_path('skin-previews');
            File::ensureDirectoryExists($outDir);
            $outPath = $outDir . DIRECTORY_SEPARATOR . $key . '.gif';
            // 90650s sample time, SAMPLE_SKIN_GIF_FRAME_COUNT frames (55, not 60)
            $generator->renderSkinGifPreview($key, $outPath, TimerGifGenerator::DEFAULT_PREVIEW_SECONDS, null, 'none', null, [], 30);
            return response()->json(['ok' => true, 'url' => $this->skinPreviewUrl($key)]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'Failed to regenerate: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Public URL for a skin template preview GIF (?v= file mtime for cache busting).
     */
    private function skinPreviewUrl(string $key): string
    {
        $base = url('/skin-previews/' . $key . '.gif');
        $path = public_path('skin-previews/' . $key . '.gif');
        $v = is_file($path) ? (string) filemtime($path) : '0';

        return $base . '?v=' . $v;
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
        $add('background', 'Background', 'color');
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
        // Custom text spacing from timer
        if (!isset($have['customTextDistance'])) { $fields[] = ['key' => 'customTextDistance', 'label' => 'Distance from Counter', 'type' => 'number', 'min' => -400, 'max' => 400, 'step' => 1]; }
        // Output canvas width/height (overall GIF)
        if (!isset($have['outputWidth']))  { $fields[] = ['key' => 'outputWidth',  'label' => 'Output Width (GIF)',  'type' => 'number', 'min' => 50, 'max' => 3000, 'step' => 1]; }
        if (!isset($have['outputHeight'])) { $fields[] = ['key' => 'outputHeight', 'label' => 'Output Height (GIF)', 'type' => 'number', 'min' => 50, 'max' => 3000, 'step' => 1]; }
        // Optional: timer-only inner baseline for heading stack math (see TimerGifGenerator::defaultInnerHeightForHeadingLayout)
        $style = (string) ($skin['style'] ?? '');
        $supportsBaseline = $style !== 'hanging_ribbon_blocks';
        if ($supportsBaseline && !isset($have['defaultOutputHeight'])) {
            $fields[] = ['key' => 'defaultOutputHeight', 'label' => 'Default output height (timer baseline)', 'type' => 'number', 'min' => 50, 'max' => 3000, 'step' => 1];
        }
        // Final message and colors
        if (!isset($have['finalMessage']))     { $fields[] = ['key' => 'finalMessage',     'label' => "Final Message",      'type' => 'text',   'maxLength' => 64]; }
        if (!isset($have['finalBackground']))  { $fields[] = ['key' => 'finalBackground',  'label' => 'Final Background',  'type' => 'color']; }
        if (!isset($have['finalTextColor']))   { $fields[] = ['key' => 'finalTextColor',   'label' => 'Final Text Color',   'type' => 'color']; }
        // Border customization
        if (!isset($have['borderWidth']))      { $fields[] = ['key' => 'borderWidth',      'label' => 'Border Width',      'type' => 'number', 'min' => 0, 'max' => 50, 'step' => 1]; }
        if (!isset($have['borderColor']))      { $fields[] = ['key' => 'borderColor',      'label' => 'Border Color',      'type' => 'color']; }
        return $fields;
    }
}
