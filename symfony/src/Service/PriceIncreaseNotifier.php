<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Enum\RoleMeaning;
use App\Repository\UserRepository;
use Gamecon\Kanaly\GcMail;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Psr\Log\LoggerInterface;

/**
 * Losing a role re-prices what the customer already ordered, which can turn a free item into
 * a debt they never agreed to. Rare — a handful of cases a year — but it must never happen
 * quietly, so the people who own the money get told.
 */
class PriceIncreaseNotifier
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<int, array{nazev: string, pred: string, po: string}> $zdrazeni keyed by order item id
     */
    public function oznamZdrazeni(User $customer, int $year, array $zdrazeni): void
    {
        if ($zdrazeni === []) {
            return;
        }

        // Doctrine fires postPersist before the commit, so anything escaping from here would
        // roll back the role change that triggered it. Failing to report must never undo the
        // thing being reported.
        try {
            $this->oznam($customer, $year, $zdrazeni);
        } catch (\Throwable $chyba) {
            $this->logger->error('Oznámení o zdražení selhalo.', [
                'user_id'   => $customer->getId(),
                'exception' => $chyba,
            ]);
        }
    }

    /**
     * @param array<int, array{nazev: string, pred: string, po: string}> $zdrazeni
     */
    private function oznam(User $customer, int $year, array $zdrazeni): void
    {
        $prijemci = $this->dejCfoMaily();
        if ($prijemci === []) {
            // Nobody to tell is itself worth recording — the price still went up.
            $this->logger->error('Zdražení objednávky po změně rolí, ale není komu to oznámit (role CFO).', [
                'user_id' => $customer->getId(),
                'year'    => $year,
            ]);

            return;
        }

        $zprava = $this->sestavZpravu($customer, $year, $zdrazeni);
        $predmet = sprintf('Zdražení objednávky po změně rolí: %s', $customer->getJmeno());
        foreach ($prijemci as $email) {
            try {
                $this->odesli($email, $predmet, $zprava);
            } catch (\Throwable $chyba) {
                $this->logger->error('Zdražení objednávky se nepodařilo oznámit.', [
                    'user_id'   => $customer->getId(),
                    'prijemce'  => $email,
                    'exception' => $chyba,
                ]);
            }
        }

        $this->logger->warning('Objednávka zdražena po změně rolí, CFO informováni.', [
            'user_id'  => $customer->getId(),
            'year'     => $year,
            'polozek'  => count($zdrazeni),
            'prijemcu' => count($prijemci),
        ]);
    }

    /**
     * Overridden in tests, which must not reach a real transport.
     */
    protected function odesli(string $email, string $predmet, string $zprava): void
    {
        (new GcMail(SystemoveNastaveni::zGlobals()))
            ->adresat($email)
            ->predmet($predmet)
            ->text($zprava)
            ->odeslat(GcMail::FORMAT_TEXT);
    }

    /**
     * @return string[]
     */
    private function dejCfoMaily(): array
    {
        $maily = [];
        foreach ($this->userRepository->findByRoleMeaning(RoleMeaning::CFO) as $uzivatel) {
            $email = trim($uzivatel->getEmail());
            if ($email !== '') {
                $maily[] = $email;
            }
        }

        return array_values(array_unique($maily));
    }

    /**
     * @param array<int, array{nazev: string, pred: string, po: string}> $zdrazeni
     */
    private function sestavZpravu(User $customer, int $year, array $zdrazeni): string
    {
        $radky = [];
        foreach ($zdrazeni as $polozka) {
            $radky[] = sprintf(
                ' - %s: %s → %s Kč',
                $polozka['nazev'],
                $polozka['pred'],
                $polozka['po'],
            );
        }

        return sprintf(
            "Uživateli %s (ID %d) se po změně rolí zdražily už objednané položky ročníku %d.\n\n%s\n\nAktuální zůstatek: %s\n",
            $customer->getJmeno(),
            (int) $customer->getId(),
            $year,
            implode("\n", $radky),
            $this->dejZustatek($customer),
        );
    }

    /**
     * Overridden in tests: a real call runs a full legacy Finance recompute.
     */
    protected function dejZustatek(User $customer): string
    {
        $legacy = \Uzivatel::zId((int) $customer->getId());
        if ($legacy === null) {
            return 'nepodařilo se zjistit';
        }

        return sprintf('%.2f Kč', $legacy->finance()->stav());
    }
}
