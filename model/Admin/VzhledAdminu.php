<?php

declare(strict_types=1);

namespace Gamecon\Admin;

use Gamecon\Uzivatel\PrepnutiNaUzivatele;

/**
 * Which look of the admin a user works in. The choice is kept in the database, so it follows
 * the user to every device; whoever has not chosen yet gets the classic look and an offer to choose.
 * After "Přihlásit se jako" the admin shows the look of the user switched to, but whoever sits at the device
 * neither chooses it for them nor counts as them in the reports (@see isZaJinehoUzivatele()).
 */
class VzhledAdminu
{
    public const string POST_KLIC = 'vzhledAdminu';
    /**
     * Not a look: the user postponed the choice, the offer comes back with the next login.
     */
    public const string VOLBA_POZDEJI = 'pozdeji';
    public const VzhledAdminuEnum VYCHOZI = VzhledAdminuEnum::KLASICKY;

    /**
     * One flag for the whole session, not per user: postponing is the wish of whoever sits at the device. After
     * "Přihlásit se jako" it is still them, and the dialog must not push them to choose for the other user.
     */
    private const string SESSION_KLIC_VYBER_ODLOZEN = 'vzhled_adminu_vyber_odlozen';

    /**
     * Within one request only, a look chosen on another device shows on the next request.
     *
     * @var array<int, ?VzhledAdminuEnum> by user id
     */
    private array $zvolenyPodleUzivatele = [];

    /**
     * @param array<string, mixed> $session the PHP session, by reference so that postponing outlives the request
     */
    public function __construct(
        private array &$session,
    ) {
    }

    public function getZvolenyVzhled(int $idUzivatele): ?VzhledAdminuEnum
    {
        if (array_key_exists($idUzivatele, $this->zvolenyPodleUzivatele)) {
            return $this->zvolenyPodleUzivatele[$idUzivatele];
        }

        $hodnota = dbOneCol(<<<'SQL'
SELECT vzhled FROM uzivatele_vzhled_adminu WHERE id_uzivatele = $0
SQL,
            [
                0 => $idUzivatele,
            ],
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

    /**
     * Counts a page the user has just seen, per day and look, so the reports show how the looks caught on.
     * A user who has not chosen yet is counted in the default look, marked as not chosen.
     */
    public function zaznamenejZobrazeniStranky(int $idUzivatele, \DateTimeInterface $kdy): void
    {
        if ($this->isZaJinehoUzivatele()) {
            return;
        }
        $zvolenyVzhled = $this->getZvolenyVzhled($idUzivatele);
        dbQuery(<<<'SQL'
INSERT INTO uzivatele_vzhled_adminu_pouziti (id_uzivatele, den, vzhled, zvoleny, pocet_zobrazeni)
VALUES ($0, $1, $2, $3, 1)
ON DUPLICATE KEY UPDATE
    pocet_zobrazeni = pocet_zobrazeni + 1,
    zvoleny = GREATEST(zvoleny, VALUES(zvoleny))
SQL,
            [
                0 => $idUzivatele,
                1 => $kdy->format('Y-m-d'),
                2 => ($zvolenyVzhled ?? self::VYCHOZI)->value,
                3 => $zvolenyVzhled !== null ? 1 : 0,
            ],
        );
    }

    /**
     * Whether the request is the user opening a page, not an ajax call or a form being sent (that redirects
     * to a page, which counts on its own). Browsers without Sec-Fetch-Dest count unless they say it is ajax.
     *
     * @param array<string, mixed> $server $_SERVER
     */
    public static function isZobrazeniStranky(array $server): bool
    {
        return ($server['REQUEST_METHOD'] ?? 'GET') === 'GET'
               && ($server['HTTP_SEC_FETCH_DEST'] ?? 'document') === 'document'
               && empty($server['HTTP_X_REQUESTED_WITH']);
    }

    public function isNabidnoutVyber(int $idUzivatele): bool
    {
        return $this->getZvolenyVzhled($idUzivatele) === null
               && empty($this->session[self::SESSION_KLIC_VYBER_ODLOZEN])
               && ! $this->isZaJinehoUzivatele();
    }

    /**
     * Whether whoever sits at the device switched to the logged-in user ("Přihlásit se jako"), typically an infopult
     * member filling in the attendance of a storyteller. Neither the dialog nor the footer switch is offered then:
     * the dialog would cover the other user's pages, Moje aktivity with the attendance included, and the choice
     * would be saved into the other user's account. Their page views do not count as the other user's either.
     */
    public function isZaJinehoUzivatele(): bool
    {
        return (new PrepnutiNaUzivatele($this->session))->isPrepnuto();
    }

    /**
     * Handles a submitted choice of the look, or of postponing it. An unknown value changes nothing, and neither
     * does any value sent for another user (@see isZaJinehoUzivatele()).
     *
     * @return VzhledAdminuEnum|null the look just chosen, null when postponed, unknown or for another user
     */
    public function zpracujVolbu(int $idUzivatele, string $volba): ?VzhledAdminuEnum
    {
        if ($this->isZaJinehoUzivatele()) {
            return null;
        }
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
