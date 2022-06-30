<?php

class UbytovaniTabulka {
  private static function htmlDny(ShopUbytovani $shop, XTemplate $t) {
    foreach ($shop->dny as $den => $typy) { // typy _v daný den_
        $ubytovan = false;
        $typVzor = reset($typy);
        $t->assign('postnameDen', $shop->pnDny . '[' . $den . ']');
        foreach ($shop->typy as $typ => $rozsah) {
            $checked = '';
            if ($shop->ubytovan($den, $typ)) {
                $ubytovan = true;
                $checked = 'checked';
            }
            $obsazeno = $shop->obsazenoMist($den, $typ);
            $kapacita = $shop->kapacita($den, $typ);
            $zbyvaMist = $shop->zbyvaMist($den, $typ);

            $t->assign([
                'idPredmetu' => isset($shop->dny[$den][$typ]) ? $shop->dny[$den][$typ]['id_predmetu'] : null,
                'checked' => $checked,
                'disabled' => !$ubytovan && (!$shop->existujeUbytovani($den, $typ) || $shop->plno($den, $typ)) ? 'disabled' : '',
                'obsazeno' => $obsazeno,
                'kapacita' => $kapacita,
                'zbyvaMist' => $zbyvaMist,
                'labelClass' => $zbyvaMist < 11 ? "radio--container--warning" : "",
            ])->parse('ubytovani.den.typ');
        }
        $denText = mb_ucfirst(substr($typVzor['nazev'], strrpos($typVzor['nazev'], ' ') + 1));
        $t->assign([
            'den' => $denText,
            'denZkratka' => mb_substr($denText, 0 ,2),
            'checked' => $ubytovan ? '' : 'checked',
            'disabled' => $ubytovan && $typVzor['stav'] == 3 && !$typVzor['nabizet'] ? 'disabled' : '',
        ])->parse('ubytovani.den');
    }
  }
  
  public static function ubytovaniTabulkaZ(ShopUbytovani $shop) {
          $t = new XTemplate(__DIR__ . '/_ubytovani_tabulka.xtpl');
          $t->assign([
              'spolubydlici' => dbOneCol('SELECT ubytovan_s FROM uzivatele_hodnoty WHERE id_uzivatele=' . $shop->u->id()),
              'postnameSpolubydlici' => $shop->pnPokoj,
              'uzivatele' => $shop->mozniUzivatele(),
          ]);
          UbytovaniTabulka::htmlDny($shop, $t);
          // sloupce popisků
          foreach ($shop->typy as $typ => $predmet) {
              $t->assign([
                  'typ' => $typ,
                  'hint' => $predmet['popis'],
                  'cena' => round($predmet['cena_aktualni']),
              ]);
              $t->parse($predmet['popis'] ? 'ubytovani.typ.hinted' : 'ubytovani.typ.normal');
              $t->parse('ubytovani.typ');
          }
  
          // specifická info podle uživatele a stavu nabídky
          if (reset($shop->typy)['stav'] == 3) {
              $t->parse('ubytovani.konec');
          }
  
          $t->parse('ubytovani');
          return $t->text('ubytovani');
      }

}

