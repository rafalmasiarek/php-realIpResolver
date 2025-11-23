<?php

namespace rafalmasiarek\RealIpResolver\IPLists;

class Localhost
{
    public static function get(): array
    {
        return [
            '127.0.0.1',
            '::1',
        ];
    }
}
