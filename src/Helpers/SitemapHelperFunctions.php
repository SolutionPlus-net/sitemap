<?php

namespace Otas\Sitemap\Helpers;

class SitemapHelperFunctions
{
    public static function getSitemapFilePath(): string
    {
        $fileName = config('sitemap.subdomain') ? config('sitemap.subdomain') . '-sitemap.xml' : 'sitemap.xml';
        $basePath = config('sitemap.base_path');

        return ($basePath ? $basePath . '/' : '') . 'sitemaps/' . $fileName;
    }

    /**
     * Encode a sitemap URL, preserving path slashes and query string syntax.
     *
     * Everything after the first "?" is treated as a query string, so "?", "&"
     * and "=" keep their meaning while keys and values are encoded.
     */
    public static function encodeUrl(string $url): string
    {
        [$path, $query] = array_pad(explode('?', $url, 2), 2, null);

        $encodedPath = self::encodePath($path);

        $encodedQuery = $query === null ? '' : self::encodeQuery($query);

        return $encodedQuery === '' ? $encodedPath : $encodedPath . '?' . $encodedQuery;
    }

    /**
     * Encode each segment of a path individually while preserving slashes.
     */
    private static function encodePath(string $path): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $path)));
    }

    /**
     * Encode each query parameter key and value while preserving "&" and "=".
     */
    private static function encodeQuery(string $query): string
    {
        $pairs = [];

        foreach (explode('&', $query) as $pair) {
            if ($pair === '') {
                continue;
            }

            [$key, $value] = array_pad(explode('=', $pair, 2), 2, null);

            $encodedKey = rawurlencode($key);

            $pairs[] = $value === null ? $encodedKey : $encodedKey . '=' . rawurlencode($value);
        }

        return implode('&', $pairs);
    }
}
