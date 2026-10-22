<?php

namespace App\Services;

use HTMLPurifier;
use HTMLPurifier_Config;

class SafeHtml
{
    public static function clean(?string $value): string
    {
        $config = HTMLPurifier_Config::createDefault();
        $config->set('HTML.Allowed', 'p,b,strong,em,i,br,ul,ol,li,span[class],a[href]');
        $config->set('Cache.SerializerPath', storage_path('framework/cache'));
        $config->set('URI.AllowedSchemes', ['https' => true, 'http' => true, 'mailto' => true]);

        return (new HTMLPurifier($config))->purify($value ?? '');
    }
}
