<?php

use Gamecon\XTemplate\XTemplate;
use Gamecon\Uzivatel\SqlStruktura\UzivateleHodnotySqlStruktura as Sql;

/**
 * GUI Element starající se o zobrazení / uložení uživatelova přání pomoct
 */
class Pomoc
{

    private $r;
    private $u;
    private $pn = 'cPomoc'; // název post proměnné vyhrazené pro tuto třídu

    public function __construct(Uzivatel $u)
    {
        $this->u = $u;
        $this->r = dbOneLine('SELECT pomoc_typ, pomoc_vice FROM uzivatele_hodnoty WHERE id_uzivatele = $1', [$this->u->id()]);
    }

    /**
     * Typy pomoci: id (ukládá se do `uzivatele_hodnoty.pomoc_typ`), název a popis v jazyce stránky (prostý text).
     *
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private static function typy(): array
    {
        return [
            ['dobrovolnik', t('prihlaska.pomoc.typ.dobrovolnik'), t('prihlaska.pomoc.typ.dobrovolnik_popis')],
            ['vypravec', t('prihlaska.pomoc.typ.vypravec'), t('prihlaska.pomoc.typ.vypravec_popis')],
            ['organizator', t('prihlaska.pomoc.typ.organizator'), t('prihlaska.pomoc.typ.organizator_popis')],
            ['partner', t('prihlaska.pomoc.typ.partner'), t('prihlaska.pomoc.typ.partner_popis')],
        ];
    }

    public function html(): string
    {
        $t = new XTemplate(__DIR__ . '/pomoc.xtpl');
        $t->assign([
            'postname'    => $this->pn,
            'displayVice' => $this->r['pomoc_typ'] ? 'block' : 'none',
            'vChci'       => $this->r['pomoc_typ'] ? 'checked' : '',
            'vDetail'     => htmlspecialchars((string) $this->r['pomoc_vice']),
            'dobrovolnik' => htmlSVlozkami(
                t('prihlaska.pomoc.dobrovolnik', ['odkaz' => znackaVlozky('odkaz')]),
                ['odkaz' => '<a href="program">' . htmlspecialchars(t('prihlaska.pomoc.dobrovolnik_odkaz')) . '</a>'],
            ),
            'sponzoring'  => htmlSVlozkami(
                t('prihlaska.pomoc.sponzoring', ['odkaz' => znackaVlozky('odkaz')]),
                ['odkaz' => '<a href="pro-sponzory-a-media">' . htmlspecialchars(t('prihlaska.pomoc.sponzoring_odkaz')) . '</a>'],
            ),
            'organizace'  => htmlSVlozkami(
                t('prihlaska.pomoc.organizace', ['odkaz' => znackaVlozky('odkaz')]),
                ['odkaz' => '<a href="organizacni-vypomoc">' . htmlspecialchars(t('prihlaska.pomoc.organizace_odkaz')) . '</a>'],
            ),
        ]);
        $typy       = self::typy();
        $vybranyTyp = $this->r['pomoc_typ'] ?: $typy[0][0];

        foreach ($typy as [$id, $nazev, $popis]) {
            $t->assign([
                'id'      => $id,
                'nazev'   => htmlspecialchars($nazev),
                'popis'   => htmlspecialchars($popis),
                'checked' => $vybranyTyp == $id ? 'checked' : '',
            ]);
            $t->parse('pomoc.typ');
        }

        $t->parse('pomoc');
        return $t->text('pomoc');
    }

    public function zpracuj(): void
    {
        if (!isset($_POST[$this->pn])) {
            return;
        }
        $p = $_POST[$this->pn];
        if (empty($p['chci'])) {
            $p['typ']    = null;
            $p['detail'] = null;
        }
        $novePomoc = [
            Sql::POMOC_TYP  => $p['typ'] ?? '',
            Sql::POMOC_VICE => $p['detail'] ?? '',
        ];
        \Uzivatel::zalogujZmenuOsobnichUdaju(
            $this->u->id(),
            $novePomoc,
            [
                Sql::POMOC_TYP  => $this->u->rawDb()[Sql::POMOC_TYP] ?? null,
                Sql::POMOC_VICE => $this->u->rawDb()[Sql::POMOC_VICE] ?? null,
            ],
            null,
            'web',
        );
        dbUpdate(Sql::UZIVATELE_HODNOTY_TABULKA, $novePomoc, [
            Sql::ID_UZIVATELE => $this->u->id(),
        ]);
    }

}
