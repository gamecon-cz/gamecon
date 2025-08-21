<?php

use Gamecon\Kanaly\GcMail;
use Gamecon\Kanaly\Exceptions\ChybiEmailoveNastaveni;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Gamecon\XTemplate\XTemplate;


/**
 * nazev: Mail Neplaticum
 * pravo: 108
 * submenu_group: 9
 */

/**
 * @var Uzivatel $u
 * @var Uzivatel|null $uPracovni
 * @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni
 */

$x = new XTemplate(__DIR__ . '/mail-neplaticum.xtpl');

if (isset($_POST['odeslat_maily'])) {
    $db = \Db::get();

    $q = <<<SQL
        SELECT 
            id_uzivatele, 
            email1_uzivatele,
            jmeno_uzivatele, 
            prijmeni_uzivatele, 
            zustatek
        FROM uzivatele_hodnoty
        WHERE zustatek < 0
          AND id_uzivatele <> 1
    SQL;

    $rows = $db->query($q)->fetchAll(PDO::FETCH_ASSOC);

    $ok = 0;
    $fail = 0;
    $fails = [];

    foreach ($rows as $r) {
        $email = trim((string)$r['email1_uzivatele']);
        if ($email === '') {
            $fail++;
            $fails[] = ['email' => '(prázdný email u ID ' . (int)$r['id_uzivatele'] . ')', 'chyba' => 'Chybí adresa'];
            continue;
        }

        $jmeno = trim($r['jmeno_uzivatele'] . ' ' . $r['prijmeni_uzivatele']);
        $castka = (float)$r['zustatek']; // záporná

        // --- jednoduchý HTML obsah mailu (můžeš vyměnit za XTemplate pro e-mail) ---
        $predmet = 'Upomínka – záporný zůstatek';
        $html = <<<HTML
<html><body style="font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;font-size:15px;color:#111;">
  <p>Ahoj {$jmeno},</p>
  <p>evidujeme u tebe <strong>záporný zůstatek {$castka} Kč</strong>. Prosíme o úhradu co nejdříve.</p>
  <p>
    <a href="https://gamecon.cz/platby" 
       style="display:inline-block;padding:10px 14px;border-radius:10px;background:#2563eb;color:#fff;text-decoration:none;">
       Zaplatit teď
    </a>
  </p>
  <p>Díky,<br>tým GameCon</p>
</body></html>
HTML;

        try {
            $mail = GcMail::vytvorZGlobals()
                ->predmet($predmet)
                ->text($html)
                ->adresat("{$jmeno} <{$email}>");

            $odeslano = $mail->odeslat(GcMail::FORMAT_HTML);
            if ($odeslano) {
                $ok++;
            } else {
                $fail++;
                $fails[] = ['email' => $email, 'chyba' => 'Neznámý problém při odesílání'];
            }
        } catch (ChybiEmailoveNastaveni $e) {
            $fail++;
            $fails[] = ['email' => $email, 'chyba' => 'Chybí MAILER_DSN'];
        } catch (TransportExceptionInterface $e) {
            $fail++;
            $fails[] = ['email' => $email, 'chyba' => 'SMTP/Transport: ' . $e->getMessage()];
        } catch (\Throwable $e) {
            $fail++;
            $fails[] = ['email' => $email, 'chyba' => $e->getMessage()];
        }
    }

    // naplnění výsledků do šablony
    $x->assign('POCET_OK', $ok);
    $x->assign('POCET_FAIL', $fail);
    foreach ($fails as $f) {
        $x->assign('EMAIL', $f['email']);
        $x->assign('CHYBA', $f['chyba']);
        $x->parse('vysledek.failradek');
    }
    $x->parse('vysledek');
}

$x->parse('mailneplaticum');
$x->out('mailneplaticum');