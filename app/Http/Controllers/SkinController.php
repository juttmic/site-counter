<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

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
        $add('fontSize', 'Font Size', 'number', ['min' => 8, 'max' => 200, 'step' => 1]);
        $add('fontPath', 'Font', 'font');
        $add('showDays', 'Show Days', 'boolean');
        $add('showHours', 'Show Hours', 'boolean');
        $add('showMinutes', 'Show Minutes', 'boolean');
        $add('showSeconds', 'Show Seconds', 'boolean');
        $add('separator', 'Separator', 'text', ['maxLength' => 5]);
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
}


