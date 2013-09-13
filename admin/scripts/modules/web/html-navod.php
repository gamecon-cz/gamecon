<?php

/** 
 * nazev: HTML návod
 * pravo: 2
 */

$demo='

<h2>Nadpis se dává mezi tagy "h2"</h2>

<p>Odstavec patří mezi tagy "p".</p>
<p>Počáteční tag je písmeno v špičatých závorkách a koncový tag je to samé písmeno s lomítkem. Dobrý způsob, jak psát špičaté závorky na české klávesnici, je pravý alt+čárka resp. pravý alt+tečka.</p>
<p>Nový řádek
nelze docílit
žádným 
odřádkováním.</p>
<p>Místo toho stačí <br /> použít tag "br"  <br />(který se sám ukončuje,<br /> proto to lomítko na konci).</p>

<h3>Podnadpis je h3</h3>

<p>Formátování v odstavci se dělá <b>tagem b</b> a <em>tagem em</em>.</p>
<p>Magická formule pro odkaz je <a href="http://google.com">Google</a>.</p>
<p>HTML umí o dost víc, z toho základního ale předvedu už jenom seznamy. Naopak věci, co nepředvedu, jsou:</p>

<ul>
  <li>tabulky</li>
  <li>velikosti a barvičky textu</li>
  <li>obrázky</li>
  <li>a hromada dalšího, protože <a href="http://goo.gl/JmUIt">Google ví</a></li>
  <li>(všimni si, že příklad vnořování tagů je třeba tag li (položka seznamu) vnořený v tagu ul (seznam))
</ul> 

';

?>

<h2>Informace</h2>

<p>1. pravidlo html: jakýkoli počet mezer, nových řádků, odstavců apod. se vždy změní na <b>jedinou mezeru</b>.</p>

<p>HTML funguje na principu značek (tagů), které se vkládají do textu. Značky určují bloky textu (seznamy, nadpisy, odstavce, tučný text, …) a vkládají se vždy na začátek a konec bloku. Značka je třeba &lt;b&gt;. Bloky se dají vnořovat (v odstavci může být tučný text), nedají se křížit (tučný text nemůže obsahovat konec jednoho odstavce a začátek jiného).</p>

<p>Text se přizpůsobuje stránce, kam je vložen (není to jak word, kde zadáte font a odsazení, a ten tam je, ale jen řeknete, že jde např. o seznam, a podle toho jestli to vložíte od admina nebo na uživatelský web, tak se jako odrážky samy zobrazí kolečka nebo gamecon kostičky.</p>

<h2>Ukázka</h2>

<table>
  <tr>
    <th width="530">Skutečně napsáno</th>
    <th>Zobrazí se jako</th>
  </tr>
  <tr>
    <td><?php echo strtr(htmlspecialchars($demo),array("\n"=>'<br />')) ?></td>
    <td><?php echo $demo ?></td>
  </tr>
</table> 






