<?php

namespace Otas\Sitemap\Helpers;

use Illuminate\Database\Eloquent\Collection;

class HandleDynamicSitemapHelper
{
    /**
     * Build sitemap URLs for models with translated slugs (e.g. blogs).
     *
     * The :modelIdentifier placeholder is automatically resolved to the translated
     * model identifier for each locale. Any additional placeholders can be resolved via $slugResolver.
     *
     * $slugResolver may return a single [':placeholder' => 'value'] map, or a list of maps
     * to emit several URL variants per model item (e.g. one per target country).
     *
     */
    public static function buildLocalizedUrls(
        Collection $modelItems,
        array $translatedSegments,
        ?callable $slugResolver = null,
        string $routeKeyName = 'slug',
        float $priority = 0.8
    ): array {
        $urls = [];
        $defaultLocale = config('sitemap.default_locale');
        $locales = config('translatable.locales');

        foreach ($modelItems as $modelItem) {
            $translations = $modelItem->translations()->pluck($routeKeyName, 'locale')->toArray();

            // Ensure default locale exists in the map
            if (!isset($translations[$defaultLocale])) {
                $translations[$defaultLocale] = $modelItem->$routeKeyName;
            }

            // Resolve extra placeholders once per model item, as one or more URL variants
            $replacementSets = self::resolveReplacementSets($slugResolver ? $slugResolver($modelItem) : []);

            foreach ($replacementSets as $extraReplacements) {

                // Build one entry for each available locale translation
                foreach ($translations as $locale => $slug) {
                    $alternates = [];

                    foreach ($locales as $altLocale) {
                        $altSlug = $translations[$altLocale] ?? $translations[$defaultLocale];
                        $altTemplate = $translatedSegments[$altLocale] ?? $translatedSegments[$defaultLocale];

                        $alternates[] = [
                            'hreflang' => $altLocale,
                            'href' => self::formatSitemapUrl(
                                locale: $altLocale,
                                path: self::resolvePlaceholders(
                                    template: $altTemplate,
                                    replacements: array_merge([':modelIdentifier' => $altSlug], $extraReplacements)
                                ),
                            ),
                        ];
                    }

                    // Add x-default
                    $defaultTemplate = $translatedSegments[$defaultLocale];

                    $alternates[] = [
                        'hreflang' => 'x-default',
                        'href' => self::formatSitemapUrl(
                            locale: $defaultLocale,
                            path: self::resolvePlaceholders(
                                template: $defaultTemplate,
                                replacements: array_merge([':modelIdentifier' => $translations[$defaultLocale]], $extraReplacements)
                            ),
                        ),
                    ];

                    $template = $translatedSegments[$locale] ?? $translatedSegments[$defaultLocale];

                    $urls[] = [
                        'loc' => self::formatSitemapUrl(
                            locale: $locale,
                            path: self::resolvePlaceholders(
                                template: $template,
                                replacements: array_merge([':modelIdentifier' => $slug], $extraReplacements)
                            ),
                        ),
                        'other_locs' => [],
                        'alternates' => $alternates,
                        'priority' => $priority,
                    ];
                }
            }
        }

        return $urls;
    }

    /**
     * Build sitemap URLs for models with non-translated slugs (e.g. offers).
     *
     * ALL placeholders (including :modelIdentifier if used) must be provided via the $slugResolver callback.
     *
     * $slugResolver may return a single [':placeholder' => 'value'] map, or a list of maps
     * to emit several URL variants per model item (e.g. one per target country).
     *
     */
    public static function buildDefaultUrls(
        Collection $modelItems,
        array $translatedSegments,
        callable $slugResolver,
        float $priority = 0.8
    ): array {
        $urls = [];
        $defaultLocale = config('sitemap.default_locale');
        $locales = config('translatable.locales');

        foreach ($modelItems as $modelItem) {
            // Resolve all placeholders once per model item, as one or more URL variants
            $replacementSets = self::resolveReplacementSets($slugResolver($modelItem));

            foreach ($replacementSets as $replacements) {

                foreach ($locales as $locale) {
                    $template = $translatedSegments[$locale] ?? $translatedSegments[$defaultLocale];

                    $alternates = [];

                    foreach ($locales as $altLocale) {
                        $altTemplate = $translatedSegments[$altLocale] ?? $translatedSegments[$defaultLocale];

                        $alternates[] = [
                            'hreflang' => $altLocale,
                            'href' => self::formatSitemapUrl(
                                locale: $altLocale,
                                path: self::resolvePlaceholders(template: $altTemplate, replacements: $replacements),
                            ),
                        ];
                    }

                    // Add x-default
                    $defaultTemplate = $translatedSegments[$defaultLocale];

                    $alternates[] = [
                        'hreflang' => 'x-default',
                        'href' => self::formatSitemapUrl(
                            locale: $defaultLocale,
                            path: self::resolvePlaceholders(template: $defaultTemplate, replacements: $replacements),
                        ),
                    ];

                    $urls[] = [
                        'loc' => self::formatSitemapUrl(
                            locale: $locale,
                            path: self::resolvePlaceholders(template: $template, replacements: $replacements),
                        ),
                        'other_locs' => [],
                        'alternates' => $alternates,
                        'priority' => $priority,
                    ];
                }
            }
        }

        return $urls;
    }

    /**
     * Normalize a slug resolver result into a list of replacement maps.
     *
     * A single [':placeholder' => 'value'] map yields one URL per locale, while a list
     * of such maps yields one URL per locale per entry (e.g. one per target country).
     */
    private static function resolveReplacementSets(array $resolved): array
    {
        if ($resolved === [] || !array_is_list($resolved)) {
            return [$resolved];
        }

        return array_filter($resolved, fn($replacements) => is_array($replacements));
    }

    /**
     * Replace all placeholders in the path template.
     */
    private static function resolvePlaceholders(string $template, array $replacements): string
    {
        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    /**
     * Format a sitemap URL path with proper encoding and locale prefix.
     *
     * The path may carry a query string (e.g. "blogs/:modelIdentifier?target_country=eg").
     */
    private static function formatSitemapUrl(string $locale, string $path): string
    {
        $encodedPath = SitemapHelperFunctions::encodeUrl($path);

        $isArabic = $locale === 'ar';

        return $isArabic ? $encodedPath : "{$locale}/{$encodedPath}";
    }
}
