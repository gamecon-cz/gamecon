<?php

class FiltrMoznosti
{
  public const FILTROVAT_PODLE_ROKU = true;
  public const NEFILTROVAT_PODLE_ROKU = false;
  /**
   * @var int
   */
  private $filtrRoku;
  /**
   * @var int
   */
  private $letosniRok;
  /**
   * @var string
   */
  private $adminAktivityFiltr;
  /**
   * @var bool
   */
  private $filtrovatPodleRoku;

  public static function vytvorZGlobals(bool $filtrovatPodleRoku): FiltrMoznosti {
    if (post('filtr')) {
      if (post('filtr') === 'vsechno') {
        unset($_SESSION['adminAktivityFiltr']);
      } else {
        $_SESSION['adminAktivityFiltr'] = post('filtr');
      }
    }

    //načtení aktivit a zpracování
    if (get('sort')) { //řazení
      setcookie('akceRazeni', get('sort'), time() + 365 * 24 * 60 * 60);
      $_COOKIE['akceRazeni'] = get('sort');
    }

    if (post('filtrRoku')) {
      if (post('filtrRoku') === 'letos') {
        unset($_SESSION['adminAktivityFiltrRoku']);
      } else {
        $_SESSION['adminAktivityFiltrRoku'] = post('filtrRoku');
      }
    }

    $filtrRoku = $filtrovatPodleRoku && !empty($_SESSION['adminAktivityFiltrRoku']) && $_SESSION['adminAktivityFiltrRoku'] >= 2000 && $_SESSION['adminAktivityFiltrRoku'] <= ROK
      ? $_SESSION['adminAktivityFiltrRoku']
      : ROK;

    $adminAktivityFiltr = $_SESSION['adminAktivityFiltr'] ?? '';

    return new static($adminAktivityFiltr, ROK, $filtrRoku, $filtrovatPodleRoku);
  }

  public function __construct(string $adminAktivityFiltr, int $letosniRok, int $filtrRoku, bool $filtrovatPodleRoku) {
    $this->adminAktivityFiltr = $adminAktivityFiltr;
    $this->letosniRok = $letosniRok;
    $this->filtrRoku = $filtrRoku;
    $this->filtrovatPodleRoku = $filtrovatPodleRoku;
  }

  public function naplnTemplate(): XTemplate {
    $tplFiltrMoznosti = new XTemplate(__DIR__ . '/_filtr-moznosti.xtpl');
    $typy = $this->typy();
    $varianty = $this->varianty($typy);
    foreach ($varianty as $idTypu => $varianta) {
      $tplFiltrMoznosti->assign('idTypu', $idTypu);
      $tplFiltrMoznosti->assign(
        'nazev_programove_linie',
        sprintf('%s (aktivit %d)', ucfirst($varianta['popis']) . ($this->filtrRoku != $this->letosniRok ? (' ' . $this->filtrRoku) : ''), $varianta['pocet_aktivit'])
      );
      $tplFiltrMoznosti->assign('selected', $this->adminAktivityFiltr == $idTypu
        ? 'selected="selected"'
        : ''
      );
      $tplFiltrMoznosti->parse('filtr.programoveLinie.programovaLinie');
    }
    $tplFiltrMoznosti->parse('filtr.programoveLinie');

    if ($this->filtrovatPodleRoku) {
      $poctyAktivitVLetech = dbArrayCol('SELECT rok, COUNT(*) AS pocet FROM akce_seznam WHERE ROK > 2000 GROUP BY rok ORDER BY rok DESC');
      foreach ($poctyAktivitVLetech as $rok => $pocetAktivit) {
        $tplFiltrMoznosti->assign('rok', $rok);
        $tplFiltrMoznosti->assign('nazevRoku', $rok == $this->letosniRok ? 'letos' : $rok);
        $tplFiltrMoznosti->assign('pocetAktivit', $pocetAktivit);
        $tplFiltrMoznosti->assign('selected', $this->filtrRoku == $rok
          ? 'selected="selected"'
          : ''
        );
        $tplFiltrMoznosti->parse('filtr.roky.rok');
      }
      $tplFiltrMoznosti->parse('filtr.roky');
    }
    $tplFiltrMoznosti->parse('filtr');
    return $tplFiltrMoznosti;
  }

  private function typy(): array {
    return dbFetchAll(<<<SQL
SELECT akce_typy.id_typu, akce_typy.typ_1pmn AS nazev_typu, COUNT(*) AS pocet_aktivit
FROM akce_seznam
JOIN akce_typy ON akce_seznam.typ = akce_typy.id_typu
WHERE akce_seznam.rok = $1
GROUP BY akce_typy.id_typu
SQL
      , [$this->filtrRoku]
    );
  }

  private function varianty(array $typy): array {
    $varianty = ['vsechno' => ['popis' => '(všechno)']];
    $varianty['vsechno']['pocet_aktivit'] = $this->pocetAktivitCelkem($typy);
    foreach ($typy as $typ) {
      $varianty[$typ['id_typu']] = ['popis' => $typ['nazev_typu'], 'db' => $typ['id_typu'], 'pocet_aktivit' => $typ['pocet_aktivit']];
    }
    return $varianty;
  }

  private function pocetAktivitCelkem(array $typy): int {
    return (int)array_sum(array_map(static function (array $typ) {
      return $typ['pocet_aktivit'];
    }, $typy));
  }

  public function zobraz(XTemplate $tplFiltrMoznosti = null) {
    $tplFiltrMoznosti = $tplFiltrMoznosti ?? $this->naplnTemplate();
    $tplFiltrMoznosti->out('filtr');
  }

  public function dejFiltr(): array {
    $razeni = ['nazev_akce', 'zacatek'];
    if (!empty($_COOKIE['akceRazeni'])) {
      array_unshift($razeni, $_COOKIE['akceRazeni']);
    }

    $typy = $this->typy();
    $varianty = $this->varianty($typy);
    $filtr = empty($varianty[$this->adminAktivityFiltr]['db'])
      ? []
      : ['typ' => $varianty[$this->adminAktivityFiltr]['db']];
    $filtr['rok'] = $this->filtrRoku;

    return [$filtr, $razeni];
  }
}
