# Push notifikace: konfigurace, nasazení a ruční testování

Web push notifikace bez mobilní aplikace (Trello 1019, proof of concept). Jak fungují uvnitř a proč, je v
[docs/generated/web-push-notifikace.md](generated/web-push-notifikace.md). Tady je, co je potřeba nastavit, aby
běžely, a jak je vyzkoušet na skutečných zařízeních.

## Co je potřeba, aby notifikace běžely

1. **VAPID klíče prostředí** (`VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`). Bez nich je funkce vypnutá.
2. **Zapnutý přepínač** v adminu: Nastavení, skupina Notifikace, „Push notifikace zapnuté“. Výchozí je vypnuto.
3. **HTTPS.** Prohlížeče pustí service worker a push jen na zabezpečeném webu (výjimkou je `localhost`).
4. **Právo „Administrace - Push notifikace“ (119)** pro toho, kdo má z adminu odesílat. Migrace ho dala roli Dev,
   dalším rolím ho přidá rada v adminu na stránce Práva. Zapnout si notifikace na svém zařízení smí každý
   přihlášený, k tomu právo není.

Dokud není splněné 1 a 2, web o funkci navenek neví: žádné ovládání v Nastavení, žádný manifest, API odpovídá 404.

## VAPID klíče

Pár klíčů, kterým se server prokazuje push službám prohlížečů (Google, Mozilla, Apple, Microsoft). Veřejný klíč
dostane prohlížeč při zapnutí notifikací, soukromým server podepisuje každou zprávu.

**Každé prostředí má vlastní pár.** Beta a preview běží nad kopií ostré databáze, která obsahuje i zařízení
účastníků z ostré. S cizími klíči jim push služba nic nedoručí, a aplikace se o to ani nepokusí: u každého
zařízení je uložený klíč a adresa webu prostředí, kde si ho uživatel zapnul, a odesílá se jen těm vlastním.

### Vygenerování

```bash
./bin-docker/php bin/console app:push:generate-vapid-keys
```

Na serveru bez Dockeru `php bin/console app:push:generate-vapid-keys`. Příkaz klíče jen vypíše, nikam je neukládá.
Spusť ho třikrát: pro ostrou, betu a preview.

Soukromý klíč nepatří do gitu, do logu ani do zprávy v chatu. **Výměna klíčů prostředí ukončí všechny notifikace,
které si uživatelé s původními zapnuli**; musí si je zapnout znovu.

### Kam klíče uložit

| Prostředí | GitHub secrets | Jak se dostanou do aplikace |
|---|---|---|
| ostrá | `OSTRA_VAPID_PUBLIC_KEY`, `OSTRA_VAPID_PRIVATE_KEY` | `deploy-ostra.yml` → `nasad.php` → `nastaveni-produkce.php` |
| beta | `BETA_VAPID_PUBLIC_KEY`, `BETA_VAPID_PRIVATE_KEY` | `deploy-beta.yml` → `nasad.php` → `nastaveni-beta.php` |
| preview | `PREVIEW_VAPID_PUBLIC_KEY`, `PREVIEW_VAPID_PRIVATE_KEY` | `deploy-preview.yml` (build-args) → `Dockerfile.preview` (`ENV`) |
| archivy | žádné | notifikace jsou v archivech vypnuté |
| lokál | proměnné `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY` v `.env` vedle `docker-compose.yml` | `docker-compose.yml` → `nastaveni-local-default.php` |

Všechna preview sdílí jeden pár, liší se adresou webu. Pár preview je zapečený v image (GHCR repozitář je
privátní); není to tajemství ostré a ostrá v Dockeru neběží, takže vrstvu s ním s ničím nesdílí.

Po uložení secrets je potřeba prostředí nasadit znovu, klíče se do nastavení zapisují při nasazení.

## Co ověřit na serveru ostré a bety

Ostrá a beta neběží v Dockeru. Knihovna `minishlink/web-push` potřebuje rozšíření PHP **curl, json, mbstring a
openssl s eliptickými křivkami**; **bcmath nebo gmp** jen zrychlují výpočty (bcmath projekt vyžaduje už dnes).
Ověř to pro PHP webu i pro příkazovou řádku, mohou mít různé nastavení:

```bash
php -m | grep -iE '^(curl|json|mbstring|openssl|bcmath|gmp)$'
```

```bash
php -r 'var_dump(in_array("prime256v1", openssl_get_curve_names(), true));'
```

