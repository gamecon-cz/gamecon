# Převod stávajících obrázků do AVIF

Nově nahrané obrázky se ukládají jako AVIF samy. Obrázky nahrané dřív (přes FTP i z administrace) zůstávají JPG a PNG, dokud je nepřevede příkaz `obrazky:prevest-na-avif`: vyrobí AVIF vedle originálu a přepíše odkazy na něj v textech webu; stejně převede fotky uživatelů (`systemove/fotky`), jen bez textů, protože na ně nic neodkazuje. Co převede a co ne (WebP v textech, soubory ke stažení, obrázky z e-mailů, galerie e-shopu, loga) a proč, je v [`docs/generated/nahravani-obrazku.md`](../docs/generated/nahravani-obrazku.md).

Každé prostředí má vlastní nahrané soubory, které nejsou v gitu, takže se převádí zvlášť: **nejdřív beta, potom ostrá**.

## Než začneš

- **Nahrané soubory se nezálohují** (Borg bere jen databáze a `backup/db`, viz [`borg.md`](borg.md)). Udělej kopii `web/soubory/obsah` a `web/soubory/systemove/fotky`, například `cp -a web/soubory/obsah <mimo-web>/obsah-pred-avif` a `cp -a web/soubory/systemove/fotky <mimo-web>/fotky-pred-avif`, a export databáze. Bez nich se převod nedá vrátit. Fotky jsou fotky lidí s GPS v EXIFu, takže kopii drž mimo web, nikam ji nesdílej a po ověření ji smaž. Příkaz chce potvrzení `--zaloha-hotova` jen pro to, co převádí: s `--oblast=fotky` stačí kopie fotek (databáze se nemění), s `--oblast=obsah` nebo `--slozka` kopie obsahu a export databáze.
- Příkaz se spouští jako uživatel webserveru (www-data), aby nové soubory šly z administrace smazat a nahradit.
- **Prostředí se pozná stejně jako na webu**, podle `SERVER_NAME` z `nastaveni/nastaveni-server.php`, který generuje nasazení. Příkaz proto při zápisu chce i `--prostredi` a odmítne ho, když se neshoduje s tím, co sám pozná. Na začátku každého běhu vypíše prostředí, databázi a adresář, se kterými pracuje: zkontroluj je. Zápis navíc odmítne, když prostředí nesedí k adresáři projektu (beta je adresář `beta`) nebo k adrese webu (`URL_WEBU`) načteného nastavení: prázdný `SERVER_NAME` by z bety udělal `ostre`, chybějící soubor `locale`. Na betě proto musí vypsat `beta`.
- Mazání originálů prochází všechny textové sloupce databáze (char, varchar, text): jeden dotaz na sloupec zjistí, jestli vůbec obsahuje `soubory/obsah/` (u fotek uživatelů `soubory/systemove/fotky/`), a jen takové sloupce se pak čtou znovu, jednou za každých 30 originálů, takže doba roste s jejich počtem. Bez `--oblast` se sloupce prohlížejí dvakrát, jednou za každou oblast, která má originály ke smazání, takže mazání trvá zhruba dvakrát déle než jedné oblasti. Zmínka o souboru v kterémkoli textu, třeba v logu, jeho smazání zablokuje. Zkouší se pět zápisů cesty (obyčejný, celý zakódovaný, s `%20`, s kódovanou diakritikou, s obojím), HTML entita v názvu, JSON se `\/` a jiné směsi se nepoznají, takže před smazáním projdi v suchém běhu, co se smaže. Sloupec s odkazy v jiné znakové sadě než utf8 nebo s kolací `_bin` mazání zastaví chybou. Spouštěj ho mimo špičku.

## Postup

V kořeni aplikace na serveru. `APP_ENV` se musí zadat, na serveru není `.env` a bez něj `bin/console` skončí chybou `Undefined array key "APP_ENV"` (web si ho bere z `nastaveni/nastaveni-*.php`):

```bash
# 1. suchý běh: nic nezapíše, vypíše co by se stalo a kolik by se ušetřilo (trvá stejně dlouho jako ostrý běh)
sudo -u www-data env APP_ENV=prod APP_DEBUG=0 php bin/console obrazky:prevest-na-avif -v

# 2. zkouška na pár obrázcích
sudo -u www-data env APP_ENV=prod APP_DEBUG=0 php bin/console obrazky:prevest-na-avif --provest --zaloha-hotova --prostredi=beta --slozka=blog --limit=5

# 3. celý převod
sudo -u www-data env APP_ENV=prod APP_DEBUG=0 php bin/console obrazky:prevest-na-avif --provest --zaloha-hotova --prostredi=beta -v
```

