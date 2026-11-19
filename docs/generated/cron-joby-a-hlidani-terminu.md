TL;DR: hodinový cron (`admin/cron.php`) a joby s `?job=`, které plánuje ručně cron-job.org; jak se hlásí pád jobu a jak hodinový cron hlídá, že upomínky dlužníkům a varování o promlčení proběhly ve svém termínu.

## Vstupní body

- `admin/cron.php` — hodinový běh (cron-job.org, jednou za hodinu): Fio platby, týmy, zamykání aktivit, archiv přednášek, kontrola zmeškaných termínů, záloha. Bez `?job=`.
- `admin/cron/_cron_job.php` — rozcestník `?job=<název>`. Každý job je zvlášť v `try`, `catch` volá `Gamecon\Cron\CronErrorNotifier` (mail na it@ a info@).
- `admin/cron/_cron_na_cas.php` — joby, které ho volají, potřebují parametr `cas` (`cas=now`, nebo čas, na který job až 80 s počká).
- `model/Cron/HlidaniZmeskanychTerminu.php` — kontrola zmeškaných termínů, `model/Cron/OknoSpusteni.php` — okno jobu (od, do = od + 23 h).
- `model/Uzivatel/UpominaniDluzniku.php`, `model/Uzivatel/PromlceniZustatku.php`, `admin/cron/jobs/promlceni_automaticke.php` — joby `dluznici` a `promlceni`.

## Pravidla

- **Job s `?job=` běží, jen když ho někdo přidá do cron-job.org** (https://console.cron-job.org, s `key` = `CRON_KEY`). Repozitář o plánu nic neví. `dluznici` a `promlceni` existují od listopadu 2025, na ostré ale nikdy neběžely, protože je tam nikdo nepřidal: po GC 2026 nedostal automatickou upomínku žádný dlužník a přišlo se na to až v listopadu 2026 (záměr). Proto nový job raději do hodinového cronu, když je levný (archiv přednášek, viz `docs/generated/archiv-prednasek.md`).
- **Pád jobu musí přijít mailem**: nový `require` jobu v `_cron_job.php` patří do `try` s `CronErrorNotifier`. Hlídá to `tests/Admin/CronJobObsluhaChybTest.php` (rozebere soubor parserem). Joby za prvním blokem `try` dřív padaly jen do `logy/cron/cron-<job>-<YYYY-MM>.log`. Neplatný název jobu zůstává mimo `try` schválně, výjimka propadne do Výjimkovače jako dřív.
- **Okna** (`OknoSpusteni`, 23 h od začátku, job je proto potřeba pouštět aspoň jednou denně):
  - upomínka dlužníkům týden a měsíc po konci GC (`UpominaniDluzniku::dejOknoUpominky()`, konec GC = `spocitanyKonecLetosnihoGameconu()`),
  - varování o promlčení měsíc a týden před začátkem registrací (`PromlceniZustatku::dejOknoVarovani()`),
  - automatické promlčení od dne po GC (`automatickePromlceniOd()`), horní mez nemá.
  Mimo okno job jen zaloguje „není správný čas“. Zmeškané okno se automaticky nedoplní: upomínky jdou rozeslat ručně (admin Finance → Upomínky dlužníkům), varování o promlčení ne.
- **Každý doběhnutý běh má záznam v `hromadne_akce_log`**, i nulový (žádný dlužník, nikdo k varování nebo k promlčení). Skupiny `upominani-dluzniku` (`upominani-tyden-<rocnik>`, `upominani-mesic-<rocnik>`) a `promlceni-zustatku` (`varovani-mesic-…`, `varovani-tyden-…`, `automaticke-promlceni-…`). Záznam zároveň brání druhému běhu v tomtéž ročníku. Nulový běh dřív záznam nepsal: od neproběhlého nešel odlišit a automatické promlčení bez lidí posílalo CFO stejný mail při každém běhu.
- **Kontrola zmeškaných termínů** (`HlidaniZmeskanychTerminu::zkontroluj()`, z hodinového cronu): hodinu po konci okna (rezerva na dobíhající job) ověří záznam běhu. Když chybí, pošle jeden mail se všemi zmeškanými termíny CFO (bez CFO info@) a it@ a zapíše `hromadne_akce_log` skupina `hlidani-zmeskanych-terminu`, akce `zmeskano-<klíč>`, takže na každý termín upozorní jednou. Zapisuje až po odeslání (výpadek SMTP = zkusí příští hodina). Před koncem prvního okna nesahá do DB. Chybějící záznam znamená, že job neběžel, nebo spadl před koncem (pak přišel i mail o pádu).
- **Kontrola běží jen na ostré** (`jsmeNaOstre()` v `admin/cron.php`): z bety (maily jen orgům) a preview upomínky skutečně neodcházejí a upozornění by CFO mátlo.
- Hlídá se jen aktuální `ROCNIK`. Po přepnutí ročníku jsou všechna okna v budoucnu.

## Gotchas

- Text mail (`GcMail::FORMAT_TEXT`) jde přes `strip_tags()`: cokoli jako `<job>` z textu zmizí.
- Nasazení kontroly na ostrou v listopadu 2026 hned ohlásí čtyři zmeškané termíny ročníku 2026 (upomínky týden a měsíc, obě varování) a podle stavu i automatické promlčení. To je účel, ne chyba.
