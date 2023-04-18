<?php

use Gamecon\XTemplate\XTemplate;

/**
 * GUI Element starající se o zobrazení / uložení uživatelova přání pomoct
 */
class Pomoc
{

    private $r;
    private $u;
    private $pn = 'cPomoc'; // název post proměnné vyhrazené pro tuto třídu

    private static $typy; // tabulka s typy pomoci a jejich popisy

    public function __construct(Uzivatel $u)
    {
        $this->u = $u;
        $this->r = dbOneLine('SELECT pomoc_typ, pomoc_vice FROM uzivatele_hodnoty WHERE id_uzivatele = $1', [$this->u->id()]);
        if (!isset(self::$typy)) {
            self::$typy = include __DIR__ . '/pomoc-texty.php';
        }
    }

    public function html()
    {
        $t = new XTemplate(__DIR__ . '/pomoc.xtpl');
        $t->assign([
            'postname'    => $this->pn,
            'a'           => $this->u->koncovkaDlePohlavi(),
            'displayVice' => $this->r['pomoc_typ'] ? 'block' : 'none',
            'vChci'       => $this->r['pomoc_typ'] ? 'checked' : '',
            'vDetail'     => $this->r['pomoc_vice'],
        ]);
        $koncovky   = [
            '{a}'  => $this->u->koncovkaDlePohlavi(),
            '{ka}' => $this->u->koncovkaDlePohlavi() ? 'ka' : '',
            '{ík}' => $this->u->koncovkaDlePohlavi() ? 'ice' : 'ík',
        ];
        $vybranyTyp = $this->r['pomoc_typ'] ?: self::$typy[0][0];

        foreach (self::$typy as $typ) {
            $t->assign([
                'id'      => $typ[0],
                'nazev'   => mb_ucfirst(strtr($typ[1], $koncovky)),
                'popis'   => strtr($typ[2], $koncovky),
                'checked' => $vybranyTyp == $typ[0] ? 'checked' : '',
            ]);
            $t->parse('pomoc.typ');
        }

        $t->parse('pomoc');
        return $t->text('pomoc');
    }

    public function zpracuj()
    {
        if (!isset($_POST[$this->pn])) {
            return;
        }
        $p = $_POST[$this->pn];
        if (empty($p['chci'])) {
            $p['typ']    = null;
            $p['detail'] = null;
        }
        dbUpdate('uzivatele_hodnoty', [
            'pomoc_typ'  => $p['typ'],
            'pomoc_vice' => $p['detail'],
        ], [
            'id_uzivatele' => $this->u->id(),
        ]);
    }

}
