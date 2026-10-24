# Moduly a podmenu administrace

TL;DR: každý soubor `admin/scripts/modules/<modul>/*.php`, jehož název nezačíná `_`, je jedna položka podmenu a všechny její vlastnosti se čtou z hlavičkového komentáře souboru (`AdminMenu` v `admin/scripts/admin-menu.php`).

## Hlavičky

- `nazev:` text tlačítka a zároveň název stránky (`$info->nazev()` v `admin/index.php`), proto bez emoji.
- `ikona:` název ikony z Font Awesome Free 6.1.1 bez předpony, např. `cart-shopping`. `admin/templates/main.xtpl` z ní udělá `<i class="fa-solid fa-fw fa-…">` před textem tlačítka.
- `pravo:` jedno právo, `prava:` víc práv oddělených čárkou (stačí kterékoli).
- `submenu_group:`, `submenu_order:` řazení a rozdělení podmenu do skupin.
- `submenu_nazev:` jiný text v podmenu, než je `nazev:`.
- `submenu_link_open_in_blank:`, `submenu_hidden:` otevření v nové záložce, skrytí položky.

## Pravidla

- Hlavička se čte jen z **prvních 2048 bajtů** souboru, drž ji nahoře.
- Každá položka podmenu musí mít hlavičku `ikona:` a ikona musí existovat v `admin/files/design/font-awesome-6.1.1/css/all.css`; `nazev:` nesmí obsahovat emoji. Hlídá to `tests/Admin/AdminMenuIkonyTest.php`.
- Modul může přes proměnnou `$submenuDoplnek` (HTML, nastaví ji před koncem souboru) přidat blok na konec posledního sloupce podmenu; používá to Přehled Aktivit pro tlačítko „aktivovat hromadně" pod sloupcem Administrace (`aktivity/_aktivovat-vse.xtpl`).
- Hlavní menu vlevo ikonu z `ikona:` nepoužívá, kreslí PNG `admin/files/design/<modul>.png`.
- Emoji se v administraci nahrazují ikonami z Font Awesome. Výjimka je všechno, co sdílí veřejný web (ten Font Awesome nenačítá) a prosté textové popisky, kam HTML nejde (např. popisky omniboxu v `model/funkce/funkce.php`).
- Ikona v CSS pseudoelementu: `content: '\f071'` s `font-family: 'Font Awesome 6 Free'; font-weight: 900;` (kódy jsou v `all.css`). V JavaScriptu a šablonách jde rovnou `<i class="fa-solid fa-…" aria-hidden="true"></i>`.
