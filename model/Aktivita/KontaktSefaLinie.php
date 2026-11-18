<?php

declare(strict_types=1);

namespace Gamecon\Aktivita;

/**
 * Šéf sekce (linie) z hlavičky linie: tabulka akce_typy_hlavicky, admin Web → Hlavičky linií. Jediné místo, odkud ho
 * bere hlavička stránky linie (web/moduly/aktivity.php) i upozornění na věk, e-mail a report o mladším účastníkovi.
 * Linie bez vyplněného e-mailu v hlavičce má šéfa programu na info@gamecon.cz.
 */
class KontaktSefaLinie
{
    private const string SEF_PROGRAMU = 'šéf programu';
    private const string SEF_SEKCE    = 'šéf sekce';

    /**
     * Hlavičky všech linií, načtené jedním dotazem při prvním dotazu v požadavku: upozornění na věk se v programu
     * počítá u každé aktivity. Hlavička se mění v jiném požadavku (admin Web → Hlavičky linií).
     *
     * @var array<int, array{sekce: string|null, jmeno: string|null, email: string|null}>|null
     */
    private static ?array $hlavickyPodleTypu = null;

    public static function zTypu(int $idTypu): self
    {
        if (self::$hlavickyPodleTypu === null) {
            self::$hlavickyPodleTypu = [];
            foreach (dbFetchAll(<<<'SQL'
                SELECT id_typu, sekce, jmeno, email FROM akce_typy_hlavicky
                SQL) as $hlavicka) {
                self::$hlavickyPodleTypu[(int) $hlavicka['id_typu']] = $hlavicka;
            }
        }
        $hlavicka = self::$hlavickyPodleTypu[$idTypu] ?? [];

        return new self(
            sekce: $hlavicka['sekce'] ?? null,
            jmeno: $hlavicka['jmeno'] ?? null,
            email: $hlavicka['email'] ?? null,
        );
    }

    /**
     * Zapomene hlavičky načtené v tomto požadavku, pro proces, který obslouží víc požadavků (testy, AbstractTestDb).
     * Kdo hlavičku v témže požadavku změní a pak čte, volá ji také.
     */
    public static function smazCache(): void
    {
        self::$hlavickyPodleTypu = null;
    }

    private readonly ?string $sekce;
    private readonly ?string $jmeno;
    private readonly ?string $email;

    /**
     * Hodnoty z hlavičky linie, prázdné jako null.
     */
    private function __construct(?string $sekce, ?string $jmeno, ?string $email)
    {
        $this->sekce = self::neprazdne($sekce);
        $this->jmeno = self::neprazdne($jmeno);
        $this->email = self::neprazdne($email);
    }

    private static function neprazdne(?string $hodnota): ?string
    {
        $hodnota = trim((string) $hodnota);

        return $hodnota !== ''
            ? $hodnota
            : null;
    }

    /**
     * Sekce, jak je v hlavičce linie („šéfka Larpů“), null když ji hlavička nemá.
     */
    public function getSekce(): ?string
    {
        return $this->sekce;
    }

    /**
     * Jméno šéfa sekce, jak je v hlavičce linie, null když ho hlavička nemá.
     */
    public function getJmeno(): ?string
    {
        return $this->jmeno;
    }

    /**
     * E-mail šéfa sekce, bez e-mailu v hlavičce šéf programu.
     */
    public function getEmail(): string
    {
        return $this->email ?? Aktivita::KONTAKT_NA_SEFA_PROGRAMU;
    }

    /**
     * Kdo to je, malým písmenem jako podmět věty („šéfka Larpů se to ale dozví“).
     * Sekce je v hlavičce volný text, do věty proto jde, jen když je to role („šéf …“, „šéfka …“). Jinak („Larpy“,
     * „LKD tým“) obecně „šéf sekce“. Bez e-mailu v hlavičce píše šéf programu, i když hlavička sekci má.
     */
    public function getKdo(): string
    {
        if ($this->email === null) {
            return self::SEF_PROGRAMU;
        }
        if ($this->sekce !== null && preg_match('~^šéf~iu', $this->sekce)) {
            // v hlavičce „šéfka Larpů“ i „Šéf D&D/JaD turnaje“, ve větě je uprostřed
            return mb_lcfirst($this->sekce);
        }

        return self::SEF_SEKCE;
    }
}
