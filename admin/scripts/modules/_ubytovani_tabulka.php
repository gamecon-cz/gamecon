<?php

use Gamecon\Shop\ShopUbytovani;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Shop\Shop;
use Gamecon\XTemplate\XTemplate;

class UbytovaniTabulka
{
    private static function htmlDny(
        ShopUbytovani      $shop,
        XTemplate          $t,
        SystemoveNastaveni $systemoveNastaveni,
        bool               $muzeEditovatUkoncenyProdej
    ) {
        $prodejUbytovaniUkoncen = !$muzeEditovatUkoncenyProdej && $systemoveNastaveni->prodejUbytovaniUkoncen();
        foreach ($shop->mozneDny() as $den => $typy) { // typy _v daný den_
            $typVzor = reset($typy);
            $t->assign('postnameDen', $shop->postnameDen() . '[' . $den . ']');
            $ubytovanVeDni = false;
            foreach ($shop->mozneTypy() as $typ => $rozsah) {
                $ubytovanVeDniATypu = false;
                $checked = '';
                if ($shop->ubytovan($den, $typ)) {
                    $ubytovanVeDniATypu = true;
                    $checked = 'checked';
                }
                $ubytovanVeDni = $ubytovanVeDni || $ubytovanVeDniATypu;

                $obsazeno = $shop->obsazenoMist($den, $typ);
                $kapacita = $shop->kapacita($den, $typ);
                $zbyvaMist = $shop->zbyvaMist($den, $typ);

                $t->assign([
                    'idPredmetu' => $shop->mozneDny()[$den][$typ]['id_predmetu'] ?? null,
                    'checked' => $checked,
                    'disabled' => !$checked // GUI neumí checked disabled, tak nesmíme dát disabled, když je chcecked
                    && ($prodejUbytovaniUkoncen
                        || (!$ubytovanVeDni && (!$shop->existujeUbytovani($den, $typ) || $shop->plno($den, $typ)))
                    )
                        ? 'disabled'
                        : '',
                    'obsazeno' => $obsazeno,
                    'kapacita' => $kapacita,
                    'zbyvaMist' => $zbyvaMist,
                ])->parse('ubytovani.den.typ');
            }
            // data pro názvy dnů a pro "Žádné" ubytování
            $denText = mb_ucfirst(substr($typVzor['nazev'], strrpos($typVzor['nazev'], ' ') + 1));
            $t->assign([
                'den' => $denText,
                'denZkratka' => mb_substr($denText, 0, 2),
                'checked' => $ubytovanVeDni ? '' : 'checked', // checked = "Žádné" ubytování
                'disabled' => $prodejUbytovaniUkoncen || ( $ubytovanVeDni && $typVzor['stav'] == Shop::STAV_POZASTAVENY && !$typVzor['nabizet'])
                    ? 'disabled'
                    : '',
            ])->parse('ubytovani.den');
        }
    }

    public static function ubytovaniTabulkaZ(
        ShopUbytovani      $shop,
        SystemoveNastaveni $systemoveNastaveni,
        bool               $muzeEditovatUkoncenyProdej
    ) {
        $t = new XTemplate(__DIR__ . '/_ubytovani_tabulka.xtpl');
        self::htmlDny($shop, $t, $systemoveNastaveni, $muzeEditovatUkoncenyProdej);
        // sloupce popisků
        $prvniUbytovani = null;
        foreach ($shop->mozneTypy() as $typ => $ubytovani) {
            $prvniUbytovani = $prvniUbytovani ?? $ubytovani;
            $t->assign([
                'typ' => $typ,
                'hint' => $ubytovani['popis'],
                'cena' => round($ubytovani['cena_aktualni']),
            ]);
            $t->parse($ubytovani['popis'] ? 'ubytovani.typ.hinted' : 'ubytovani.typ.normal');
            $t->parse('ubytovani.typ');
        }

        // specifická info podle uživatele a stavu nabídky
        if ((!$muzeEditovatUkoncenyProdej && $systemoveNastaveni->prodejUbytovaniUkoncen())
            || ($prvniUbytovani['stav'] ?? null) == Shop::STAV_POZASTAVENY
        ) {
            $t->parse('ubytovani.konec');
        }

        $t->parse('ubytovani');
        return $t->text('ubytovani');
    }

}

