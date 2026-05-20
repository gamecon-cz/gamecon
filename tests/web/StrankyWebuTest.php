<?php

declare(strict_types=1);

namespace Gamecon\Tests\web;

class StrankyWebuTest extends AbstractTestWeb
{
    private const EXTERNI_REDIRECT_MODULY_WEBU = [
        'discord',
        'facebook',
        'instagram',
        'youtube',
    ];

    /**
     * @test
     *
     * @dataProvider provideWebUrls
     *
     * @param string[] $urls
     */
    public function muzuSiZobrazitKazdouStrankuNaWebu(...$urls)
    {
        // aby se DNS vyřešilo ještě před curl, které by jinak mohlo padnout na ještě nepřipraveném Apache
        get_headers(URL_WEBU);

        $this->testPagesAccessibility($urls);
    }

    /**
     * @test
     *
     * @dataProvider provideWebRedirectUrls
     *
     * @param string[] $urls
     */
    public function muzuSiSeNechatPresmerovatNaKazdouExterniStrankuZWebu(...$urls)
    {
        get_headers(URL_WEBU);

        $this->testPagesRedirect($urls);
    }

    /**
     * @test
     *
     * @dataProvider provideAdminUrls
     *
     * @param string[] $urls
     */
    public function muzuSiZobrazitKazdouStrankuVAdminu(...$urls)
    {
        $this->testAdminPagesAccessibility($urls);
    }

    public static function provideWebUrls(): array
    {
        return [
            'moduly webu' => array_values(array_diff(
                self::getUrlsModuluWebu(),
                self::getUrlsExternichRedirectu(),
            )),
        ];
    }

    public static function provideWebRedirectUrls(): array
    {
        return [
            'externi redirecty z webu' => self::getUrlsExternichRedirectu(),
        ];
    }

    public static function provideAdminUrls(): array
    {
        return [
            'moduly adminu' => self::getUrlsModuluAdminu(),
        ];
    }

    protected static function getUrlsModuluWebu(): array
    {
        $modulyWebu = scandir(__DIR__ . '/../../web/moduly');
        $modulyWebuBaseUrls = [];
        foreach ($modulyWebu as $modulWebu) {
            if (! preg_match('~[.]php$~', $modulWebu)) {
                continue;
            }
            $modulyWebuBaseUrls[] = basename($modulWebu, '.php');
        }
        $blocklist = [
            'ajax-vyjimkovac',
            'mail',
            'nenalezeno',
            'stranka',
            'programold',
            'program-nahled-api',
            'info-po-gc',
        ];
        $modulyWebuBaseUrls = array_diff($modulyWebuBaseUrls, $blocklist);

        return self::prefixWebBaseUrl($modulyWebuBaseUrls);
    }

    /**
     * @return string[]
     */
    protected static function getUrlsExternichRedirectu(): array
    {
        return self::prefixWebBaseUrl(self::EXTERNI_REDIRECT_MODULY_WEBU);
    }

    /**
     * @param string[] $modulyWebuBaseUrls
     *
     * @return string[]
     */
    private static function prefixWebBaseUrl(array $modulyWebuBaseUrls): array
    {
        $webBaseUrl = basename(__DIR__ . '/../../web');

        return array_map(static function (
            string $modulWebuUrl,
        ) use (
            $webBaseUrl,
        ) {
            return $webBaseUrl . '/' . $modulWebuUrl;
        }, $modulyWebuBaseUrls);
    }

    protected static function getUrlsModuluAdminu(): array
    {
        $modulyWebu = scandir(__DIR__ . '/../../admin/scripts/modules');
        $modulyWebuBaseUrls = [];
        foreach ($modulyWebu as $modulWebu) {
            if (! preg_match('~(^[^_].*[.]php$|^[a-z-]+$)~', $modulWebu)) {
                continue;
            }
            $modulyWebuBaseUrls[] = basename($modulWebu, '.php');
        }
        $adminBaseUrl = basename(__DIR__ . '/../../admin');

        return array_map(static function (
            string $modulAdminuUrl,
        ) use (
            $adminBaseUrl,
        ) {
            return $adminBaseUrl . '/' . $modulAdminuUrl;
        }, $modulyWebuBaseUrls);
    }
}
