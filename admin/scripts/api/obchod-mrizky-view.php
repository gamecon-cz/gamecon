<?php

use Gamecon\Kfc\ObchodMrizkaBunka;
use Gamecon\Kfc\ObchodMrizka;
use Gamecon\Pravo;

/** @var Uzivatel $u */

if (empty($u) || (!$u->maPravo(Pravo::ADMINISTRACE_FINANCE) && !$u->maPravo(Pravo::ADMINISTRACE_PENIZE) && !$u->jeInfopultak() && !$u->jeOrganizator())
) {
    header('HTTP/1.1 403 Forbidden');
    echo '{error: "403 Forbidden"}';
    exit;
}

// TODO: vxužíváno adminem asi by mělo být v adminu (nutno dovymyslet)
// TODO: ObchodMrizka, ObchodMrizkaBunka by měli být asi v nějakém namespace (nepodařilo se mi rozchodit - padá)
// TODO: řešit pomocí joinu nebo view na DB
// TODO: OpenAPI

/*
  type ApiMřížka = {
    id?: number,
    text?: string,
    bunky?: {
      id?: number,
      typ: number,
      text?: string,
      barva?: string,
      barvaText?: string,
      cilId?: number,
    }[],
  }[];

  GET api/obchod-mrizky-view
  response: MřížkaAPI všechno přítomné

  POST api/obchod-mrizky-view
  body: MřížkaAPI

*/

$config = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // TODO: ukladání objektů musí mít správně udělaný escaping a zabezpečení
    $body = postBody();

    // mřížky které nejsou v DB jsou poslány se zaporným ID
    $mapovaniId = [];

    $bunkyRaw = [];

    foreach ($body as &$mrizkaRaw) {
        $idPuvodni = $mrizkaRaw['id'] ?? null;
        if (isset($idPuvodni) && $idPuvodni < 0) {
            unset($mrizkaRaw['id']);
        } else {
            unset($idPuvodni);
        }
        $bunky = $mrizkaRaw['bunky'] ?? [];
        unset($mrizkaRaw['bunky']);

        $mrizka = ObchodMrizka::novy($mrizkaRaw);
        if (isset($idPuvodni)) {
            $mapovaniId[$idPuvodni] = $mrizka->id();
        }

        foreach ($bunky as &$bunkaRaw) {
            $bunkaRaw['mrizka_id'] = $mrizka->id();
            if (isset($bunkaRaw['cilId'])) {
                $bunkaRaw['cil_id'] = $bunkaRaw['cilId'];
            }
            unset($bunkaRaw['cilId']);
            if (isset($bunkaRaw['barvaText'])) {
                $bunkaRaw['barva_text'] = $bunkaRaw['barvaText'];
            }
            unset($bunkaRaw['barvaText']);

            $bunkyRaw[] = $bunkaRaw;
        }
    }

    foreach ($bunkyRaw as &$bunkaRaw) {
        if (isset($bunkaRaw['id']) && $bunkaRaw['id'] < 1) {
            unset($bunkaRaw['id']);
        }
        if (isset($bunkaRaw['cil_id']) && ($bunkaRaw['typ'] == ObchodMrizkaBunka::TYP_STRANKA) && $bunkaRaw['cil_id'] < 0) {
            $bunkaRaw['cil_id'] = $mapovaniId[$bunkaRaw['cil_id']];
        }
        ObchodMrizkaBunka::novy($bunkaRaw);
    }

    return;
}

// GET
$vsechny = ObchodMrizka::zVsech();
$bunky   = ObchodMrizkaBunka::zVsech();
$res     = [];

foreach ($vsechny as &$x) {
    $bunkyMrizky = array_values(
        array_filter($bunky, function ($y) use ($x) {
            /** @var ObchodMrizkaBunka $y */
            return $y->mrizkaId() == $x->id();
        })
    );
    $res[]       = [
        'id'    => $x->id(),
        'text'  => $x->text(),
        'bunky' => array_map(function ($y) {
            return [
                'id'     => $y->id(),
                'typ'    => $y->typ(),
                'text'   => $y->text(),
                'barva'  => $y->barva(),
                'barvaText'  => $y->barvaText(),
                'cilId' => $y -> cilId(),
            ];
        }, $bunkyMrizky),
    ];
}

header('Content-type: application/json');
echo json_encode($res, $config);
