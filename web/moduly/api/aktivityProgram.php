<?php

// TODO: při pojmenování jako api/aktivity.php z nezámeho důvodu připisuje obsah aktivity.php
// TODO: udělat REST api definice

use Gamecon\Api\ApiAktivityProgram;
use Gamecon\Api\Pomocne\ApiFunkce;

$u = Uzivatel::zSession();

// TODO: remove tesing snippet:
/*
var downloadAsJSON = (storageObj, name= "object") =>{
  const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(storageObj));
  const dlAnchorElem = document.createElement("a");
  dlAnchorElem.setAttribute("href",     dataStr     );
  dlAnchorElem.setAttribute("download", `${name}.json`);
  dlAnchorElem.click();
}

Promise.all(
  [2016, 2017, 2022]
    .map(rok =>
      fetch(`/web/api/aktivityProgram?rok=${rok}`, {method:"POST"})
        .then(x=>x.json())
        .catch(x=>[])
        .then(x=>[rok, x])
      )
  )
  .then(x=>Object.fromEntries(x))
  .then(x=>downloadAsJSON(x, "aktivityProgram"))

fetch("/web/api/aktivityProgram", {method:"POST"}).then(x=>x.text()).then(x=>console.log(x))
*/
// TODO: je potřeba otestovat taky $u->gcPrihlasen() ?
// TODO: tohle nastavení by mělo platit pro všechny php soubory ve složce api
$this->bezStranky(true);
header('Content-type: application/json');

if ($_SERVER["REQUEST_METHOD"] != "POST") {
  return;
}

$rok = array_key_exists("rok", $_GET) ? intval($_GET["rok"], 10) : ROK;

$json = ApiFunkce::vytvorApiJson(ApiAktivityProgram::apiAktivityProgram($rok, $u));
$etag = ApiFunkce::etagZApiJson($json);

$ifNoneMatch = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim($_SERVER['HTTP_IF_NONE_MATCH']) : '';

if ($ifNoneMatch === $etag) {
    header("HTTP/1.1 304 Not Modified");
    exit();
}

header("Etag: $etag");
echo $json;
