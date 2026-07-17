# Správa webu

Kartička **Web** v adminu slouží ke správě obsahu veřejného webu GameConu:
novinek a blogu, obsahových stránek, hlaviček programových linií a log
sponzorů a partnerů. Najdeš tu také odkazy na preview prostředí a archivy
starých ročníků. Co z toho vidíš, závisí na tvých právech — **Loga 🎨**
mají vlastní právo, takže je nemusí vidět každý, kdo spravuje zbytek webu.

## Novinky (hlavní stránka)

Po otevření kartičky **Web** vidíš tabulku všech novinek a blogových
příspěvků se sloupci **Vydat** (datum vydání), **Typ** (novinka / blog),
**Název** a **Akce**. Novou položku založíš tlačítkem **Přidat novinku**,
existující otevřeš odkazem **upravit**.

Formulář novinky obsahuje:

| Pole | K čemu je |
|------|-----------|
| **Typ** | výběr **novinka** / **blog** — podle toho se příspěvek zařadí na webu |
| **Vydat** | datum a čas vydání — na veřejném webu se příspěvek objeví až po tomto okamžiku, takže můžeš psát do zásoby |
| **Url** | adresa příspěvku (část URL na webu); musí být unikátní |
| **Nazev** | titulek příspěvku |
| **Autor** | jméno autora |
| text | vlastní obsah v Markdownu — vlevo píšeš, vpravo se rovnou ukazuje náhled, jak bude text vypadat |

Uložíš tlačítkem **Uložit**; po úspěchu se zobrazí hláška **Uloženo**.
První obrázek vložený do textu se na webu použije jako náhledový obrázek
příspěvku.

## Editace stránek

Podstránka **Editace stránek** vypisuje všechny obsahové stránky webu podle
jejich **URL**. Novou založíš tlačítkem **Přidat stránku**, existující
otevřeš přes **upravit**.

Formulář stránky obsahuje **Url stranky** (adresa na webu), **Poradi**
(řadicí číslo), zaškrtávátko **Redirect** a obsah v Markdownu s živým
náhledem vpravo. Když je **Redirect** zaškrtnutý, stránka nic nezobrazuje
a místo toho přesměruje návštěvníka na adresu zapsanou v poli obsahu.

**Pozor:** v obsahu některých stránek najdeš speciální vložky — widgety ve
tvaru `(widget:nazev)` a zástupné značky ohraničené procenty (např.
`%PRVNI_VLNA_KDY%`, doplní se z nastavení ročníku). Nemaž je ani nepřepisuj,
pokud přesně nevíš, co dělají — web si je při zobrazení nahrazuje živým
obsahem.

## Návod (formátování textů)

Podstránka **Návod** shrnuje, jak se formátují texty na webu (aktivity,
stránky, novinky). Používá se jazyk **Markdown** — stejný jako na Trellu
nebo GitHubu: nadpisy křížky (`## Nadpis`), prázdný řádek mezi odstavci,
`_podtržítka_` pro zvýraznění (`__dvojitá__` pro řvoucí), odkaz
`[Google](http://google.com)`, odrážky pomlčkou, číslované seznamy `1.`
(čísluje se samo). Odkazy mimo web GameConu se automaticky otevírají
v novém tabu — neřeš to ručně přes HTML. Na podstránce najdeš živou ukázku
„Skutečně napsáno" vs. „Zobrazí se jako".

## Hlavičky linií

Podstránka **Hlavičky linií** upravuje text a obrázek v hlavičce programové
linie na webu aktivit. Každá linie zobrazovaná v menu má řádek se třemi
sloupci: **Linie** (název a odkaz **otevřít veřejnou stránku**), **Texty**
a **Obrázek**.

