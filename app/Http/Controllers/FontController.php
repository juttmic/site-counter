<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;

class FontController extends Controller
{
    /**
     * List font filenames from public/assets/fonts directory.
     * Returns TTF, OTF, WOFF, WOFF2 files.
     */
    public function index()
    {
        $fontsDir = public_path('assets/fonts');
        $fonts = [];

        if (File::isDirectory($fontsDir)) {
            $files = File::files($fontsDir);
            $extensions = ['ttf', 'otf', 'woff', 'woff2'];
            foreach ($files as $file) {
                $ext = strtolower($file->getExtension());
                if (in_array($ext, $extensions)) {
                    $fonts[] = $file->getFilename();
                }
            }
            sort($fonts);
        }

        return response()->json(['fonts' => $fonts]);
    }
}
