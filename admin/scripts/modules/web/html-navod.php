<?php

/** 
 * nazev: Návod
 * pravo: 2
 */

$demo='

## Nadpis se zvýrazňuje křížky

Před a za odstavcem se dělá prázdný řádek.

Nové řádky uprostřed odstavce by se neměly objevovat. V speciálních případech, jako je třeba báseň, se dají zalomit přidáním dvou mezer (které tu nejsou vidět, ale jsou tu)  
na  
konec  
řádku.

### Menší nadpis se označuje víc křížky

Formátování v odstavci se dělá _podtržítkem_ pro normární zvýraznění a __dvojitým podtržítkem__ pro řvoucí zvýraznění.

Magická formule pro odkaz je [Google](http://google.com).

Seznamy se dělají pomocí odrážek

- první
- druhá
- třetí

seznam pomocí čísel

1. první
1. druhá - čísluje se samo takže je lepší psát vždy 1., 1., 1. aby člověk nemusel ručně přepisovat
1. třetí

';

?>

<h2>Informace</h2>

<p>K formátování většiny textů na webu (aktivity, stránky) používáme jazyk Markdown (který používá Redbooth, Github, Google a další). Viz ukázka. Do Markdownu je možné vkládat html značky, pokud je to možné snažíme se toho ale vyvarovat.</p>
<p><b>FAQ:</b></p>
<ul>
  <li>Odkazy mimo stránky se automaticky otevřou v novém tabu, není dobré je psát ručně přes html.</li>
  <li>Obrázky plovoucí vpravo nebo vlevo TODO</li>
</ul>

<h2>Ukázka</h2>

<table>
  <tr>
    <th width="480">Skutečně napsáno</th>
    <th>Zobrazí se jako</th>
  </tr>
  <tr>
    <td><?php echo strtr(htmlspecialchars($demo),array("\n"=>'<br />')) ?></td>
    <td><?php echo markdown($demo) ?></td>
  </tr>
</table> 
