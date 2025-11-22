<?php

namespace Gamecon\Aktivita;

use Gamecon\Aktivita\SqlStruktura\AkceSeznamSqlStruktura as Sql;
use Gamecon\Cas\DateTimeCz;
use Gamecon\Pravo;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\XTemplate\XTemplate;
use Uzivatel;
use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\EditorTagu;
use Gamecon\Aktivita\TypAktivity;
use Gamecon\Aktivita\Program;

// todo: tenhle soubor by měl asi jít do adminu ?

class AktivitaEditor
{

    /**
     * Vrátí HTML kód editoru aktivit určený pro vytváření a editaci aktivity.
     * Podle nastavení $a buď aktivitu edituje nebo vytváří.
     * @todo Zkusit refaktorovat editor na samostatnou třídu, pokud to půjde bez
     * vytvoření závislostí na vnitřní proměnné aktivity.
     */
    public static function editor(
        SystemoveNastaveni $systemoveNastaveni,
        ?Aktivita          $a = null,
    ) {
        return self::vytvorEditorAktivity(
            editorTagu: new EditorTagu($systemoveNastaveni->db()),
            aktivita: $a,
            systemoveNastaveni: $systemoveNastaveni,
        );
    }

    /**
     * Vrátí html kód editoru, je možné parametrizovat, co se pomocí něj dá měnit
     */
    protected static function vytvorEditorAktivity(
        EditorTagu         $editorTagu,
        ?Aktivita          $aktivita,
        SystemoveNastaveni $systemoveNastaveni = null,
    ) {
        // inicializace šablony
        $xtpl = new XTemplate(__DIR__ . '/templates/editor-aktivity.xtpl');
        $xtpl->assign('fields', Aktivita::POST_KLIC); // název proměnné (pole) v kterém se mají posílat věci z formuláře
        $xtpl->assign('ajaxKlic', Aktivita::AJAX_KLIC);
        $xtpl->assign('obrKlic', Aktivita::OBRAZEK_KLIC);
        $xtpl->assign('obrKlicUrl', Aktivita::OBRAZEK_KLIC . 'Url');
        $xtpl->assign('aEditTag', Aktivita::TAGY_KLIC);
        $xtpl->assign('limitPopisKratky', Aktivita::LIMIT_POPIS_KRATKY);
        $xtpl->assign('typBrigadnicka', TypAktivity::BRIGADNICKA);

        if ($aktivita) {
            $aktivitaData = $aktivita->dejData(); // databázový řádek
            $xtpl->assign($aktivitaData);
            $xtpl->assign(Sql::POPIS, $aktivitaData[Sql::POPIS]);
            $xtpl->assign('urlObrazku', $aktivita->obrazek());
            $xtpl->assign(Sql::VYBAVENI, $aktivita->vybaveni());
        }

        AktivitaEditor::parseUpravyTabulkaTagy($aktivita, $editorTagu, $xtpl);
        AktivitaEditor::parseUpravyTabulkaLokace($aktivita, $xtpl);
        AktivitaEditor::parseUpravyTabulkaDeti(aktivita: $aktivita, xtpl: $xtpl, systemoveNastaveni: $systemoveNastaveni);
        AktivitaEditor::parseUpravyTabulkaRodice(aktivita: $aktivita, xtpl: $xtpl, systemoveNastaveni: $systemoveNastaveni);

        // editace dnů + časů
        // načtení dnů
        AktivitaEditor::parseUpravyTabulkaDen($aktivita, $xtpl); // načtení časů
        AktivitaEditor::parseUpravyTabulkaZacatekAKonec($aktivita, $xtpl);

        // načtení organizátorů
        AktivitaEditor::parseUpravyTabulkaVypraveci($aktivita, $xtpl);

        // načtení typů
        AktivitaEditor::parseUpravyTabulkaTypy($aktivita, $xtpl);

        // výstup
        $xtpl->parse('upravy.tabulka');

        if (Uzivatel::zSession()->maPravo(Pravo::PROVADI_KOREKCE)) {
            $xtpl->parse('upravy.checkboxKorekce');
        }
        $xtpl->parse('upravy');

        return $xtpl->text('upravy');
    }

