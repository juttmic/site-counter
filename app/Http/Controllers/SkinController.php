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
                'preview_url' => url('/skin-previews/' . $key . '.png'),
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
        // Only expose editable parts and defaults
        $exposed = [
            'key' => $key,
            'name' => $json['name'] ?? $key,
            'defaults' => $json,
            'fields' => $json['fields'] ?? [],
            'preview_url' => url('/skin-previews/' . $key . '.png'),
        ];
        return response()->json($exposed);
    }
}


