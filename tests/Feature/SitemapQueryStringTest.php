<?php

namespace Otas\Sitemap\Tests\Feature;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Otas\Sitemap\Helpers\HandleDynamicSitemapHelper;
use Otas\Sitemap\Helpers\HandleStaticSitemapHelper;
use Otas\Sitemap\Helpers\SitemapHelperFunctions;
use Otas\Sitemap\Tests\TestCase;

class SitemapQueryStringTest extends TestCase
{
    /** @test */
    public function it_encodes_a_path_without_a_query_string()
    {
        $this->assertSame(
            '%D8%A7%D9%84%D9%85%D8%AF%D9%88%D9%86%D8%A9',
            SitemapHelperFunctions::encodeUrl('المدونة')
        );
    }

    /** @test */
    public function it_keeps_the_query_string_syntax_intact()
    {
        $this->assertSame(
            '%D8%A7%D9%84%D9%85%D8%AF%D9%88%D9%86%D8%A9?target_country=eg',
            SitemapHelperFunctions::encodeUrl('المدونة?target_country=eg')
        );
    }

    /** @test */
    public function it_supports_multiple_query_parameters()
    {
        $this->assertSame(
            'blogs/my-post?target_country=eg&sort=latest',
            SitemapHelperFunctions::encodeUrl('blogs/my-post?target_country=eg&sort=latest')
        );
    }

    /** @test */
    public function it_encodes_query_keys_and_values_but_not_the_separators()
    {
        $this->assertSame(
            'blogs?%D8%A7%D9%84%D8%AF%D9%88%D9%84%D8%A9=%D9%85%D8%B5%D8%B1&tag=new%20year',
            SitemapHelperFunctions::encodeUrl('blogs?الدولة=مصر&tag=new year')
        );
    }

    /** @test */
    public function it_keeps_a_parameter_without_a_value()
    {
        $this->assertSame('blogs?featured', SitemapHelperFunctions::encodeUrl('blogs?featured'));
    }

    /** @test */
    public function it_drops_an_empty_query_string()
    {
        $this->assertSame('blogs', SitemapHelperFunctions::encodeUrl('blogs?'));
        $this->assertSame('blogs', SitemapHelperFunctions::encodeUrl('blogs?&'));
    }

    /** @test */
    public function it_only_treats_the_first_question_mark_as_a_separator()
    {
        $this->assertSame('blogs?q=a%3Fb', SitemapHelperFunctions::encodeUrl('blogs?q=a?b'));
    }

    /** @test */
    public function it_builds_static_links_with_query_parameters()
    {
        config()->set('sitemap.default_locale', 'en');
        config()->set('sitemap.static_links', [
            [
                'loc' => 'ar?target_country=eg',
                'other_locs' => ['المدونة?target_country=eg'],
                'alternates' => [
                    ['hreflang' => 'ar', 'href' => 'المدونة?target_country=eg'],
                    ['hreflang' => 'en', 'href' => 'en/blog?target_country=eg'],
                ],
                'priority' => '1.0',
            ],
        ]);

        $urls = HandleStaticSitemapHelper::buildUrls();

        $this->assertSame(
            ['%D8%A7%D9%84%D9%85%D8%AF%D9%88%D9%86%D8%A9?target_country=eg'],
            $urls[0]['other_locs']
        );
        $this->assertSame(
            [
                ['hreflang' => 'ar', 'href' => '%D8%A7%D9%84%D9%85%D8%AF%D9%88%D9%86%D8%A9?target_country=eg'],
                ['hreflang' => 'en', 'href' => 'en/blog?target_country=eg'],
                ['hreflang' => 'x-default', 'href' => 'en/blog?target_country=eg'],
            ],
            $urls[0]['alternates']
        );
    }

    /** @test */
    public function it_builds_dynamic_links_with_query_parameters()
    {
        config()->set('sitemap.default_locale', 'en');
        config()->set('translatable.locales', ['en', 'ar']);

        $urls = HandleDynamicSitemapHelper::buildDefaultUrls(
            modelItems: new Collection([new SitemapQueryStringTestModel()]),
            translatedSegments: [
                'en' => 'blogs/:modelIdentifier?target_country=:country',
                'ar' => ':modelIdentifier/المدونة?target_country=:country',
            ],
            slugResolver: fn() => [':modelIdentifier' => 'my-post', ':country' => 'eg'],
        );

        $this->assertSame('en/blogs/my-post?target_country=eg', $urls[0]['loc']);
        $this->assertSame(
            'my-post/%D8%A7%D9%84%D9%85%D8%AF%D9%88%D9%86%D8%A9?target_country=eg',
            $urls[1]['loc']
        );
        $this->assertSame(
            [
                ['hreflang' => 'en', 'href' => 'en/blogs/my-post?target_country=eg'],
                ['hreflang' => 'ar', 'href' => 'my-post/%D8%A7%D9%84%D9%85%D8%AF%D9%88%D9%86%D8%A9?target_country=eg'],
                ['hreflang' => 'x-default', 'href' => 'en/blogs/my-post?target_country=eg'],
            ],
            $urls[0]['alternates']
        );
    }