    /**
     * @param array<string> $vybraneTagy
     */
    private static function parseUpravyTabulkaTagy(
        ?Aktivita  $aktivita,
        EditorTagu $editorTagu,
        XTemplate  $xtpl,
    ): void {
        $vybraneTagy = $aktivita?->tagy() ?? [];
        $vsechnyTagy = $editorTagu->getTagy();
        $pocetVsechTagu = count($vsechnyTagy);
        $nazevPredchoziKategorie = null;
        foreach ($vsechnyTagy as $indexTagu => $mappedTag) {
            $encodedTag = [];
            foreach ($mappedTag as $tagKey => $tagValue) {
                $encodedTag[$tagKey] = htmlspecialchars($tagValue, ENT_QUOTES | ENT_HTML5);
            }
            $jeNovaKategorie = $nazevPredchoziKategorie !== $mappedTag['nazev_kategorie'];
            $xtpl->assign('id_tagu', $encodedTag['id']);
            $xtpl->assign('nazev_tagu', $encodedTag['nazev']);
            $xtpl->assign('tag_selected', in_array($encodedTag['nazev'], $vybraneTagy, true)
                ? 'selected'
                : '');
            $xtpl->assign(
                'previous_optgroup_tag_end',
                $jeNovaKategorie && $nazevPredchoziKategorie !== null
                    ? '</optgroup>'
                    : '',
            );
            $xtpl->assign(
                'optgroup_tag_start',
                $jeNovaKategorie
                    ? '<optgroup label="' . mb_ucfirst($encodedTag['nazev_kategorie']) . '">'
                    : '',
            );
            $xtpl->assign(
                'last_optgroup_tag_end',
                $indexTagu + 1 === $pocetVsechTagu
                    ? '</optgroup>'
                    : '',
            );
            $xtpl->parse('upravy.tabulka.tag');
            $nazevPredchoziKategorie = $mappedTag['nazev_kategorie'];
        }
    }

    private static function parseUpravyTabulkaLokace(
        ?Aktivita $aktivita,
        XTemplate $xtpl,
    ): void {
        $aktivitaData = $aktivita?->dejData(); // databázový řádek
        $xtpl->assign(['id_lokace' => null, 'nazev' => '(žádná)', 'selected' => '']);
        $xtpl->parse('upravy.tabulka.lokace');
        $q = dbQuery('SELECT id_lokace, nazev FROM akce_lokace ORDER BY poradi');
        while ($lokaceData = mysqli_fetch_assoc($q)) {
            $xtpl->assign('selected', $aktivita && $aktivitaData[Sql::LOKACE] == $lokaceData['id_lokace']
                ? 'selected'
                : '');
            $xtpl->assign($lokaceData);
            $xtpl->parse('upravy.tabulka.lokace');
        }
    }


    private static function parseUpravyTabulkaDeti(
        ?Aktivita           $aktivita,
        XTemplate           $xtpl,
        ?SystemoveNastaveni $systemoveNastaveni,
    ): void {
        $q = dbQuery(
            "SELECT id_akce FROM akce_seznam WHERE id_akce != $1 AND rok = $2 ORDER BY nazev_akce",
            [1 => $aktivita?->id(), 2 => ROCNIK],
        );
        $detiIds = $aktivita
            ? $aktivita->detiIds()
            : [];
        while ($mozneDiteData = mysqli_fetch_assoc($q)) {
            $mozneDiteId = $mozneDiteData[Sql::ID_AKCE];
            $xtpl->assign(
                'selected',
                in_array($mozneDiteId, $detiIds, false)
                    ? 'selected'
                    : '',
            );
            $mozneDite = Aktivita::zId(
                id: $mozneDiteId,
                zCache: true,
                systemoveNastaveni: $systemoveNastaveni,
            );
            $xtpl->assign('id_ditete', $mozneDiteId);
            $xtpl->assign('nazev_ditete', Aktivita::dejRozsirenyNazevAktivity($mozneDite));
            $xtpl->parse('upravy.tabulka.dite');
        }
    }


    private static function parseUpravyTabulkaRodice(
        ?Aktivita           $aktivita,
        XTemplate           $xtpl,
        ?SystemoveNastaveni $systemoveNastaveni,
    ): void {
        $q = dbQuery(
            "SELECT id_akce FROM akce_seznam WHERE id_akce != $1 AND rok = $2 ORDER BY nazev_akce",
            [1 => $aktivita?->id(), 2 => ROCNIK],
        );
        while ($moznyRodicData = mysqli_fetch_assoc($q)) {
            $moznyRodicId = $moznyRodicData[Sql::ID_AKCE];
            $moznyRodic = Aktivita::zId(id: $moznyRodicId, zCache: true, systemoveNastaveni: $systemoveNastaveni);
            $xtpl->assign(
                'selected',
                $aktivita && $moznyRodic->maDite($aktivita->id())
                    ? 'selected'
                    : '',
            );
            $xtpl->assign('id_rodice', $moznyRodicId);
            $xtpl->assign('nazev_rodice', Aktivita::dejRozsirenyNazevAktivity($moznyRodic));
            $xtpl->parse('upravy.tabulka.rodic');
        }
    }

    private static function parseUpravyTabulkaDen(
        ?Aktivita $aktivita,
        XTemplate $xtpl,
    ) {
        $denAktivity = $aktivita?->denProgramu();
        $xtpl->assign([
            'selected' => $aktivita && !$aktivita->zacatek()
                ? 'selected'
                : '',
            'den'      => 0,
            'denSlovy' => '?',
        ]);
        $xtpl->parse('upravy.tabulka.den');
        for ($den = new DateTimeCz(PROGRAM_OD); $den->pred(PROGRAM_DO); $den->plusDen()) {
            $xtpl->assign([
                'selected' => $aktivita && $den->stejnyDen($denAktivity)
                    ? 'selected'
                    : '',
                'den'      => $den->format('Y-m-d'),
                'denSlovy' => $den->format('l'),
            ]);
            $xtpl->parse('upravy.tabulka.den');
        }
    }


