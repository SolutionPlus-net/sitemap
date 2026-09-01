# Otas Sitemap

A Laravel package to generate multilingual XML sitemaps with support for static pages, dynamic model-based URLs, translated slugs, and multi-segment path templates.

---

## Table of Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Static Links](#static-links)
- [Dynamic Links](#dynamic-links)
  - [The SitemapContract](#the-sitemapcontract)
  - [Non-Translated Slugs (buildDefaultUrls)](#non-translated-slugs-builddefaulturls)
  - [Translated Slugs (buildLocalizedUrls)](#translated-slugs-buildlocalizedurls)
  - [Multi-Segment URLs](#multi-segment-urls)
  - [Path Template Placeholders](#path-template-placeholders)
  - [Query Parameters](#query-parameters)
  - [Multiple URL Variants per Model](#multiple-url-variants-per-model)
- [Generating the Sitemap](#generating-the-sitemap)
- [Serving the Sitemap](#serving-the-sitemap)
- [Artisan Commands](#artisan-commands)
- [Full Examples](#full-examples)
- [Generated XML Output](#generated-xml-output)
- [License](#license)

---

## Requirements

- PHP 8.1+
- Laravel 9.0+

---

## Installation

```bash
composer require otas/sitemap
```

Run the setup command to publish the configuration file:

```bash
php artisan setup:sitemap
```

Or publish the config manually:

```bash
php artisan vendor:publish --provider="Otas\Sitemap\SitemapServiceProvider" --tag="sitemap-config"
```

---

## Configuration

After publishing, the configuration file is located at `config/sitemap.php`.

```php
return [
    // Base URL for all sitemap links (falls back to app.url if null)
    'website_url' => null,

    // Subdomain prefix (e.g. 'www', 'shop')
    'subdomain' => null,

    // Optional base path for multi-tenant sitemap file storage
    'base_path' => null,

    // Default locale — used for x-default hreflang and locale prefixing
    'default_locale' => 'en',

    // Static pages (see Static Links section)
    'static_links' => [],

    // Model classes implementing SitemapContract (see Dynamic Links section)
    'dynamic_links' => [],

    // Whether to load the package's built-in /sitemap route
    'load_routes' => true,

    // Route prefix for the sitemap endpoint
    'package_routes_prefix' => 'api',

    // Subdirectory for published route files
    'routes_publish_subdirectory' => '',
];
```

> **Note:** The package also reads `config('translatable.locales')` to determine which locales to generate URLs for. Make sure this is configured in your application (e.g. via the `astrotomic/laravel-translatable` package).

---

## Static Links

Define static pages directly in the config file. Each entry specifies its location, alternate locale variants, and priority.

```php
'static_links' => [
    // Homepage
    [
        'loc' => '',
        'other_locs' => ['ar'],
        'alternates' => [
            ['hreflang' => 'en', 'href' => ''],
            ['hreflang' => 'ar', 'href' => 'ar'],
            ['hreflang' => 'x-default', 'href' => ''],
        ],
        'priority' => '1.0',
    ],

    // About page
    [
        'loc' => 'en/about',
        'other_locs' => [],
        'alternates' => [
            ['hreflang' => 'en', 'href' => 'en/about'],
            ['hreflang' => 'ar', 'href' => 'عن-الشركة'],
        ],
        'priority' => '0.8',
    ],

    // Contact page
    [
        'loc' => 'en/contact',
        'other_locs' => [],
        'alternates' => [
            ['hreflang' => 'en', 'href' => 'en/contact'],
            ['hreflang' => 'ar', 'href' => 'اتصل-بنا'],
        ],
        'priority' => '0.7',
    ],
],
```

---

## Dynamic Links

Dynamic links are generated from Eloquent model records. Register your models in the config:

```php
'dynamic_links' => [
    App\Models\Offer::class,
    App\Models\Blog::class,
    App\Models\Product::class,
],
```

### The SitemapContract

Every model must implement `Otas\Sitemap\Contracts\SitemapContract`:

```php
use Otas\Sitemap\Contracts\SitemapContract;

class Offer extends Model implements SitemapContract
{
    public static function sitemapTranslatedSegments(): array
    {
        // Path template per locale with :placeholders
    }

    public static function buildSitemapUrls(): array
    {
        // Build and return the URL array
    }
}
```

### Path Template Placeholders

The `sitemapTranslatedSegments()` method returns a path **template** per locale. Templates use `:placeholder` syntax for dynamic parts:

```php
public static function sitemapTranslatedSegments(): array
{
    return [
        'en' => 'offers/:modelIdentifier',
        'ar' => ':modelIdentifier/العروض',
    ];
}
```

**Reserved placeholder:**

| Placeholder | Resolved by |
|---|---|
| `:modelIdentifier` | Automatically resolved from the translations table (`buildLocalizedUrls` only). For `buildDefaultUrls`, you must provide it via `slugResolver`. |

**Custom placeholders** (`:categorySlug`, `:brandSlug`, etc.) are resolved via the `$slugResolver` callback:

```php
slugResolver: fn($modelItem) => [
    ':modelIdentifier' => $modelItem->slug,
    ':categorySlug' => $modelItem->category->slug,
]
```

The `$slugResolver` receives only the model item and returns a flat `[':placeholder' => 'value']` map. No locale logic is needed inside it — the locale-specific path structure is already defined in the template.

---

### Query Parameters

A path template (and a static link `href` / `other_locs` entry) may end with a query string. Everything after the **first `?`** is treated as the query string: `?`, `&` and `=` keep their meaning, while parameter keys and values are encoded like path segments.

```php
public static function sitemapTranslatedSegments(): array
{
    return [
        'en' => 'blogs/:modelIdentifier?target_country=eg',
        'ar' => 'المدونة?target_country=eg',
    ];
}
```

Produces:

```
https://example.com/en/blogs/my-post?target_country=eg
https://example.com/%D8%A7%D9%84%D9%85%D8%AF%D9%88%D9%86%D8%A9?target_country=eg
```

Placeholders work inside the query string too, so a parameter value can be dynamic:

```php
'en' => 'blogs/:modelIdentifier?target_country=:country',

slugResolver: fn($blog) => [
    ':modelIdentifier' => $blog->slug,
    ':country' => $blog->country_code,
],
```

Notes:

- Multiple parameters are supported: `blogs?target_country=eg&sort=latest`.
- A value-less parameter is kept as-is: `blogs?featured`.
- An empty query string is dropped: `blogs?` becomes `blogs`.
- A `?` inside a parameter value is encoded (`%3F`) — only the first one separates path from query.

---

### Multiple URL Variants per Model

When one model record maps to **several** URLs (e.g. a blog targeting several countries), return a **list of replacement maps** from `$slugResolver` instead of a single map. Each map produces a full set of locale URLs:

```php
public static function sitemapTranslatedSegments(): array
{
    return [
        'en' => 'blogs/:modelIdentifier?target_country=:targetCountry',
        'ar' => ':modelIdentifier/المدونة?target_country=:targetCountry',
    ];
}

public static function buildSitemapUrls(): array
{
    $blogs = self::visible()->with('targetCountries')->get();

    return HandleDynamicSitemapHelper::buildLocalizedUrls(
        modelItems: $blogs,
        translatedSegments: self::sitemapTranslatedSegments(),
        slugResolver: fn($blog) => $blog->targetCountries
            ->map(fn($country) => [':targetCountry' => $country->code])
            ->all(),
    );
}
```

A blog with 2 locales and 3 target countries yields 6 `<url>` entries. Alternates stay **within** a variant: the `?target_country=eg` URL only cross-links the `eg` variant of the other locales, so each country forms its own hreflang cluster.

Both return shapes are supported:

| `$slugResolver` returns | Result |
|---|---|
| `[':x' => 'y']` (map) | one URL per locale |
| `[[':x' => 'y'], [':x' => 'z']]` (list of maps) | one URL per locale, per entry |
| `[]` | one URL per locale, no extra placeholders resolved |

> An **empty list** is treated as "no extra placeholders", not "no URLs" — a record with zero related rows would then emit URLs with the placeholder left unresolved. Filter those records out of the query (`->whereHas('targetCountries')`) or return a fallback map for them.

---

### Non-Translated Slugs (`buildDefaultUrls`)

Use this when the model's slug is the **same across all locales** (e.g. offers, products).
Since the slug is the same, there's no automatic resolution of `:modelIdentifier`. You must provide ALL placeholders in the `slugResolver`.

```php
HandleDynamicSitemapHelper::buildDefaultUrls(
    Collection $modelItems,       // The Eloquent collection
    array $translatedSegments,    // Path template per locale
    callable $slugResolver,       // Required: provide all placeholders
    float $priority,              // URL priority (default: 0.8)
);
```

**Simple example — single segment:**

```php
use Otas\Sitemap\Contracts\SitemapContract;
use Otas\Sitemap\Helpers\HandleDynamicSitemapHelper;

class Offer extends Model implements SitemapContract
{
    public static function sitemapTranslatedSegments(): array
    {
        return [
            'en' => 'offers/:modelIdentifier',
            'ar' => ':modelIdentifier/العروض',
        ];
    }

    public static function buildSitemapUrls(): array
    {
        $offers = self::visible()->get();

        return HandleDynamicSitemapHelper::buildDefaultUrls(
            modelItems: $offers,
            translatedSegments: self::sitemapTranslatedSegments(),
            slugResolver: fn($offer) => [
                ':modelIdentifier' => $offer->slug,
            ]
        );
    }
}
```

Generated URLs for an offer with slug `summer-sale`:

```
en/offers/summer-sale
summer-sale/العروض
```

---

### Translated Slugs (`buildLocalizedUrls`)

Use this when the model's slug is **different per locale** (e.g. blog posts with translated titles/slugs).

```php
HandleDynamicSitemapHelper::buildLocalizedUrls(
    Collection $modelItems,       // The Eloquent collection
    array $translatedSegments,    // Path template per locale
    ?callable $slugResolver,      // Optional: resolve extra placeholders
    string $routeKeyName,         // Translatable attribute to pluck (default: 'slug')
    float $priority,              // URL priority (default: 0.8)
);
```

The `:modelIdentifier` placeholder is automatically resolved to the **translated value** for each locale from the model's translations table.

**Simple example — single segment:**

```php
use Otas\Sitemap\Contracts\SitemapContract;
use Otas\Sitemap\Helpers\HandleDynamicSitemapHelper;

class Blog extends Model implements SitemapContract
{
    public static function sitemapTranslatedSegments(): array
    {
        return [
            'en' => 'blogs/:modelIdentifier',
            'ar' => ':modelIdentifier/المدونة',
        ];
    }

    public static function buildSitemapUrls(): array
    {
        $blogs = self::visible()->hasVisibleBlogCategory()->get();

        return HandleDynamicSitemapHelper::buildLocalizedUrls(
            modelItems: $blogs,
            translatedSegments: self::sitemapTranslatedSegments(),
        );
    }
}
```

Generated URLs for a blog with EN slug `laravel-tips` and AR slug `نصائح-لارافل`:

```
en/blogs/laravel-tips
نصائح-لارافل/المدونة
```

---

### Multi-Segment URLs

For URLs with multiple dynamic parts, add custom placeholders to the template and provide a `slugResolver`.

#### Example: Offer with Category (non-translated)

URL structure: `offers/{category}/{offer-slug}`

```php
class Offer extends Model implements SitemapContract
{
    public static function sitemapTranslatedSegments(): array
    {
        return [
            'en' => 'offers/:categorySlug/details/:modelIdentifier',
            'ar' => ':modelIdentifier/تفاصيل/:categorySlug/العروض',
        ];
    }

    public static function buildSitemapUrls(): array
    {
        $offers = self::visible()->with('category')->get();

        return HandleDynamicSitemapHelper::buildDefaultUrls(
            modelItems: $offers,
            translatedSegments: self::sitemapTranslatedSegments(),
            slugResolver: fn($offer) => [
                ':modelIdentifier' => $offer->slug,
                ':categorySlug' => $offer->category->slug,
            ],
        );
    }
}
```

Generated URLs for offer `summer-sale` in category `electronics`:

```
en/offers/electronics/details/summer-sale
summer-sale/تفاصيل/electronics/العروض
```

#### Example: Blog with Category (translated)

URL structure: `blogs/{category}/{post-slug}`

```php
class Blog extends Model implements SitemapContract
{
    public static function sitemapTranslatedSegments(): array
    {
        return [
            'en' => 'blogs/:categorySlug/:modelIdentifier',
            'ar' => ':modelIdentifier/:categorySlug/المدونة',
        ];
    }

    public static function buildSitemapUrls(): array
    {
        $blogs = self::visible()->with('category')->get();

        return HandleDynamicSitemapHelper::buildLocalizedUrls(
            modelItems: $blogs,
            translatedSegments: self::sitemapTranslatedSegments(),
            slugResolver: fn($blog) => [
                ':categorySlug' => $blog->category->slug,
            ],
        );
    }
}
```

Generated URLs for blog with EN slug `laravel-tips`, AR slug `نصائح-لارافل`, in category `tech`:

```
en/blogs/tech/laravel-tips
نصائح-لارافل/tech/المدونة
```

#### Example: Product with Brand + Category (three levels)

URL structure: `shop/{brand}/{category}/{product-slug}`

```php
class Product extends Model implements SitemapContract
{
    public static function sitemapTranslatedSegments(): array
    {
        return [
            'en' => 'shop/:brandSlug/:categorySlug/:modelIdentifier',
            'ar' => ':modelIdentifier/:categorySlug/:brandSlug/متجر',
        ];
    }

    public static function buildSitemapUrls(): array
    {
        $products = self::visible()->with(['brand', 'category'])->get();

        return HandleDynamicSitemapHelper::buildDefaultUrls(
            modelItems: $products,
            translatedSegments: self::sitemapTranslatedSegments(),
            slugResolver: fn($product) => [
                ':modelIdentifier' => $product->slug,
                ':brandSlug' => $product->brand->slug,
                ':categorySlug' => $product->category->slug,
            ],
        );
    }
}
```

Generated URLs for product `macbook-pro`, brand `apple`, category `laptops`:

```
en/shop/apple/laptops/macbook-pro
macbook-pro/laptops/apple/متجر
```

---

## Generating the Sitemap

Generate the sitemap XML file using the Artisan command:

```bash
php artisan app:generate-sitemap
```

The file is saved to the `public` disk at the path configured in `sitemap.base_path` + `sitemaps/sitemap.xml`. If a `subdomain` is configured, the filename becomes `{subdomain}-sitemap.xml`.

You can schedule this command in your `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    $schedule->command('app:generate-sitemap')->daily();
}
```

---

## Serving the Sitemap

When `load_routes` is `true` (default), the package registers a route that serves the generated sitemap:

```
GET /api/sitemap
```

The route prefix is controlled by `package_routes_prefix` in the config.

To publish and customize the route file:

```bash
php artisan sitemap:publish-routes
```

This copies the route file to your application's `routes/` directory for full control.

---

## Artisan Commands

| Command | Description |
|---|---|
| `php artisan setup:sitemap` | Publish the config file (with overwrite prompt if it already exists) |
| `php artisan app:generate-sitemap` | Generate the sitemap XML and save it to the public disk |
| `php artisan sitemap:publish-routes` | Publish the package route file to your application's `routes/` directory |

---

## Full Examples

### Minimal Setup (Single Model, No Translations)

**1. Config:**

```php
// config/sitemap.php
'default_locale' => 'en',
'dynamic_links' => [
    App\Models\Offer::class,
],
```

**2. Model:**

```php
use Otas\Sitemap\Contracts\SitemapContract;
use Otas\Sitemap\Helpers\HandleDynamicSitemapHelper;

class Offer extends Model implements SitemapContract
{
    public static function sitemapTranslatedSegments(): array
    {
        return [
            'en' => 'offers/:modelIdentifier',
            'ar' => ':modelIdentifier/العروض',
        ];
    }

    public static function buildSitemapUrls(): array
    {
        return HandleDynamicSitemapHelper::buildDefaultUrls(
            modelItems: self::visible()->get(),
            translatedSegments: self::sitemapTranslatedSegments(),
            slugResolver: fn($offer) => [
                ':modelIdentifier' => $offer->slug,
            ]
        );
    }
}
```

**3. Generate:**

```bash
php artisan app:generate-sitemap
```

### Complete Setup (Static + Multiple Dynamic Models)

**Config:**

```php
// config/sitemap.php
'website_url' => 'https://example.com',
'subdomain' => null,
'default_locale' => 'en',

'static_links' => [
    [
        'loc' => '',
        'other_locs' => ['ar'],
        'alternates' => [
            ['hreflang' => 'en', 'href' => ''],
            ['hreflang' => 'ar', 'href' => 'ar'],
            ['hreflang' => 'x-default', 'href' => ''],
        ],
        'priority' => '1.0',
    ],
    [
        'loc' => 'en/about',
        'other_locs' => [],
        'alternates' => [
            ['hreflang' => 'en', 'href' => 'en/about'],
            ['hreflang' => 'ar', 'href' => 'عن-الشركة'],
        ],
        'priority' => '0.8',
    ],
],

'dynamic_links' => [
    App\Models\Offer::class,
    App\Models\Blog::class,
    App\Models\Product::class,
],
```

**Models:**

```php
// Simple — non-translated slug
class Offer extends Model implements SitemapContract
{
    public static function sitemapTranslatedSegments(): array
    {
        return [
            'en' => 'offers/:modelIdentifier',
            'ar' => ':modelIdentifier/العروض',
        ];
    }

    public static function buildSitemapUrls(): array
    {
        return HandleDynamicSitemapHelper::buildDefaultUrls(
            modelItems: self::visible()->get(),
            translatedSegments: self::sitemapTranslatedSegments(),
            slugResolver: fn($offer) => [
                ':modelIdentifier' => $offer->slug,
            ]
        );
    }
}

// Simple — translated slug
class Blog extends Model implements SitemapContract
{
    public static function sitemapTranslatedSegments(): array
    {
        return [
            'en' => 'blogs/:modelIdentifier',
            'ar' => ':modelIdentifier/المدونة',
        ];
    }

    public static function buildSitemapUrls(): array
    {
        return HandleDynamicSitemapHelper::buildLocalizedUrls(
            modelItems: self::visible()->get(),
            translatedSegments: self::sitemapTranslatedSegments(),
        );
    }
}

// Multi-segment — with slugResolver
class Product extends Model implements SitemapContract
{
    public static function sitemapTranslatedSegments(): array
    {
        return [
            'en' => 'shop/:brandSlug/:categorySlug/:modelIdentifier',
            'ar' => ':modelIdentifier/:categorySlug/:brandSlug/متجر',
        ];
    }

    public static function buildSitemapUrls(): array
    {
        return HandleDynamicSitemapHelper::buildDefaultUrls(
            modelItems: self::visible()->with(['brand', 'category'])->get(),
            translatedSegments: self::sitemapTranslatedSegments(),
            slugResolver: fn($product) => [
                ':modelIdentifier' => $product->slug,
                ':brandSlug' => $product->brand->slug,
                ':categorySlug' => $product->category->slug,
            ],
        );
    }
}
```

---

## Generated XML Output

The generated sitemap follows the [Sitemaps XML protocol](https://www.sitemaps.org/protocol.html) with `xhtml:link` alternate tags for multilingual support:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xhtml="http://www.w3.org/1999/xhtml">

  <!-- Static page -->
  <url>
    <loc>https://www.example.com</loc>
    <xhtml:link rel="alternate" hreflang="en" href="https://www.example.com"/>
    <xhtml:link rel="alternate" hreflang="ar" href="https://www.example.com/ar"/>
    <xhtml:link rel="alternate" hreflang="x-default" href="https://www.example.com"/>
    <lastmod>2026-07-21T12:00:00+00:00</lastmod>
    <changefreq>daily</changefreq>
    <priority>1.0</priority>
  </url>

  <!-- Dynamic page (offer: summer-sale) -->
  <url>
    <loc>https://www.example.com/en/offers/summer-sale</loc>
    <xhtml:link rel="alternate" hreflang="en" href="https://www.example.com/en/offers/summer-sale"/>
    <xhtml:link rel="alternate" hreflang="ar" href="https://www.example.com/summer-sale/%D8%A7%D9%84%D8%B9%D8%B1%D9%88%D8%B6"/>
    <xhtml:link rel="alternate" hreflang="x-default" href="https://www.example.com/en/offers/summer-sale"/>
    <lastmod>2026-07-21T12:00:00+00:00</lastmod>
    <changefreq>daily</changefreq>
    <priority>0.8</priority>
  </url>

</urlset>
```

---

## License

Otas Sitemap is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).