    /** @test */
    public function it_builds_one_url_per_locale_per_replacement_set()
    {
        config()->set('sitemap.default_locale', 'en');
        config()->set('translatable.locales', ['en', 'ar']);

        $urls = HandleDynamicSitemapHelper::buildDefaultUrls(
            modelItems: new Collection([new SitemapQueryStringTestModel()]),
            translatedSegments: [
                'en' => 'blogs/:modelIdentifier?target_country=:country',
                'ar' => ':modelIdentifier/المدونة?target_country=:country',
            ],
            slugResolver: fn() => [
                [':modelIdentifier' => 'my-post', ':country' => 'eg'],
                [':modelIdentifier' => 'my-post', ':country' => 'sa'],
            ],
        );

        $this->assertCount(4, $urls);
        $this->assertSame(
            [
                'en/blogs/my-post?target_country=eg',
                'my-post/%D8%A7%D9%84%D9%85%D8%AF%D9%88%D9%86%D8%A9?target_country=eg',
                'en/blogs/my-post?target_country=sa',
                'my-post/%D8%A7%D9%84%D9%85%D8%AF%D9%88%D9%86%D8%A9?target_country=sa',
            ],
            array_column($urls, 'loc')
        );
    }

    /** @test */
    public function it_keeps_alternates_within_the_same_replacement_set()
    {
        config()->set('sitemap.default_locale', 'en');
        config()->set('translatable.locales', ['en', 'ar']);

        $urls = HandleDynamicSitemapHelper::buildDefaultUrls(
            modelItems: new Collection([new SitemapQueryStringTestModel()]),
            translatedSegments: [
                'en' => 'blogs/:modelIdentifier?target_country=:country',
                'ar' => ':modelIdentifier/blog?target_country=:country',
            ],
            slugResolver: fn() => [
                [':modelIdentifier' => 'my-post', ':country' => 'eg'],
                [':modelIdentifier' => 'my-post', ':country' => 'sa'],
            ],
        );

        // The "sa" URL must only cross-link the "sa" variant of the other locales
        $this->assertSame(
            [
                ['hreflang' => 'en', 'href' => 'en/blogs/my-post?target_country=sa'],
                ['hreflang' => 'ar', 'href' => 'my-post/blog?target_country=sa'],
                ['hreflang' => 'x-default', 'href' => 'en/blogs/my-post?target_country=sa'],
            ],
            $urls[2]['alternates']
        );
    }

    /** @test */
    public function it_still_accepts_a_single_replacement_map()
    {
        config()->set('sitemap.default_locale', 'en');
        config()->set('translatable.locales', ['en', 'ar']);

        $urls = HandleDynamicSitemapHelper::buildDefaultUrls(
            modelItems: new Collection([new SitemapQueryStringTestModel()]),
            translatedSegments: ['en' => 'blogs/:modelIdentifier', 'ar' => ':modelIdentifier/blog'],
            slugResolver: fn() => [':modelIdentifier' => 'my-post'],
        );

        $this->assertSame(['en/blogs/my-post', 'my-post/blog'], array_column($urls, 'loc'));
    }

    /** @test */
    public function it_falls_back_to_a_single_url_when_the_resolver_returns_nothing()
    {
        config()->set('sitemap.default_locale', 'en');
        config()->set('translatable.locales', ['en', 'ar']);

        $urls = HandleDynamicSitemapHelper::buildDefaultUrls(
            modelItems: new Collection([new SitemapQueryStringTestModel()]),
            translatedSegments: ['en' => 'blogs', 'ar' => 'blog'],
            slugResolver: fn() => [],
        );

        $this->assertSame(['en/blogs', 'blog'], array_column($urls, 'loc'));
    }
}

class SitemapQueryStringTestModel extends Model
{
    //
}
