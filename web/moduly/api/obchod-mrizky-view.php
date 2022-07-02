<?php

// TODO: vxužíváno adminem asi by mělo být v adminu (nutno dovymyslet)
// TODO: ObchodMrizka, ObchodMrizkaBunka by měli být asi v nějakém namespace (nepodařilo se mi rozchodit - padá)
// TODO: řešit pomocí joinu nebo view na DB

/*
 
*/

$this->bezStranky(true);
header('Content-type: application/json');
$config = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;



$vsechny = ObchodMrizka::zVsech();
$bunky = ObchodMrizkaBunka::zVsech();
$res = [];


foreach ($vsechny as &$x) {
  $bunkyMrizky = array_filter($bunky, function ($y) use ($x) {
    /** @var ObchodMrizkaBunka $y */ return $y->mrizka_id() == $x->id();
  });
  $res[] = [
    'id' => $x->id(),
    'text' => $x->text(),
    'buňky' =>
    array_map(function ($y) {
      return [
        'id' => $y->id(),
        'typ' => $y->typ(),
        'text' => $y->text(),
        'barva' => $y->barva(),
        'cil_id' => $y->cil_id(),
        'mrizka_id' => $y->mrizka_id(),
      ];
    }, $bunkyMrizky)
  ];
}


echo json_encode($res, $config);