Pro PHP webu totéž ukáže `phpinfo()` (sekce openssl, curl, mbstring, bcmath).

Server musí smět ven na port 443 těchto hostitelů:

- `fcm.googleapis.com`, `jmt17.google.com` (Chrome, Edge na Androidu, Opera, Samsung Internet)
- `*.push.services.mozilla.com` (Firefox)
- `*.push.apple.com` (Safari na macOS, web na ploše v iOS)
- `*.notify.windows.com` (Edge na Windows)

```bash
curl -sS -o /dev/null -w '%{http_code}\n' https://fcm.googleapis.com/
```

Jakákoli odpověď HTTP (i 404) znamená, že spojení projde; chyba spojení ne. Na jiné hostitele aplikace
notifikace neposílá (`OvereniSubscription::PUSH_SLUZBY`); když se objeví prohlížeč s jinou push službou, najdeš
odmítnutého hostitele v logu a seznam doplníš.

## Postup nasazení

1. Vygeneruj tři páry klíčů a ulož je do GitHub secrets podle tabulky výš.
2. Ověř rozšíření PHP a odchozí spojení na serveru ostré a bety.
3. Nasaď. Migrace založí tabulky, právo 119 a vypnutý přepínač.
4. **Preview:** po nasazení větve zapni přepínač v adminu preview a otestuj podle tabulky níž.
5. **Ostrá:** přepínač zapni, až projdou testy na preview. Zapnutí znamená zapnutí pro všechny přihlášené;
   úzký okruh testerů na ostré není.

Vypnutí: přepínač v Nastavení. Uložená zařízení zůstanou v databázi a po zapnutí fungují dál.

## Kde co najdu

- Ovládání pro uživatele: web, Nastavení (`/registrace`), sekce „Notifikace na tomto zařízení“.
- Odeslání: admin, Web, **Push notifikace** (vlastní text) a **Testovací notifikace** (tlačítka s připravenými
  notifikacemi). Příjemce je uživatel vybraný nahoře v adminu.
- Log: `logy/push-notifikace-RRRR-MM.log`, řádek za každé zařízení (kdo, komu, druh, push služba, výsledek).
  Endpoint, klíče ani obsah notifikace v něm nejsou.
- Tabulky: `uzivatele_push_subscriptions` (zařízení), `push_notifikace_odeslani` (kdo komu kdy poslal, 90 dní).
  Anonymizovaná databáze obě vyprázdní.

## Výsledek odeslání v adminu

| Výsledek | Co znamená |
|---|---|
| předáno push službě | Služba prohlížeče zprávu převzala (HTTP 2xx). **Neznamená to, že ji zařízení ukázalo**: to server nezjistí. Doručí ji, až bude zařízení dostupné, nejdéle po dobu uvedenou u druhu notifikace. |
| neplatná, smazána | Služba subscription nezná (HTTP 404 nebo 410): uživatel notifikace zrušil v prohlížeči nebo web odinstaloval. Zařízení je ze seznamu smazané. |
| chyba | Cokoli jiného (služba neodpověděla, odmítla podpis, je přetížená). Zařízení zůstává, u něj roste počet chyb v řadě. HTTP 401 nebo 403 obvykle znamená, že se změnily VAPID klíče prostředí. |

## Ruční testování

Automatické testy posílají na falešnou push službu. Že notifikace dorazí na skutečné zařízení, ověří jen člověk.

### Příprava

1. Preview větve (`<slug>.preview.gamecon.cz`) s nastavenými klíči, v adminu zapnutý přepínač.
2. Uživatel A: účet, kterému se posílá. Uživatel B: účet s právem 119 (role Dev).
3. A se na zařízení přihlásí **se zaškrtnutým „Trvale přihlásit“**, v Nastavení klikne na Zapnout notifikace
   a povolí je.
4. B v adminu vybere A jako uživatele a na stránce Testovací notifikace mačká tlačítka.

**Brána před preview.** Preview je za bránou (basic auth, nebo přihlášení organizátora přes admin). Vstup přes
odkaz ze stránky Previews v adminu nebo přes přihlášení v adminu nastaví cookie, se kterou projdou i service
worker, manifest a API. Samotný dialog basic auth může registraci service workeru v Safari zablokovat, proto
vstupuj odkazem. Web na ploše v iOS má vlastní cookies: po přidání na plochu se v něm bránou i do webu
přihlašuješ znovu. Když to na preview neprojde, zkoušej na betě.

