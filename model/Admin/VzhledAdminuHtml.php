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

    public function __construct(
        private readonly VzhledAdminu $vzhledAdminu,
    ) {
    }

    public function getPrepinacHtml(int $idUzivatele): string
    {
        $vzhled = $this->vzhledAdminu->getVzhled($idUzivatele);
        $sablona = $this->vytvorSablonu('prepinac-vzhledu.xtpl');
        $sablona->assign([
            'klasickyZvolen' => $vzhled === VzhledAdminuEnum::KLASICKY ? 'true' : 'false',
            'moderniZvolen'  => $vzhled === VzhledAdminuEnum::MODERNI ? 'true' : 'false',
        ]);
        // tmavé barvy umí jen moderní vzhled
        if ($vzhled === VzhledAdminuEnum::MODERNI) {
            $sablona->parse('prepinacVzhledu.barvy');
        }
        $sablona->parse('prepinacVzhledu');

        return $sablona->text('prepinacVzhledu');
    }

    /**
     * @return string empty when the user has chosen already or postponed the choice for this session
     */
    public function getVyberHtml(int $idUzivatele): string
    {
        if (! $this->vzhledAdminu->isNabidnoutVyber($idUzivatele)) {
            return '';
        }
        $sablona = $this->vytvorSablonu('vyber-vzhledu.xtpl');
        $sablona->parse('vyberVzhledu');

        return $sablona->text('vyberVzhledu');
    }

    private function vytvorSablonu(string $soubor): XTemplate
    {
        $sablona = new XTemplate(self::SABLONY . '/' . $soubor);
        $sablona->assign([
            'vzhledPostKlic' => VzhledAdminu::POST_KLIC,
            'vzhledPozdeji'  => VzhledAdminu::VOLBA_POZDEJI,
            'vzhledKlasicky' => VzhledAdminuEnum::KLASICKY->value,
            'vzhledModerni'  => VzhledAdminuEnum::MODERNI->value,
        ]);

        return $sablona;
    }
}
