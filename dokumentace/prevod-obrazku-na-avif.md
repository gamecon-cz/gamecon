# Převod stávajících obrázků do AVIF

Nově nahrané obrázky se ukládají jako AVIF samy. Obrázky nahrané dřív (přes FTP i z administrace) zůstávají JPG a PNG, dokud je nepřevede příkaz `obrazky:prevest-na-avif`: vyrobí AVIF vedle originálu a přepíše odkazy na něj v textech webu. Co převede a co ne (WebP, soubory ke stažení, obrázky z e-mailů, galerie e-shopu, loga, fotky vypravěčů) a proč, je v [`docs/generated/nahravani-obrazku.md`](../docs/generated/nahravani-obrazku.md).

Každé prostředí má vlastní nahrané soubory, které nejsou v gitu, takže se převádí zvlášť: **nejdřív beta, potom ostrá**.

## Než začneš

- **Nahrané soubory se nezálohují** (Borg bere jen databáze a `backup/db`, viz [`borg.md`](borg.md)). Udělej kopii `web/soubory/obsah`, například `cp -a web/soubory/obsah <mimo-web>/obsah-pred-avif`, a export databáze. Bez nich se převod nedá vrátit.
- Příkaz se spouští jako uživatel webserveru (www-data), aby nové soubory šly z administrace smazat a nahradit.
- **Prostředí se pozná stejně jako na webu**, podle `SERVER_NAME` z `nastaveni/nastaveni-server.php`, který generuje nasazení. Příkaz proto při zápisu chce i `--prostredi` a odmítne ho, když se neshoduje s tím, co sám pozná. Na začátku každého běhu vypíše prostředí, databázi a adresář, se kterými pracuje: zkontroluj je. Zápis navíc odmítne, když prostředí nesedí k adresáři projektu (beta je adresář `beta`) nebo k adrese webu (`URL_WEBU`) načteného nastavení: prázdný `SERVER_NAME` by z bety udělal `ostre`, chybějící soubor `locale`. Na betě proto musí vypsat `beta`.
- Mazání originálů prochází všechny textové sloupce databáze (char, varchar, text): jeden dotaz na sloupec zjistí, jestli vůbec obsahuje `soubory/obsah/`, a jen takové sloupce se pak čtou znovu, jednou za každých 30 originálů, takže doba roste s jejich počtem. Zmínka o souboru v kterémkoli textu, třeba v logu, jeho smazání zablokuje. Zkouší se pět zápisů cesty (obyčejný, celý zakódovaný, s `%20`, s kódovanou diakritikou, s obojím), HTML entita v názvu, JSON se `\/` a jiné směsi se nepoznají, takže před smazáním projdi v suchém běhu, co se smaže. Sloupec s odkazy v jiné znakové sadě než utf8 nebo s kolací `_bin` mazání zastaví chybou. Spouštěj ho mimo špičku.

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

Příkaz se dá spustit znovu kdykoli: hotový AVIF použije, jiný stejnojmenný AVIF nepřepíše a řádek textu, který někdo mezitím upravil, přeskočí (vypíše ho, stačí příkaz zopakovat).

## Po převodu

1. Projdi pár stránek, novinek a popisů aktivit s obrázky a zkontroluj, že se obrázky zobrazují. Náhledy v `cache/public/img` se přegenerují samy.
2. **Originály se nemažou.** Na staré adresy se může odkazovat zvenku (vyhledávače, odeslané e-maily, sociální sítě). Smaž je až později, když v přístupovém logu na staré adresy skoro nikdo nechodí:

   ```bash
   sudo -u www-data env APP_ENV=prod APP_DEBUG=0 php bin/console obrazky:prevest-na-avif --smazat-puvodni            # suchý běh
   sudo -u www-data env APP_ENV=prod APP_DEBUG=0 php bin/console obrazky:prevest-na-avif --smazat-puvodni --provest --zaloha-hotova --prostredi=beta
   ```

   Smaže jen originál, na který už nic neodkazuje a který má vedle stejný AVIF.
3. **Beta bere databázi z ostré.** Po jejím obnovení z ostré, která už odkazuje na AVIF, spusť převod na betě znovu: doplní AVIF k JPG a PNG, které tam zůstaly.
4. **AVIF z PNG, který vznikl před opravou vzorkování barev, má zprůměrované barvy sytých hran** (viz Gotchas v [`nahravani-obrazku.md`](../docs/generated/nahravani-obrazku.md)). Smaž ho (PNG vedle něj zůstává) a spusť příkaz znovu.
5. **Starší běh vynechával obrázky, na kterých AVIF ušetřil málo** (pod 2 KiB nebo čtvrtinu). Pravidlo už neplatí, takže další běh je převede taky.

## Návrat zpět

Z kopie obnov `web/soubory/obsah` a z exportu texty (stránky, novinky, popisy aktivit, medailonky). Dokud se originály nesmažou, stačí vrátit jen texty.
