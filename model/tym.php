<?php

use Gamecon\Aktivita\Aktivita;
use Gamecon\XTemplate\XTemplate;

/**
 * Abstrakce týmu na aktivitě
 */
class Tym
{

    private $a; // (primární) aktivita ke které tým patří
    private $r; // db řádek s aktivitou
    private ?array $clenove = null;

    private static $aktivityId;

    const POST = 'cTym'; // výchozí název post proměnné pro formuláře

    /**
     * Toto je pouze rychle nahákovaný způsob vytváření týmu. Pokud bychom ho
     * používali na více místech, je potřeba vymyslet jak správně ukládat věci
     * v db a jak je spolu s aktivitou ne/tahat (viz také orm)
     */
    function __construct(Aktivita $a, array $r)
    {
        $this->a = $a;
        $this->r = $r;
    }

    /** Vrací číslo družiny (zvyk z DrD) a to generuje z ID aktivity */
    function cislo()
    {
        $typ = $this->a->typId();
        if (!isset(self::$aktivityId[$typ])) {
            self::$aktivityId[$typ] = explode(',', dbOneCol(
                'SELECT GROUP_CONCAT(id_akce) FROM akce_seznam WHERE typ = $1 AND rok = $2', [$typ, ROCNIK],
            ));
        }
        return array_search($this->a->id(), self::$aktivityId[$typ]) + 1;
    }

    function clenove(): array
    {
        if (!isset($this->clenove)) {
            $this->clenove = $this->a->prihlaseni();
        }
        return $this->clenove;
    }

    function kapacita()
    {
        return $this->r['kapacita']; // u týmovek nepodporujeme rozdělení ž/m
    }

    private function maxKapacita()
    {
        return $this->r['team_max'];
    }

    private function minKapacita()
    {
        return $this->r['team_min'];
    }

    function nazev()
    {
        return $this->r['team_nazev'];
    }

    private function volnych()
    {
        return $this->kapacita() - count($this->clenove());
    }

    /** Výpis členů týmu s ovládáním (html) */
    function vypis($post = self::POST)
    {
        $t = new XTemplate(__DIR__ . '/tym-vypis.xtpl');
        $t->parseEach($this->clenove(), 'u', 'vypis.prihlaseny');
        $t->assign([
            'post' => $post,
            'mist' => cislo($this->volnych(), ' volné místo', ' volná místa', ' volných míst'),
            'id'   => $this->a->id(),
        ]);
        if ($this->kapacita() > $this->minKapacita() && $this->volnych() > 0)
            $t->parse('vypis.odebrat');
        if ($this->kapacita() < $this->maxKapacita())
            $t->parse('vypis.pridat');
        $t->parse('vypis');
        return $t->text('vypis');
    }

    static function vypisZpracuj(Uzivatel $u = null, $post = self::POST)
    {
        if (!$u) {
            return;
        }
        if (post($post, 'id')) {
            $tym = Aktivita::zId(post($post, 'id'))->tym();
            if (!$tym->a->prihlasen($u)) {
                throw new Exception('Nelze měnit cizí týmy');
            }
            if (post($post, 'odebrat')) {
                if ($tym->kapacita() <= $tym->minKapacita()) {
                    throw new Exception('Kapacita týmu nelze snížit, už je minimální');
                }
                dbQuery('UPDATE akce_seznam SET team_limit = $2 - 1 WHERE id_akce = $1', [post($post, 'id'), $tym->kapacita()]);
            }
            if (post($post, 'pridat')) {
                if ($tym->kapacita() >= $tym->maxKapacita()) {
                    throw new Exception('Kapacita týmu nelze zvýšit, už je maximální');
                }
                dbQuery('UPDATE akce_seznam SET team_limit = $2 + 1 WHERE id_akce = $1', [post($post, 'id'), $tym->kapacita()]);
            }
            back();
        }
    }

}
