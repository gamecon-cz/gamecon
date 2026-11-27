<?php

declare(strict_types=1);

namespace Gamecon\PushNotifikace;

use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Záznam o odeslaných notifikacích a limit, jak často je jde poslat.
 *
 * Do tabulky `push_notifikace_odeslani` jde řádek za každé odeslání (kdo, komu, jaký druh, počty výsledků), podle něj
 * se počítá limit. Do souboru `LOGY/push-notifikace-RRRR-MM.log` jde řádek za každé zařízení. Ani jedno nenese
 * endpoint, klíče ani obsah notifikace: z endpointu jen jméno push služby.
 */
final readonly class ZaznamOdeslani
{
    /**
     * Kolik notifikací smí jeden člověk z adminu poslat za {@see self::OKNO_SEKUND}.
     */
    public const int LIMIT_ODESILATELE = 30;

    /**
     * Kolik notifikací smí za {@see self::OKNO_SEKUND} dostat jeden uživatel, ať je posílá kdokoli.
     */
    public const int LIMIT_PRIJEMCE = 10;

    public const int OKNO_SEKUND = 10 * 60;

    /**
     * Starší záznamy limit nepotřebuje. Historie odeslaných notifikací zatím není, až bude, uchová si je déle.
     */
    private const int UCHOVAT_DNU = 90;

    public function __construct(
        private ClockInterface $hodiny,
        private string $adresarLogu,
    ) {
    }

    /**
     * Smí odesílatel poslat příjemci další notifikaci? Jen se ptá, nic nezapisuje: zapisuje až
     * {@see self::zaznamenej()} po odeslání, takže odmítnutý pokus limit nespotřebuje.
     *
     * @throws ChybaPushNotifikace
     */
    public function overLimit(?int $idOdesilatele, int $idPrijemce): void
    {
        $od = $this->hodiny->now()->getTimestamp() - self::OKNO_SEKUND;
        $minut = intdiv(self::OKNO_SEKUND, 60);
        if ($idOdesilatele !== null) {
            $odeslal = (int) dbOneCol(<<<'SQL'
                SELECT COUNT(*)
                FROM push_notifikace_odeslani
                WHERE id_odesilatele = $0 AND odeslano_unix > $1
                SQL, [
    0             => $idOdesilatele,
                1 => $od,
]);
            if ($odeslal >= self::LIMIT_ODESILATELE) {
                throw new ChybaPushNotifikace(sprintf('Za posledních %d minut jsi poslal(a) %d notifikací, víc teď nejde. Zkus to za chvíli.', $minut, $odeslal), 429);
            }
        }
        $dostal = (int) dbOneCol(<<<'SQL'
            SELECT COUNT(*)
            FROM push_notifikace_odeslani
            WHERE id_prijemce = $0 AND odeslano_unix > $1
            SQL, [
    0         => $idPrijemce,
            1 => $od,
]);
        if ($dostal >= self::LIMIT_PRIJEMCE) {
            throw new ChybaPushNotifikace(sprintf('Tenhle uživatel dostal za posledních %d minut už %d notifikací, víc mu teď poslat nejde.', $minut, $dostal), 429);
        }
    }

    /**
     * @param list<VysledekOdeslani> $vysledky
     */
    public function zaznamenej(?int $idOdesilatele, int $idPrijemce, TypNotifikace $typ, array $vysledky): void
    {
        $ted = $this->hodiny->now();
        $pocty = array_count_values(array_map(
            static fn (VysledekOdeslani $vysledek): string => $vysledek->stav->value,
            $vysledky,
        ));
        dbQuery(<<<'SQL'
            INSERT INTO push_notifikace_odeslani
                (id_odesilatele, id_prijemce, typ, odeslano_unix, pocet_zarizeni, pocet_predano, pocet_smazano, pocet_chyb)
            VALUES ($0, $1, $2, $3, $4, $5, $6, $7)
            SQL, [
    0         => $idOdesilatele,
            1 => $idPrijemce,
            2 => $typ->value,
            3 => $ted->getTimestamp(),
            4 => count($vysledky),
            5 => $pocty[StavOdeslani::PREDANO->value] ?? 0,
            6 => $pocty[StavOdeslani::NEPLATNA_SMAZANA->value] ?? 0,
            7 => $pocty[StavOdeslani::CHYBA->value] ?? 0,
]);
        dbQuery(<<<'SQL'
            DELETE FROM push_notifikace_odeslani
            WHERE odeslano_unix < $0
            LIMIT 1000
            SQL, [
            0 => $ted->getTimestamp() - self::UCHOVAT_DNU * 86400,
        ]);

        $radky = '';
        foreach ($vysledky as $vysledek) {
            $radky .= $this->getRadekLogu($ted, $idOdesilatele, $idPrijemce, $typ, $vysledek);
        }
        if ($vysledky === []) {
            $radky = sprintf(
                "%s odesilatel=%s prijemce=%d typ=%s zadne-zarizeni\n",
                $ted->format(DATE_ATOM),
                $idOdesilatele ?? 'system',
                $idPrijemce,
                $typ->value,
            );
        }
        $this->zapisDoLogu($ted, $radky);
    }

    /**
     * Hostitel, kterého kontrola endpointu odmítla: podle logu se pozná push služba, která v seznamu chybí.
     */
    public function zaznamenejOdmitnutyEndpoint(int $idUzivatele, string $hostitel): void
    {
        $ted = $this->hodiny->now();
        $this->zapisDoLogu($ted, sprintf(
            "%s uzivatel=%d odmitnuty-endpoint sluzba=%s\n",
            $ted->format(DATE_ATOM),
            $idUzivatele,
            self::bezpecneDoLogu($hostitel),
        ));
    }

    private function getRadekLogu(
        \DateTimeInterface $ted,
        ?int $idOdesilatele,
        int $idPrijemce,
        TypNotifikace $typ,
        VysledekOdeslani $vysledek,
    ): string {
        return sprintf(
            "%s odesilatel=%s prijemce=%d typ=%s subscription=%d sluzba=%s stav=%s http=%s duvod=%s\n",
            $ted->format(DATE_ATOM),
            $idOdesilatele ?? 'system',
            $idPrijemce,
            $typ->value,
            $vysledek->idSubscription,
            self::bezpecneDoLogu($vysledek->pushSluzba),
            $vysledek->stav->value,
            $vysledek->httpKod ?? '-',
            self::bezpecneDoLogu($vysledek->duvod),
        );
    }

    private function zapisDoLogu(\DateTimeInterface $ted, string $radky): void
    {
        (new Filesystem())->mkdir($this->adresarLogu, 0775);
        file_put_contents(
            $this->adresarLogu . '/push-notifikace-' . $ted->format('Y-m') . '.log',
            $radky,
            FILE_APPEND | LOCK_EX,
        );
    }

    /**
     * Hodnota na jeden řádek logu: bez konců řádků a mezer, které by rozbily jeho čtení, a ne delší než je rozumné.
     */
    private static function bezpecneDoLogu(string $hodnota): string
    {
        $hodnota = preg_replace('/[^\x21-\x7E]+/', '_', $hodnota) ?? '';

        return $hodnota === ''
            ? '-'
            : substr($hodnota, 0, 200);
    }
}
