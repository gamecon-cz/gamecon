
## Code style PHP

- __2 znaky mezera__ – odsazení
- __Mezery kolem = . +__ a dalších podobných operátorů
- __Bez mezer za klíčovými slovy__ if(podmínka) { ... }
- __Otvírací složená závorka vždy na stejném řádku__ function mojeMetoda($argument) { ... }
- __LF__ – znak konce řádku, CR nepoužívat
- __Uzavírací závorka před else__ } else {
- __Zbytek standardy__ – pro zbytek, není-li řečeno jinak, je nutné dodržovat standardy, tj. [PSR-1](http://www.php-fig.org/psr/psr-1/) a [PSR-2](http://www.php-fig.org/psr/psr-2/)

Další doporučení:

- __80 znaků na řádek__
- __Respektovat existující kód__ – pokud už nějaký kód je kolem (a není starý nerespektující aktuální style guide), tak se snažíme okopírovat jeho styl i v kódu co přidáváme.
- __Neměnit nesouvisející věci__ – code style opravujeme jenom na místech, která měníme v rámci programování daného commitu. Pokud ho chceme změnit i jinde v souboru, musíme tyto změny přidat v samostatném commitu.
- __Zarovnávat__ – u věcí pod sebou jako jsou => v polích nebo více přiřazení = je často hezčí zarovnat. Řídíme se úsudkem a respektováním okolního kódu.
- __Jednoduché podmínky oneline__ – následující je v pohodě:

```php
if($podminka) udelejNeco();
else          throw new Exception('nesplněno');
```

## Code style JS

[JavaScript Standard](https://standardjs.com/).

## Code style SQL

```sql
SELECT u.email1_uzivatele as 'mail'
FROM uzivatele_hodnoty u
JOIN uzivatele_role uz ON
  uz.id_uzivatele = u.id_uzivatele AND -- [1]
  uz.id_role = {zPritomen}
LEFT JOIN medailonky m ON m.id_uzivatele = u.id_uzivatele
WHERE u.souhlas_maily AND NOT u.mrtvy_mail
ORDER BY u.email1_uzivatele
```

- __Jen JOIN a LEFT JOIN__ – další varianty (INNER, OUTER, NATURAL, ...) nepoužívat.
- __Dlouhé podmínky na více řádků__ – viz [1], odsazení 2 mezery, spojka mezi řádky se píše na konec řádku, spojka mezi všemi řádky musí být stejná (AND nebo OR), pokud jsou spojky i uvni řádku, je vhodné ho uzávorkovat.
- __Dlouhé SELECTy na více řádků__ – odsadit a hodnota per řádek podobně jako u podmínek.

## Nastavení editorů

Je vhodné nastavit si editor, aby code style (minimálně u PHP a JS) kontroloval. Určitě to umí minimálně Sublime, Atom, Netbeans, PhpStorm.

Pokud váš editor generuje nějaké pomocné složky / soubory, nastavte si [globální .gitignore](https://davidwalsh.name/global-gitignore) a přidejte je do něj.

__NetBeans:__ code style, který si můžete importovat, je na google drive ve složce "I: Web a IT" archiv "konfigurace NetBeans.zip" s nastavením pro import. Pro zformátování kódu podle style guide v NetBeans označte Vámi upravenou část kódu a použijte klávesovou zkratku Alt+Shift+F. Nepoužívat pro celý skript.