Na ostré je to `--prostredi=ostre`.

Bez přepínače `--oblast` převádí příkaz obojí, nejdřív obrázky v textech, pak fotky uživatelů. Jen fotky: `--oblast=fotky`, zkouška na třech: `--oblast=fotky --limit=3`. Přepínač `--slozka` omezuje jen obrázky v textech a s ním se fotky nepřevádějí, takže zkouška z kroku 2 na fotky nesahá. Přepínač `--limit` platí za celý běh: nejdřív se převádějí texty, a pokud se limit vyčerpá na nich, příkaz to u fotek řekne a ty přijdou na řadu až při dalším běhu. Když první oddíl skončí výjimkou (třeba sloupec s odkazy v jiné kolaci při mazání, to ještě nic nesmazalo), druhý se nespustí; druhou oblast pak pusť zvlášť přes `--oblast`.

Uživatele s více soubory, ze kterých web fotku vybírá (`5.jpg` a `5.png`, i `5.stara.png`, protože web hledá `<id>.*`), příkaz nepřevádí ani nemaže a vypíše je s důvodem: AVIF z jednoho z nich by zakryl ten, který web ukazoval. Nech jen ten správný soubor a spusť příkaz znovu.

Příkaz se dá spustit znovu kdykoli: hotový AVIF použije, jiný stejnojmenný AVIF nepřepíše a řádek textu, který někdo mezitím upravil, přeskočí (vypíše ho, stačí příkaz zopakovat).

## Po převodu

1. Projdi pár stránek, novinek a popisů aktivit s obrázky a zkontroluj, že se obrázky zobrazují. Náhledy v `cache/public/img` se přegenerují samy.
2. **Originály se nemažou.** Na staré adresy se může odkazovat zvenku (vyhledávače, odeslané e-maily, sociální sítě). Smaž je až později, když v přístupovém logu na staré adresy skoro nikdo nechodí:

   ```bash
   sudo -u www-data env APP_ENV=prod APP_DEBUG=0 php bin/console obrazky:prevest-na-avif --smazat-puvodni            # suchý běh
   sudo -u www-data env APP_ENV=prod APP_DEBUG=0 php bin/console obrazky:prevest-na-avif --smazat-puvodni --provest --zaloha-hotova --prostredi=beta
   ```

   Smaže jen originál, na který už nic neodkazuje a který má vedle stejný AVIF. U fotek uživatelů tím z ostré zmizí i GPS v originálech, které jsou veřejně na odhadnutelné adrese `systemove/fotky/<id>.jpg`.
3. **Beta bere databázi z ostré.** Po jejím obnovení z ostré, která už odkazuje na AVIF, spusť převod na betě znovu: doplní AVIF k JPG a PNG, které tam zůstaly.
4. **AVIF z PNG, který vznikl před opravou vzorkování barev, má zprůměrované barvy sytých hran** (viz Gotchas v [`nahravani-obrazku.md`](../docs/generated/nahravani-obrazku.md)). Smaž ho (PNG vedle něj zůstává) a spusť příkaz znovu.
5. **Starší běh vynechával obrázky, na kterých AVIF ušetřil málo** (pod 2 KiB nebo čtvrtinu). Pravidlo už neplatí, takže další běh je převede taky.
6. **Fotky uživatelů:** otevři stránku vypravěče a zkontroluj, že se jeho fotka ukáže. `Uzivatel::fotka()` bere AVIF přednostně, i když originál má velkou příponu `.JPG` (`glob` řadí bajty, takže ta by šla před `.avif`), takže po převodu je to AVIF; náhled se přegeneruje sám při prvním zobrazení, takže první načtení stránky s mnoha vypravěči je pomalejší (každý AVIF se dekóduje znovu); otevři ji hned po převodu.

## Návrat zpět

Z kopie obnov `web/soubory/obsah`, `web/soubory/systemove/fotky` a z exportu texty (stránky, novinky, popisy aktivit, medailonky). Dokud se originály nesmažou, stačí vrátit jen texty; fotky uživatelů texty nepřepisují, stačí smazat AVIF vedle originálu a `Uzivatel::fotka()` vezme zase originál.
