# Vstup na preview přes admin (sdílená URL)

TL;DR: Když někdo otevře URL preview a nemá přístup, brána na serveru se serverově zeptá adminu, jestli jeho relace patří organizátorovi. Ano → projde bez basic auth a je přihlášen i do samotného preview, ne → místo dialogu dostane stránku s odkazem do adminu. Admin na tu otázku odpovídá endpointem `/api/preview-vstup`, který smí volat jen brána.

## Vstupní body

- `admin/scripts/api/preview-vstup.php` — endpoint (`GET /api/preview-vstup?nonce=…`), bez layoutu a bez přesměrování na přihlášení
- `model/Dev/PreviewVstup.php` — kdo smí a podpis přihlašovacího tokenu
- `model/Dev/GateLink.php::over()` — ověření tokenu v `X-Gate-Auth`
- repozitář ansible (soukromý): `roles/gate_validator/files/enter.go`, snippet `preview_auth` v `roles/caddy/templates/Caddyfile.j2`, `docs/preview-archive-gate-token.md`

Testy: `tests/Dev/PreviewVstupTest.php`, `tests/Dev/GateLinkTest.php`.

## Pravidla

- **Volat smí jen brána.** Endpoint vydává přihlašovací token tomu, čí relaci dostane, takže prohlížeč (třeba cizí stránka, která ho přesměruje na endpoint) ho volat nesmí. Brána proto posílá `X-Gate-Auth`: token ve formátu odkazů `?gate=`, podepsaný `PREVIEW_GATE_SECRET`, platný minutu. Odkaz `?gate=` ze stránky Previews a cookie `gc_gate` mají stejný formát a klíč, ale platí 24 h a drží je každý, kdo byl v preview, proto `GateLink::over()` přijme jen token s platností nejvýš 120 s (odkaz by prošel až v posledních dvou minutách své platnosti a dostal by login token jen pro vlastní relaci). Bez takového tokenu odpoví endpoint stejně jako každá neexistující adresa v `api` (`404`, prostý text), i když je relace přihlášená a i při špatné metodě.
- **Kdo smí:** `Uzivatel::jeOrganizator()` (role organizátora letošního ročníku, vypravěči mezi ně nepatří) nebo právo `Pravo::ADMINISTRACE_WEB`. `(záměr)` Organizátor s přístupem do adminu. Data z ostré v preview chrání práva uvnitř aplikace stejně jako na ostré, vstup do preview tedy nic navíc neodemyká.
- **Odpovědi:** `401 {"stav":"neprihlasen"}`, `403 {"stav":"bez-pristupu"}`, `200 {"stav":"ok","gcsso":"…"}`. `gcsso` je prázdné bez platného `nonce` (32 znaků `0-9a-f`) nebo bez `GAMECON_SSO_SECRET`; pak se organizátor jen pustí dál.
- **`nonce` generuje brána** a stejnou hodnotu uloží do spárovací cookie `gc_sso_pair` (viz `SsoParovaciCookie`). Token se podepisuje jako u odkazů ze stránky Previews (`CrossSiteLogin::podepis`), do preview ho přijme jen prohlížeč s odpovídající cookie.
- **Přihlášení do webu preview** funguje až na větvi, která čte `?gcsso=` i na veřejném webu (`SsoPrihlaseniZUrl`). Brána samotná na tom nezávisí.

## Gotchas

- Brána spoléhá na to, že cookie relace `PS0gamecon0cz` má `Domain=.gamecon.cz` a prohlížeč ji proto posílá i na preview. Při zúžení jejího rozsahu na admin se každému návštěvníkovi ukáže stránka „přihlas se do adminu“ (basic auth zůstává odkazem na ní).
- Brána volá admin přes Apache na loopbacku (`127.0.0.1:8080`, hlavička `Host: admin.gamecon.cz`, `X-Forwarded-Proto: https`), ne přes veřejnou adresu: její systemd jednotka smí jen na loopback.
- Routa `api` v `admin/index.php` běží před přihlašovací obrazovkou, proto endpoint dostane `$u` bez přesměrování a o stavu přihlášení rozhoduje sám.
- Brána posílá do adminu jen cookie relace. Kdo je přihlášen jen „trvale“ (cookie `gcTrvalePrihlaseni`, platí jen pro host adminu) a relace mu vypršela, dostane stránku „přihlas se do adminu“, dokud admin jednou neotevře.
- PHP drží soubor relace zamčený po dobu požadavku, takže dlouhý požadavek téže relace v adminu (export, report) odpověď brány zdrží. Po vypršení času brány se zobrazí dialog basic auth.
- Preview ze starší větve `?gcsso=` na webu nečte: organizátor projde bránou, ale do aplikace se musí přihlásit sám.
- Lokální ověření: `PREVIEW_GATE_SECRET` a `GAMECON_SSO_SECRET` v `docker-compose.override.yml`, požadavek s `X-Gate-Auth` podepsaným stejným tajemstvím (`openssl dgst -sha256 -hmac`) a cookie přihlášené relace.
