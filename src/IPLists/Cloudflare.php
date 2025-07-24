<?php

namespace rafalmasiarek\Http\RealIpResolver\IPLists;

class Cloudflare implements IpListInterface
{
    public static function get(): array
    {
        $file = __DIR__ . '/../../data/cloudflare.txt';
        if (!file_exists($file)) {
            return [];
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        return array_filter(array_map('trim', $lines));
    }
}