**iOS.** Notifikace fungují jen z webu přidaného na plochu, od iOS 16.4: v Safari Sdílet → Přidat na plochu,
otevřít z plochy, přihlásit se, zapnout v Nastavení.

### Tabulka k vyplnění

Výsledek: OK / nedorazila / dorazila pozdě / jiné. Prodleva: od kliknutí v adminu po zobrazení.

| Zařízení | OS a verze | Prohlížeč | Scénář | Výsledek | Prodleva doručení | Poznámka |
|---|---|---|---|---|---|---|
| | | | otevřený web | | | |
| | | | web na pozadí (jiná karta, jiná aplikace) | | | |
| | | | zavřený prohlížeč | | | |
| | | | zamčený telefon | | | |
| | | | úsporný režim (tlačítko Nízká urgence i Připomínka aktivity) | | | |
| | | | telefon delší dobu v nečinnosti (30+ minut) | | | |
| | | | dočasně bez internetu, pak zpět (do 15 minut a po 15 minutách u Připomínky) | | | |
| | | | víc zařízení jednoho uživatele | | | |
| | | | víc účtů (notifikaci dostane jen vybraný) | | | |
| | | | odhlášení (pak nesmí dorazit) | | | |
| | | | „Odhlásit ostatní zařízení“ z jiného zařízení (pak nesmí dorazit) | | | |
| | | | přihlášení jiného uživatele na stejném zařízení (předchozímu nesmí dorazit) | | | |
| | | | odebrané oprávnění v prohlížeči | | | |
| | | | neplatná subscription (zrušená v prohlížeči, v adminu „neplatná, smazána“) | | | |
| | | | opakovaná registrace (vypnout a zapnout, v adminu jedno zařízení) | | | |
| | | | pokus o odeslání bez práva (stránka v adminu se neotevře) | | | |
| | | | kliknutí na notifikaci (Připomínka otevře Můj program) | | | |
| | | | Změna aktivity po Připomínce (zůstane jen jedna notifikace) | | | |

Zařízení k otestování: Android Chrome, iOS Safari (web na ploše), Windows Chrome, Windows Edge, případně
Firefox a macOS Safari. Pro každé zařízení zkopíruj řádky scénářů, které na něm dávají smysl.

**Klíčový scénář:** B odešle z počítače, A má telefon zamčený a prohlížeč zavřený, notifikace se ukáže na
zamčené obrazovce a klepnutí otevře Můj program.

## Známá omezení platforem

- **iOS a iPadOS:** jen web přidaný na plochu, iOS 16.4+. Safari v prohlížeči push neumí vůbec. Web na ploše
  má vlastní úložiště a cookies, oddělené od Safari. Oprávnění se uděluje webu na ploše, v Nastavení telefonu
  je pak pod jeho názvem. Když uživatel web z plochy smaže, notifikace skončí.
- **Android:** výrobci (Xiaomi, Huawei, Samsung a další) omezují aplikace na pozadí; v úsporném režimu a po
  delší nečinnosti může notifikace s normální nebo nízkou urgencí dorazit se zpožděním. Vysoká urgence
  (připomínka, zrušení, změna aktivity) má telefon probudit.
- **Počítače:** notifikace dorazí, jen když prohlížeč běží (stačí na pozadí). Chrome a Edge na Windows a macOS
  obvykle běží na pozadí i po zavření okna, Firefox ne. V anonymním okně push nefunguje.
- **Všude:** oprávnění odebrané v prohlížeči nejde vrátit ze stránky, uživatel ho musí povolit v nastavení
  prohlížeče (návod mu stránka ukáže). Push služba drží nedoručenou zprávu jen po dobu TTL druhu notifikace.
- **Server neví, jestli notifikace dorazila.** Ví jen, že ji převzala push služba.
- **Ostrá a beta, sdílený počítač:** notifikace patří k trvalému přihlášení webu. Kdo se na zařízení po někom
  přihlásí jen do administrace (má vlastní adresu) a web neotevře, tomu notifikace předchozího uživatele chodí,
  dokud neotevře stránku webu. Odhlášení v administraci trvalé přihlášení webu neruší, a tak ani notifikace.
  Na preview se to neprojeví, admin tam běží na stejné adrese jako web.
- **Jeden uživatel, jedno přihlášení:** notifikace patří k trvalému přihlášení zařízení. Bez „Trvale přihlásit“
  zapnout nejdou a po novém přihlášení heslem je potřeba je zapnout znovu.
