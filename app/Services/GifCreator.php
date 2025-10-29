<?php

namespace App\Services;

// Minimalistic GIF encoder (adapted for simple use-cases)
// Credits: MIT-licensed snippets inspired by Sybio/GifCreator
class GifCreator
{
    private string $gif = "";

    public function create(array $frames, array $durations, int $loops = 0): void
    {
        if (count($frames) !== count($durations)) {
            throw new \InvalidArgumentException('Frames and durations count mismatch');
        }

        $this->gif = 'GIF89a';
        $encodedFrames = [];
        foreach ($frames as $i => $frame) {
            $encodedFrames[] = $this->convertPngToGifFrame($frame, $durations[$i]);
        }

        // Use Netscape loop extension
        $netscapeExt = "\x21\xFF\x0BNETSCAPE2.0\x03\x01" . pack('v', $loops) . "\x00";

        // Basic logical screen descriptor: take from first frame
        $first = $encodedFrames[0];
        $lsd = substr($first, 6, 7);
        $gctFlag = ord($lsd[4]) & 0x80; // check global color table
        if (!$gctFlag) {
            // If first frame has no GCT, prepend a dummy one (black/white)
            $lsd = substr_replace($lsd, chr(ord($lsd[4]) | 0x80), 4, 1);
            $gct = "\x00\x00\x00\xFF\xFF\xFF";
        } else {
            $gctSize = 3 * (2 << (ord($lsd[4]) & 0x07));
            $gct = substr($first, 13, $gctSize);
        }

        $this->gif .= $lsd . ($gct ?? '') . $netscapeExt;

        foreach ($encodedFrames as $idx => $gifFrame) {
            // Extract image descriptor and data from frame
            $imgPos = strpos($gifFrame, "\x2C");
            $imgDescriptor = substr($gifFrame, $imgPos, 10);
            $rest = substr($gifFrame, $imgPos + 10);

            // Graphic Control Extension with delay
            $delayCs = max(1, (int) $durations[$idx]);
            $gce = "\x21\xF9\x04\x08" . pack('v', $delayCs) . "\x00\x00";

            $this->gif .= $gce . $imgDescriptor . $rest;
        }

        $this->gif .= "\x3B"; // GIF trailer
    }

    public function getGif(): string
    {
        return $this->gif;
    }

    private function convertPngToGifFrame(string $pngBlob, int $durationCs): string
    {
        $im = imagecreatefromstring($pngBlob);
        ob_start();
        imagegif($im);
        $gif = ob_get_clean();
        imagedestroy($im);
        return $gif;
    }
}


