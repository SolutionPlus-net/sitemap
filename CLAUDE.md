# Otas Sitemap — notes for future sessions

Laravel package (`Otas\Sitemap`, PSR-4 → `src/`). No app skeleton; tests run through Orchestra Testbench.

## Running tests

```bash
vendor/bin/phpunit
```

`phpunit.xml` only picks up `tests/Feature/*Test.php`. Tests extend `Otas\Sitemap\Tests\TestCase`; the package reads everything from `config('sitemap.*')` and `config('translatable.locales')`, so set those with `config()->set(...)` inside the test instead of booting an app config file.

`HandleDynamicSitemapHelper::buildDefaultUrls()` never touches the database — it only hands each model to `$slugResolver` — so it can be tested with bare `Model` stubs in an `Eloquent\Collection`.

## URL encoding is centralised

`SitemapHelperFunctions::encodeUrl()` is the single source of truth for turning a raw path/template into a sitemap-safe URL (per-segment `rawurlencode`, slashes preserved, query string after the first `?` preserved with encoded keys/values). Both `HandleStaticSitemapHelper::encodeHref()` and `HandleDynamicSitemapHelper::formatSitemapUrl()` delegate to it — add encoding rules there, not in the callers.

## Known quirks (don't "fix" accidentally)

- `HandleStaticSitemapHelper::buildUrls()` passes `static_links[*]['loc']` through **unencoded** (only `other_locs` and `alternates` are encoded). Changing that would alter output for every existing config, so treat it as a deliberate behaviour change if you ever touch it.
- `HandleDynamicSitemapHelper::formatSitemapUrl()` hardcodes `'ar'` as the unprefixed locale; every other locale gets a `{$locale}/` prefix regardless of `sitemap.default_locale`.
- Placeholder substitution is a plain `str_replace` over the template, so a placeholder value containing `/` or `?` changes the URL structure.
- A `$slugResolver` may return either one replacement map or a **list** of maps (one URL variant per entry); `resolveReplacementSets()` tells them apart with `array_is_list()`, so an empty return means "no extra placeholders", never "no URLs".
