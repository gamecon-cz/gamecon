<?php

declare(strict_types=1);

namespace Gamecon\Kanaly\EmailovaSablona;

use Gamecon\XTemplate\XTemplate;

/**
 * Form for editing the subject and text of an e-mail, shared by all templates and by the custom reminder wording.
 *
 * The symbol list for suggestions and the Insert symbol button comes from here, from the
 * definition and the system values, not from a copy in JavaScript.
 *
 * @see /admin/files/editor-sablony.js
 */
class EditorSablonyHtml
{
    private const ADRESAR_ADMIN_FILES = __DIR__ . '/../../../admin/files';

    public function __construct(
        private readonly SystemoveHodnotyProSablony $systemoveHodnoty,
    ) {
    }

    /**
     * @param list<string> $chyby errors from the server when saving failed
     * @param string $nahledUrl where the editor sends the text being typed and where it expects the HTML preview from
     * @param string|null $testUrl where the editor sends the text to be mailed as a test, with $testEmail the address
     *     it goes to (the logged-in admin); without both the Send test button is not offered
     * @param string|null $schrankaUrl where the Open mailbox button leads, on an environment that catches e-mails;
     *     without it the button is not offered
     * @see \Gamecon\Kanaly\ZachytavacEmailu::getUrl()
     */
    public function vykresli(
        EmailovaSablonaDefinice $definice,
        string                  $predmet,
        string                  $text,
        array                   $chyby,
        string                  $nahledUrl,
        string                  $zpetUrl,
        string                  $zpetNazev,
        ?string                 $testUrl = null,
        ?string                 $testEmail = null,
        ?string                 $schrankaUrl = null,
    ): string {
        $sablona = new XTemplate(__DIR__ . '/templates/editor.xtpl');
        $sablona->assign([
            'predmet'      => htmlspecialchars($predmet, ENT_QUOTES),
            'text'         => htmlspecialchars($text, ENT_QUOTES),
            'nahledUrl'    => htmlspecialchars($nahledUrl, ENT_QUOTES),
            'testUrl'      => htmlspecialchars((string) $testUrl, ENT_QUOTES),
            'testEmail'    => htmlspecialchars((string) $testEmail, ENT_QUOTES),
            'schrankaUrl'  => htmlspecialchars((string) $schrankaUrl, ENT_QUOTES),
            'zpetUrl'      => htmlspecialchars($zpetUrl, ENT_QUOTES),
            'zpetNazev'    => htmlspecialchars($zpetNazev, ENT_QUOTES),
            'dataJson'     => $this->dataJson($definice),
            'cssVerze'     => md5_file(self::ADRESAR_ADMIN_FILES . '/editor-sablony.css'),
            'logikaVerze'  => md5_file(self::ADRESAR_ADMIN_FILES . '/editor-sablony-logika.js'),
            'scriptVerze'  => md5_file(self::ADRESAR_ADMIN_FILES . '/editor-sablony.js'),
        ]);

        foreach ($chyby as $chyba) {
            $sablona->assign('chyba', htmlspecialchars($chyba, ENT_QUOTES));
            $sablona->parse('editor.chyby.chyba');
        }
        if ($chyby !== []) {
            $sablona->parse('editor.chyby');
        }
        if ($testUrl !== null && $testEmail !== null && $testEmail !== '') {
            $sablona->parse('editor.testTlacitko');
            $sablona->parse('editor.testDialog');
        }
        if ($schrankaUrl !== null && $schrankaUrl !== '') {
            $sablona->parse('editor.schrankaTlacitko');
        }

        $sablona->parse('editor');

        return $sablona->text('editor');
    }

    /**
     * The same data the editor receives, usable elsewhere too (a test, for one).
     *
     * @return array{
     *     promenne: list<array{nazev: string, popis: string, ukazka: string, vPredmetu: bool}>,
     *     systemove: list<array{nazev: string, popis: string, ukazka: string}>,
     *     neverejne: list<string>,
     * }
     */
    public function data(EmailovaSablonaDefinice $definice): array
    {
        $promenne = [];
        foreach ($definice->promenne as $promenna) {
            $promenne[] = [
                'nazev'     => $promenna->nazev,
                'popis'     => $promenna->popis,
                'ukazka'    => $promenna->ukazka,
                'vPredmetu' => $promenna->vPredmetu,
            ];
        }

        $systemove = [];
        foreach ($this->systemoveHodnoty->povoleneProEditor() as $nazev => $udaje) {
            $systemove[] = [
                'nazev'  => $nazev,
                'popis'  => $udaje['popis'],
                'ukazka' => $udaje['ukazka'],
            ];
        }

        return [
            'promenne'  => $promenne,
            'systemove' => $systemove,
            'neverejne' => $this->systemoveHodnoty->neverejneKlice(),
        ];
    }

    private function dataJson(EmailovaSablonaDefinice $definice): string
    {
        // HEX_* so it can be embedded in <script>: otherwise `</script>` or `<!--` in a description would break the page
        return json_encode(
            $this->data($definice),
            JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR,
        );
    }
}
