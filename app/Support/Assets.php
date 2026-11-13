<?php

namespace App\Support;

/* Link to a file in public/assets with the file's last-changed time, so a browser or server cache never keeps an old script or style after an update. */
class Assets
{
    public static function url(string $path): string
    {
        $file = public_path($path);
        return asset($path) . (is_file($file) ? '?v=' . filemtime($file) : '');
    }
}
