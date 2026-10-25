<?php

declare(strict_types=1);

namespace Gamecon\Admin;

use Gamecon\XTemplate\XTemplate;

/**
 * The footer switch of the look and the dialog offering the choice on the first visit.
 * Shared by the admin frame (both looks) and by the online attendance (Moje aktivity), which has no admin frame
 * and is often the only admin page a storyteller sees.
 */
class VzhledAdminuHtml
{
    private const string SABLONY = __DIR__ . '/../../admin/templates';

    public function __construct(private readonly VzhledAdminu $vzhledAdminu)
    {
    }

    /**
     * @param bool $sOznamenim false na stránce, která nevypisuje hlášky (chyba(), oznameni())
     */
    public function dejPrepinac(int $idUzivatele, bool $sOznamenim = true): string
    {
        $vzhled = $this->vzhledAdminu->getVzhled($idUzivatele);
        $sablona = $this->dejSablonu('prepinac-vzhledu.xtpl');
        $sablona->assign([
            'klasickyZvolen' => $vzhled === VzhledAdminuEnum::KLASICKY ? 'true' : 'false',
            'moderniZvolen'  => $vzhled === VzhledAdminuEnum::MODERNI ? 'true' : 'false',
        ]);
        if (! $sOznamenim) {
            $sablona->parse('prepinacVzhledu.bezOznameni');
        }
        $sablona->parse('prepinacVzhledu');

        return $sablona->text('prepinacVzhledu');
    }

    /**
     * @param bool $sOznamenim false na stránce, která nevypisuje hlášky (chyba(), oznameni())
     * @return string empty when the user has chosen already or postponed the choice for this session
     */
    public function dejVyber(int $idUzivatele, bool $sOznamenim = true): string
    {
        if (! $this->vzhledAdminu->isNabidnoutVyber($idUzivatele)) {
            return '';
        }
        $sablona = $this->dejSablonu('vyber-vzhledu.xtpl');
        if (! $sOznamenim) {
            $sablona->parse('vyberVzhledu.bezOznameni');
        }
        $sablona->parse('vyberVzhledu');

        return $sablona->text('vyberVzhledu');
    }

    private function dejSablonu(string $soubor): XTemplate
    {
        $sablona = new XTemplate(self::SABLONY . '/' . $soubor);
        $sablona->assign([
            'vzhledPostKlic'  => VzhledAdminu::POST_KLIC,
            'bezOznameniKlic' => VzhledAdminu::POST_KLIC_BEZ_OZNAMENI,
            'vzhledPozdeji'   => VzhledAdminu::VOLBA_POZDEJI,
            'vzhledKlasicky'  => VzhledAdminuEnum::KLASICKY->value,
            'vzhledModerni'   => VzhledAdminuEnum::MODERNI->value,
        ]);

        return $sablona;
    }
}
