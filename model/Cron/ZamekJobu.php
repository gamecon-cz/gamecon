<?php

declare(strict_types=1);

namespace Gamecon\Cron;

/**
 * Zajistí, aby jeden job neběžel ve dvou bězích cronu zároveň.
 *
 * Joby, které rozesílají maily, se nejdřív podívají do logu, jestli už maily odešly, pak desítky sekund
 * rozesílají a teprve na konci to zalogují. Druhý běh, který se zeptá mezitím, rozešle všechno podruhé
 * (17. 7. 2026 pustil cron-job.org `aktivity_hromadne` v 00:00:49 a znovu v 00:01:09 a 37 neplatičů dostalo
 * varování dvakrát). Kontrola, rozesílka i zápis do logu proto běží pod tímto zámkem a druhý běh, který ho
 * nedostane, nesmí udělat nic.
 *
 * Je to pojmenovaný zámek MariaDB (`GET_LOCK`) na vlastním, neperzistentním spojení, ne na hlavním:
 * - hlavní spojení je perzistentní a přežije request; zámek běhu, který spadl na časový limit (ten `finally`
 *   nespustí), by na něm zůstal viset, dokud by to spojení nepoužil některý další request;
 * - vlastní spojení PHP zavře s koncem requestu i po fatální chybě a MariaDB tím zámek uvolní;
 * - zámek nezávisí na transakcích hlavního spojení ani na jeho přepnutí (`dbReadOnly()`, reconnect).
 *
 * Pojmenované zámky platí pro celý server a ostrá, beta, archivy i preview sdílejí jednu MariaDB,
 * proto je v názvu zámku jméno databáze. Jinak by job na betě zablokoval stejný job na ostré.
 */
final class ZamekJobu
{
    private ?\mysqli $spojeni = null;

    public function __construct(
        private readonly string $nazev,
    ) {
    }

    /**
     * Nečeká: když zámek drží jiný běh, vrátí hned false. Joby, které ho používají, mají na svou práci
     * okno hodin až dní a jejich další běh ji dodělá; čekáním by druhý běh jen držel request.
     */
    public function zamkni(): bool
    {
        if ($this->spojeni !== null) {
            throw new \LogicException("Zámek jobu '{$this->nazev}' už tento běh drží");
        }

        $spojeni = dbConnectTemporary(selectDb: false);
        try {
            $vysledek = dbQuery(<<<'SQL'
                SELECT GET_LOCK($0, 0)
                SQL,
                [0 => $this->nazevNaServeru()],
                $spojeni,
            );
            $zamceno = mysqli_fetch_column($vysledek);
        } catch (\Throwable $chyba) {
            mysqli_close($spojeni);
            throw $chyba;
        }

        if ($zamceno === null || $zamceno === false) {
            mysqli_close($spojeni);
            throw new \RuntimeException("Zámek jobu '{$this->nazev}' se nepodařilo získat, GET_LOCK skončil chybou");
        }
        if ((int) $zamceno !== 1) {
            mysqli_close($spojeni);

            return false;
        }

        $this->spojeni = $spojeni;

        return true;
    }

    public function odemkni(): void
    {
        if ($this->spojeni === null) {
            return;
        }

        $spojeni       = $this->spojeni;
        $this->spojeni = null;
        try {
            // Výslovně, ne jen zavřením spojení: odpojení server zpracuje až po tom, co se klient odpojí,
            // takže běh, který přijde hned po nás, by mohl zámek ještě najít obsazený.
            dbQuery(<<<'SQL'
                DO RELEASE_LOCK($0)
                SQL,
                [0 => $this->nazevNaServeru()],
                $spojeni,
            );
        } finally {
            mysqli_close($spojeni);
        }
    }

    private function nazevNaServeru(): string
    {
        // MariaDB bere název zámku nejvýš o 64 znacích, a jen jméno testovací databáze jich má přes 40.
        return 'gamecon-job-' . sha1(DB_NAME . '|' . $this->nazev);
    }
}