    private static function parseUpravyTabulkaZacatekAKonec(
        ?Aktivita $aktivita,
        XTemplate $xtpl,
    ) {
        $aZacatek = $aktivita && $aktivita->zacatek()
            ? (int)$aktivita->zacatek()->format('G')
            : null;
        $aKonec = $aktivita && $aktivita->konec()
            ? (int)$aktivita->konec()->sub(new \DateInterval('PT1H'))->format('G')
            : null; // kontrola přehoupnutí přes půlnoc
        $hodinyZacatku = Program::seznamHodinZacatku();

        array_unshift($hodinyZacatku, null);
        foreach ($hodinyZacatku as $hodinaZacatku) {
            $xtpl->assign('selected', $aZacatek === $hodinaZacatku
                ? 'selected'
                : '');
            if ($hodinaZacatku === 0) {
                $xtpl->assign(Sql::ZACATEK, "24");
                $xtpl->assign('zacatekSlovy', '24:00');
            } else {
                $xtpl->assign(Sql::ZACATEK, $hodinaZacatku);
                $xtpl->assign('zacatekSlovy', $hodinaZacatku !== null
                    ? ($hodinaZacatku . ':00')
                    : '?');
            }
            $xtpl->parse('upravy.tabulka.zacatek');

            $xtpl->assign('selected', $aKonec === $hodinaZacatku
                ? 'selected'
                : '');
            $xtpl->assign(Sql::KONEC, ($hodinaZacatku !== null
                ? $hodinaZacatku + 1
                : null));
            $xtpl->assign('konecSlovy', $hodinaZacatku !== null
                ? (($hodinaZacatku + 1) . ':00')
                : '?');
            $xtpl->parse('upravy.tabulka.konec');
        }
    }


    private static function parseUpravyTabulkaVypraveci(
        ?Aktivita $aktivita,
        XTemplate $xtpl,
    ): void {
        $q = dbQuery(
            <<<SQL
                SELECT u.id_uzivatele, u.login_uzivatele, u.jmeno_uzivatele, u.prijmeni_uzivatele
                FROM uzivatele_hodnoty u
                JOIN platne_role_uzivatelu
                    ON u.id_uzivatele = platne_role_uzivatelu.id_uzivatele
                JOIN prava_role
                    ON platne_role_uzivatelu.id_role = prava_role.id_role
                WHERE prava_role.id_prava = $0
                GROUP BY u.login_uzivatele
                ORDER BY u.login_uzivatele
            SQL,
            [
                0 => Pravo::PORADANI_AKTIVIT,
            ],
        );

        $vsichniOrg = [];
        while ($uzivatelData = mysqli_fetch_assoc($q)) {
            $vsichniOrg[$uzivatelData['id_uzivatele']] = Uzivatel::jmenoNickZjisti($uzivatelData);
        }
        $aktOrg = $aktivita
            ? array_map(static function (
                Uzivatel $e,
            ) {
                return (int)$e->id();
            }, $aktivita->organizatori())
            : [];
        $aktOrg[] = 0; // poslední pole má selected 0 (žádný org)
        foreach ($vsichniOrg as $id => $org) {
            if (in_array($id, $aktOrg, false)) {
                $xtpl->assign('vypravecSelected', 'selected');
            } else {
                $xtpl->assign('vypravecSelected', '');
            }
            $xtpl->assign('vypravecId', $id);
            $xtpl->assign('vypravecJmeno', $org);
            $xtpl->parse('upravy.tabulka.vypraveci.vypravec');
        }
        $xtpl->parse('upravy.tabulka.vypraveci');
    }


    private static function parseUpravyTabulkaTypy(
        ?Aktivita $aktivita,
        XTemplate $xtpl,
    ) {
        $aktivitaData = $aktivita?->dejData(); // databázový řádek
        // typ s id 0 je (bez typu – organizační) a ten chceme první
        $sKladnymPoradim = dbFetchAll('SELECT id_typu, typ_1p FROM akce_typy WHERE aktivni = 1 AND (poradi > 0 OR id_typu = 0) ORDER BY poradi');
        // typy se záporným pořadím jsou technické, brigádnické a tak
        $seZapornymPoradim = dbFetchAll('SELECT id_typu, typ_1p FROM akce_typy WHERE aktivni = 1 AND poradi < 0 AND id_typu != 0 ORDER BY poradi DESC');
        foreach (array_merge($sKladnymPoradim, $seZapornymPoradim) as $akceTypData) {
            $xtpl->assign('selected', $aktivita && $akceTypData['id_typu'] == $aktivitaData[Sql::TYP]
                ? 'selected'
                : '');
            $xtpl->assign($akceTypData);
            $xtpl->parse('upravy.tabulka.typ');
        }
    }
}
