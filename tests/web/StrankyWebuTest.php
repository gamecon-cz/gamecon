<?php

declare(strict_types=1);

namespace Gamecon\Tests\web;

class StrankyWebuTest extends AbstractWebTest
{
    /**
     * @test
     * @dataProvider provideWebUrls
     * @param string[] $urls
     */
    public function Muzu_si_zobrazit_kazdou_stranku_na_webu(...$urls) {
        $this->testPagesAccessibility($urls);
    }

    /**
     * @test
     * @dataProvider provideAdminUrls
     * @param string[] $urls
     */
    public function Muzu_si_zobrazit_kazdou_stranku_v_adminu(...$urls) {
        $this->testAdminPagesAccessibility($urls);
    }

    public function provideWebUrls(): array {
        return [
            'moduly webu' => $this->getUrlsModuluWebu(),
        ];
    }

    public function provideAdminUrls(): array {
        return [
            'moduly adminu' => $this->getUrlsModuluAdminu(),
        ];
    }

    protected function getUrlsModuluWebu(): array {
        $modulyWebu         = scandir(__DIR__ . '/../../web/moduly');
        $modulyWebuBaseUrls = [];
        foreach ($modulyWebu as $modulWebu) {
            if (!preg_match('~[.]php$~', $modulWebu)) {
                continue;
            }
            $modulyWebuBaseUrls[] = basename($modulWebu, '.php');
        }
        $webBaseUrl = basename(__DIR__ . '/../../web');
        return array_map(static function (string $modulWebuUrl) use ($webBaseUrl) {
            return $webBaseUrl . '/' . $modulWebuUrl;
        }, $modulyWebuBaseUrls);
    }

    protected function getUrlsModuluAdminu(): array {
        $modulyWebu         = scandir(__DIR__ . '/../../admin/scripts/modules');
        $modulyWebuBaseUrls = [];
        foreach ($modulyWebu as $modulWebu) {
            if (!preg_match('~(^[^_].*[.]php$|^[a-z-]+$)~', $modulWebu)) {
                continue;
            }
            $modulyWebuBaseUrls[] = basename($modulWebu, '.php');
        }
        $adminBaseUrl       = basename(__DIR__ . '/../../admin');
        return array_map(static function (string $modulAdminuUrl) use ($adminBaseUrl) {
            return $adminBaseUrl . '/' . $modulAdminuUrl;
        }, $modulyWebuBaseUrls);
    }
}
