<?php

declare(strict_types=1);

namespace Gamecon\Admin;

/**
 * Which look of the admin a user works in. The choice is kept in the database, so it follows
 * the user to every device; whoever has not chosen yet gets the classic look and an offer to choose.
 */
class VzhledAdminu
{
    public const string POST_KLIC = 'vzhledAdminu';
    /** Not a look: the user postponed the choice, the offer comes back with the next login. */
    public const string VOLBA_POZDEJI = 'pozdeji';
    public const VzhledAdminuEnum VYCHOZI = VzhledAdminuEnum::KLASICKY;

    private const string SESSION_KLIC_VYBER_ODLOZEN = 'vzhled_adminu_vyber_odlozen';

    /** @var array<int, ?VzhledAdminuEnum> by user id */
    private array $zvolenyPodleUzivatele = [];

    /**
     * @param array<string, mixed> $session the PHP session, by reference so that postponing outlives the request
     */
    public function __construct(private array &$session)
    {
    }

    public function getZvolenyVzhled(int $idUzivatele): ?VzhledAdminuEnum
    {
        if (array_key_exists($idUzivatele, $this->zvolenyPodleUzivatele)) {
            return $this->zvolenyPodleUzivatele[$idUzivatele];
        }

        $hodnota = dbOneCol(<<<'SQL'
SELECT vzhled FROM uzivatele_vzhled_adminu WHERE id_uzivatele = $0
SQL,
            [0 => $idUzivatele],
        );

        // A value this version does not know (left by a newer one) counts as not chosen.
        return $this->zvolenyPodleUzivatele[$idUzivatele] = $hodnota !== null
            ? VzhledAdminuEnum::tryFrom((string) $hodnota)
            : null;
    }

    public function getVzhled(int $idUzivatele): VzhledAdminuEnum
    {
        return $this->getZvolenyVzhled($idUzivatele) ?? self::VYCHOZI;
    }

    public function setVzhled(int $idUzivatele, VzhledAdminuEnum $vzhled): void
    {
        dbQuery(<<<'SQL'
INSERT INTO uzivatele_vzhled_adminu (id_uzivatele, vzhled)
VALUES ($0, $1)
ON DUPLICATE KEY UPDATE vzhled = VALUES(vzhled)
SQL,
            [
                0 => $idUzivatele,
                1 => $vzhled->value,
            ],
        );

        $this->zvolenyPodleUzivatele[$idUzivatele] = $vzhled;
    }

    public function isNabidnoutVyber(int $idUzivatele): bool
    {
        return $this->getZvolenyVzhled($idUzivatele) === null
               && empty($this->session[self::SESSION_KLIC_VYBER_ODLOZEN]);
    }

    /**
     * Handles a submitted choice of the look, or of postponing it. An unknown value changes nothing.
     *
     * @return VzhledAdminuEnum|null the look just chosen, null when postponed or unknown
     */
    public function zpracujVolbu(int $idUzivatele, string $volba): ?VzhledAdminuEnum
    {
        if ($volba === self::VOLBA_POZDEJI) {
            $this->session[self::SESSION_KLIC_VYBER_ODLOZEN] = true;

            return null;
        }

        $vzhled = VzhledAdminuEnum::tryFrom($volba);
        if ($vzhled !== null) {
            $this->setVzhled($idUzivatele, $vzhled);
        }

        return $vzhled;
    }
}
