<?php

declare(strict_types=1);

namespace Gamecon\Tests\Aktivity;

use Gamecon\Aktivita\OnlinePrezence\OnlinePrezenceAjax;
use Gamecon\Aktivita\OnlinePrezence\OnlinePrezenceHtml;
use Gamecon\Aktivita\StavAktivity;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractUzivatelTestDb;
use Symfony\Component\Filesystem\Filesystem;

class OnlinePrezenceAjaxTest extends AbstractUzivatelTestDb
{
    use ProbihaRegistraceAktivitTrait;

    protected static bool $disableStrictTransTables = true;

    protected static function resetDbAfterClass(): bool
    {
        return true;
    }

    private function vytvorOnlinePrezenceAjax(SystemoveNastaveni $systemoveNastaveni): OnlinePrezenceAjax
    {
        $filesystem = new Filesystem();
        $onlinePrezenceHtml = new OnlinePrezenceHtml(
            '',
            $systemoveNastaveni,
            $filesystem,
        );

        return new OnlinePrezenceAjax(
            $onlinePrezenceHtml,
            $filesystem,
            $systemoveNastaveni,
            false, // testujeme = false → production mode
        );
    }

    private function vytvorAktivitu(int $stav): int
    {
        dbInsert('akce_seznam', [
            'stav'     => $stav,
            'typ'      => 1,
            'teamova'  => 0,
            'kapacita' => 10,
            'zacatek'  => '2099-01-01 08:00:00',
            'konec'    => '2099-01-01 14:00:00',
            'rok'      => ROCNIK,
        ]);

        return dbInsertId();
    }

    private function zavolejAjaxZmenitPritomnost(
        OnlinePrezenceAjax $ajax,
        \Uzivatel $vypravec,
        int $idUcastnika,
        int $idAktivity,
        bool $dorazil,
    ): array {
        $method = new \ReflectionMethod($ajax, 'ajaxZmenitPritomnostUcastnika');
        $method->setAccessible(true);

        ob_start();
        $method->invoke($ajax, $vypravec, $idUcastnika, $idAktivity, $dorazil);
        $output = ob_get_clean();

        return json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Nastaví globální SystemoveNastaveni s aktivní registrací aktivit
     * a vrátí předchozí hodnotu pro obnovení.
     */
    private function nastavGlobalniSystemoveNastaveni(SystemoveNastaveni $noveNastaveni): ?SystemoveNastaveni
    {
        global $systemoveNastaveni;
        $puvodni = $systemoveNastaveni;
        $systemoveNastaveni = $noveNastaveni;

        return $puvodni;
    }

    public function testZmenitPritomnostNaAktiviteVPripravena()
    {
        $nastaveni = self::vytvorSystemoveNastaveni();
        $puvodni = $this->nastavGlobalniSystemoveNastaveni($nastaveni);

        try {
            $idAktivity = $this->vytvorAktivitu(StavAktivity::PRIPRAVENA);
            $ucastnik = self::prihlasenyUzivatel();
            $vypravec = self::prihlasenyUzivatel();

            $ajax = $this->vytvorOnlinePrezenceAjax($nastaveni);
            $result = $this->zavolejAjaxZmenitPritomnost(
                $ajax,
                $vypravec,
                (int) $ucastnik->id(),
                $idAktivity,
                true,
            );

            self::assertArrayHasKey(OnlinePrezenceAjax::PRIHLASEN, $result);
            self::assertTrue($result[OnlinePrezenceAjax::PRIHLASEN], 'Účastník by měl být označen jako dorazivší na PŘIPRAVENÉ aktivitě');
            self::assertArrayNotHasKey(OnlinePrezenceAjax::ERRORS, $result);
        } finally {
            $this->nastavGlobalniSystemoveNastaveni($puvodni);
        }
    }

    public function testZmenitPritomnostNaAktiviteVAktivovana()
    {
        $nastaveni = self::vytvorSystemoveNastaveni();
        $puvodni = $this->nastavGlobalniSystemoveNastaveni($nastaveni);

        try {
            $idAktivity = $this->vytvorAktivitu(StavAktivity::AKTIVOVANA);
            $ucastnik = self::prihlasenyUzivatel();
            $vypravec = self::prihlasenyUzivatel();

            $ajax = $this->vytvorOnlinePrezenceAjax($nastaveni);
            $result = $this->zavolejAjaxZmenitPritomnost(
                $ajax,
                $vypravec,
                (int) $ucastnik->id(),
                $idAktivity,
                true,
            );

            self::assertArrayHasKey(OnlinePrezenceAjax::PRIHLASEN, $result);
            self::assertTrue($result[OnlinePrezenceAjax::PRIHLASEN], 'Účastník by měl být označen jako dorazivší na AKTIVOVANÉ aktivitě');
            self::assertArrayNotHasKey(OnlinePrezenceAjax::ERRORS, $result);
        } finally {
            $this->nastavGlobalniSystemoveNastaveni($puvodni);
        }
    }
}
