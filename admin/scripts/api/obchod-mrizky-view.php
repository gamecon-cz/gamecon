<?php

// TODO: vxužíváno adminem asi by mělo být v adminu (nutno dovymyslet)
// TODO: ObchodMrizka, ObchodMrizkaBunka by měli být asi v nějakém namespace (nepodařilo se mi rozchodit - padá)
// TODO: řešit pomocí joinu nebo view na DB
// TODO: OpenAPI

/*
  type MřížkaAPI = {
    id?: number, 
    text?: string, 
    bunky?: {
      id?: number,
      typ: number - TypBunky,
      text?: string,
      barva?: string,
      cil_id?: number,
    }[],
  }[]

  GET api/obchod-mrizky-view
  response: MřížkaAPI všechno přítomné

  POST api/obchod-mrizky-view
  body: MřížkaAPI

*/

header('Content-type: application/json');
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

  die();
}


// GET
$vsechny = ObchodMrizka::zVsech();
$bunky = ObchodMrizkaBunka::zVsech();
$res = [];


foreach ($vsechny as &$x) {
  $bunkyMrizky = array_values(
    array_filter($bunky, function ($y) use ($x) {
      /** @var ObchodMrizkaBunka $y */ return $y->mrizka_id() == $x->id();
    })
  );
  $res[] = [
    'id' => $x->id(),
    'text' => $x->text(),
    'bunky' => array_map(function ($y) {
      return [
        'id' => $y->id(),
        'typ' => $y->typ(),
        'text' => $y->text(),
        'barva' => $y->barva(),
        'cil_id' => $y->cil_id(),
      ];
    }, $bunkyMrizky)
  ];
}

echo json_encode($res, $config);