- Texty: pole **Sekce**, **Jméno** a **E-mail** (kontakt na šéfa linie),
  uložíš tlačítkem **Uložit texty**. Když necháš všechna tři pole prázdná,
  hlavička se vymaže a použije se výchozí podoba (hláška „Hlavička linie
  byla vymazána, použije se fallback.").
- Obrázek: vyber soubor a klikni **Nahrát obrázek**. Podporované formáty
  jsou JPG, PNG, WebP a GIF, maximální velikost 2 MB. U každé linie vidíš
  náhled a stav (vlastní obrázek / fallback). Tlačítko **Smazat vlastní
  obrázek** vrátí linii k výchozímu obrázku.

## Loga sponzorů a partnerů

Podstránka **Loga 🎨** spravuje čtyři skupiny log: **Loga sponzorů na
titulce**, **Loga partnerů na titulce**, **Loga sponzorů v přehledu**
a **Loga partnerů v přehledu**. V každé skupině vidíš nahraná loga
s náhledem a odkazem, tlačítko **Smazat** (s potvrzením „Opravdu chcete
smazat logo …?") a formulář pro nahrání nového.

Nahrání: vyber soubor loga, vyplň **URL sponzora** / **URL partnera**
(povinné — adresa, kam logo na webu odkazuje) a klikni **Nahrát logo**.
Podporované formáty: JPG, PNG, GIF, WebP, SVG. Soubor se pojmenuje podle
domény z URL, takže nahrání dalšího loga se stejnou doménou přepíše to
původní.

Pořadí log řídí soubor **RAZENI.csv**: nahraješ ho tlačítkem **Nahrát
RAZENI.csv**, stáhnout si můžeš **Platné RAZENI.csv** i **vzor RAZENI.csv**.
Loga se seřadí podle seznamu v souboru (velikost písmen a případné `www.`
na začátku se ignorují — `www.Albi.cz` se bere jako `albi.cz`); loga, která
v souboru nejsou, se připojí za ně seřazená podle abecedy.

## Previews

Podstránka **Previews** ukazuje seznam aktivních preview prostředí — to jsou
zkušební kopie webu pro jednotlivé vyvíjené změny, kde si můžeš novou funkci
vyzkoušet dřív, než se dostane na ostrý web. U každého preview vidíš jeho
URL, odkaz **/admin**, odkaz na související PR a datum nasazení
(**Deployed**).

Preview jsou schovaná za heslem: přihlašovací údaje k bráně jsou na stránce
vypsané jako kopírovatelný text, ale prokliky odsud obvykle projdou bez
ptaní — a do adminu preview budeš rovnou přihlášený svým účtem. Nahoře je
odkaz na sdílenou schránku **webmail.preview.gamecon.cz**, kam chodí všechny
e-maily odeslané z preview (žádné se neposílají skutečným lidem).

## Staré ročníky

Podstránka **Staré ročníky** vypisuje archivy minulých ročníků na adresách
`RRRR.gamecon.cz`, seřazené od nejnovějšího. Ročníky od 2012 jsou „živé" —
mají funkční web i admin; starší jsou jen statické kopie (sekce **Pouze
statické kopie** a **Věk Altaru**) a admin u nich není.

I archivy jsou za heslem — přihlašovací údaje k bráně vidíš na stránce jako
kopírovatelný text, proklik odsud ale většinou projde bez dialogu. U živých
ročníků je odkaz do adminu (**RRRR /admin**): kliknutím jsi v archivu rovnou
přihlášený svým účtem, nic dalšího vyplňovat nemusíš. Odkaz funguje jen
tobě — když ho pošleš někomu jinému, nepřihlásí ho. Ročníky, které v seznamu
nejsou, zatím žijí na staré infrastruktuře mimo tento přehled.

## Medailonky vypravěčů

Medailonky (krátké představení vypravěčů na webu) se spravují na kartičce
**Aktivity → Medailonky** — přístup k nim ale patří ke správě webu. Nový
medailonek vytvoříš zadáním **ID uživatele** a tlačítkem **vytvořit**.
V tabulce vidíš u každého vypravěče ✅/❌ podle toho, zda má vyplněný obecný
**Popis** a část pro **DrD** (najetím myší se text ukáže), a tužku pro
editaci. Horní část medailonku je obecná, dolní část je pro DrD.

## Typické postupy

**Přidat novinku:** Web → **Přidat novinku** → vyplň **Typ**, **Vydat**,
**Url**, **Nazev**, **Autor** a text v Markdownu → **Uložit**. Zkontroluj
datum vydání — do té doby novinka na webu vidět není.

**Upravit text stránky:** Web → **Editace stránek** → u stránky klikni
**upravit** → uprav Markdown vlevo, náhled sleduj vpravo → **Uložit**.

## Na co si dát pozor

- **Smazat novinku ani stránku z adminu nejde** — formuláře umí jen
  vytvářet a upravovat. Když potřebuješ něco odstranit, obrať se na správce.
- Novinka se zveřejňuje **podle data v poli Vydat** — budoucí datum znamená
  odložené vydání, ne chybu.
- Ve stránkách **nemaž widgety a značky v procentech** — nahrazují se živým
  obsahem z nastavení.
- U log **přepíše nové logo se stejnou doménou to staré** — chceš-li logo
  jen vyměnit, stačí nahrát nové se stejnou URL.
- Pokud se uložení nepovede, formulář zobrazí hlášku **„Chyba: …"** —
  oprav vstup a zkus to znovu.
