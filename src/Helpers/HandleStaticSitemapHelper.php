<?php

namespace Otas\Sitemap\Helpers;

use Illuminate\Support\Str;

class HandleStaticSitemapHelper
{
    public static function buildUrls(): array
    {
        $urls = [];

        $staticLinks = config('sitemap.static_links');
        $defaultLocale = config('sitemap.default_locale');

        foreach ($staticLinks as $staticLink) {

            $otherLocs = [];

            foreach ($staticLink['other_locs'] as $otherLoc):
                $otherLocs[] = self::encodeHref($otherLoc);
            endforeach;

            $alternates = [];
            $defaultHref = null;
            $hasXDefault = false;

            foreach ($staticLink['alternates'] as $alternate):

                if ($alternate['hreflang'] === 'x-default') {
                    $hasXDefault = true;
                }

                if (Str::contains($alternate['href'], "{$alternate['hreflang']}/")) {
                    $href = str_replace("{$alternate['hreflang']}/", '', $alternate['href']);
                    $href = $alternate['hreflang'] . '/' . self::encodeHref($href);
                } else {
                    $href = self::encodeHref($alternate['href']);
                }

                $alternates[] = [
                    'hreflang' => $alternate['hreflang'],
                    'href' => $href,
                ];

                if ($alternate['hreflang'] === $defaultLocale) {
                    $defaultHref = $href;
                }

            endforeach;

            if (!$hasXDefault && $defaultHref !== null) {
                $alternates[] = [
                    'hreflang' => 'x-default',
                    'href' => $defaultHref,
                ];
            }

            $urls[] = [
                'loc' => $staticLink['loc'],
                'other_locs' => $otherLocs,
                'alternates' => $alternates,
                'priority' => $staticLink['priority'],
            ];

        }

        return $urls;
    }

    private static function encodeHref(string $href): string
    {
        return SitemapHelperFunctions::encodeUrl($href);
    }
}
