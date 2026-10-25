# Moduly a podmenu administrace

TL;DR: každý soubor `admin/scripts/modules/<modul>/*.php`, jehož název nezačíná `_`, je jedna položka podmenu a všechny její vlastnosti se čtou z hlavičkového komentáře souboru (`AdminMenu` v `admin/scripts/admin-menu.php`).

## Hlavičky

- `nazev:` text tlačítka (v podmenu ho přebíjí `submenu_nazev:`) a zároveň název stránky (`$info->nazev()` v `admin/index.php`), proto bez emoji.
- `ikona:` název ikony z Font Awesome Free 6.1.1 bez předpony, např. `cart-shopping`. `admin/templates/main.xtpl` z ní udělá `<i class="fa-solid fa-fw fa-…">` před textem tlačítka.
- `pravo:` jedno právo, `prava:` víc práv oddělených čárkou (stačí kterékoli).
- `submenu_group:`, `submenu_order:` řazení a rozdělení podmenu do skupin.
- `submenu_nazev:` jiný text v podmenu, než je `nazev:`.
- `submenu_link_open_in_blank:`, `submenu_hidden:` otevření v nové záložce, skrytí položky.

## Pravidla

- Hlavička se čte jen z **prvních 2048 bajtů** souboru, drž ji nahoře.
- Každá položka podmenu i hlavního menu musí mít hlavičku `ikona:` a ikona musí existovat v `admin/files/design/font-awesome-6.1.1/css/all.css`; `nazev:` ani `submenu_nazev:` nesmí obsahovat emoji. Hlídá to `tests/Admin/AdminMenuIkonyTest.php`.
- Modul může přes proměnnou `$submenuDoplnek` (HTML, nastaví ji před koncem souboru) přidat blok na konec posledního sloupce podmenu; používá to Přehled Aktivit pro tlačítko „aktivovat hromadně" pod sloupcem Administrace (`aktivity/_aktivovat-vse.xtpl`). Takový blok není položka podmenu, `ikona:` se ho netýká; ikonu (`fa-bolt`) nese přímo jeho šablona.
- Hlavní menu vlevo kreslí v klasickém vzhledu PNG `admin/files/design/<modul>.png`, v moderním ikonu z `ikona:` (viz [vzhled-adminu](vzhled-adminu.md)). Proto ji musí mít i moduly hlavního menu bez podmenu (`uzivatel.php`, `statistiky.php`, `napoveda.php`); hlídá to týž test.
- (záměr) Ikony z Font Awesome nahrazují emoji jen v menu (názvy a tlačítka podmenu). Emoji mimo menu, tedy v hláškách, na tlačítkách a ve stavových značkách stránek, na online prezenci i v nápovědě, zůstávají; nenahrazuj je.
