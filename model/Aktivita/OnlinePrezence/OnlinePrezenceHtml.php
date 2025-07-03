<?php declare(strict_types=1);

namespace Gamecon\Aktivita\OnlinePrezence;

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\AktivitaPrezence;
use Gamecon\Aktivita\RazitkoPosledniZmenyPrihlaseni;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Web\Info;
use Symfony\Component\Filesystem\Filesystem;
use Gamecon\XTemplate\XTemplate;

class OnlinePrezenceHtml
{
    public static function nazevProAnchor(Aktivita $aktivita): string
    {
        return implode(' – ', array_filter([$aktivita->nazev(), $aktivita->orgJmena(), $aktivita->popisLokaci()]));
    }

    private ?XTemplate                  $onlinePrezenceTemplate     = null;
    private ?OnlinePrezenceUcastnikHtml $onlinePrezenceUcastnikHtml = null;
    private bool                        $testujeme;

    public function __construct(
        private readonly string             $jsVyjimkovac,
        private readonly SystemoveNastaveni $systemoveNastaveni,
        private readonly Filesystem         $filesystem,
        private readonly bool               $muzemeTestovat = false,
        bool                                $testujeme = false,
    )
    {
        $this->testujeme = $muzemeTestovat && $testujeme;
    }

    public function dejHtmlOnlinePrezence(
        \Uzivatel $editujici,
        array     $organizovaneAktivity,
    ): string
    {
        $template = $this->dejOnlinePrezenceTemplate();

        if ($this->muzemeTestovat) {
            if ($this->testujeme) {
                $template->assign('urlBezTestu', getCurrentUrlWithQuery(['test' => 0]));
                $template->parse('onlinePrezence.test.odkazBezTestu');
            } else {
                $template->assign('urlTest', getCurrentUrlWithQuery(['test' => 1]));
                $template->parse('onlinePrezence.test.odkazNaTest');
            }
            $template->parse('onlinePrezence.test');
        }

        ['url' => $urlZpet, 'nazev' => $textZpet] = $editujici->mimoMojeAktivityUvodniAdminLink(
            URL_ADMIN,
            URL_WEBU,
        );
        $template->assign('urlZpet', $urlZpet);
        $template->assign('textZpet', $textZpet);
        $template->assign('jsVyjimkovac', $this->jsVyjimkovac);
        $template->assign('a', $editujici->koncovkaDlePohlavi());

        $this->pridejLokalniAssety($template);

        if (count($organizovaneAktivity) === 0) {
            $template->parse('onlinePrezence.zadnaAktivita');
        } else {
            $this->sestavHtmlOnlinePrezence($template, $editujici, $organizovaneAktivity);
        }

        $template->parse('onlinePrezence');
        return $template->text('onlinePrezence');
    }

    private function pridejLokalniAssety(XTemplate $template)
    {
        static $localAssets = [
            'stylesheets' => [
                __DIR__ . '/../../../admin/files/design/hint.css',
                __DIR__ . '/../../../admin/files/design/zvyraznena-tabulka.css',
                __DIR__ . '/../../../admin/files/design/online-prezence.css',
            ],
            'javascripts' => [
                'text'   => [
                    __DIR__ . '/../../../admin/files/omnibox-1.1.4.js',
                    __DIR__ . '/../../../admin/files/zablikej-1.0.js',
                    __DIR__ . '/../../../admin/files/online-prezence/online-prezence-heat-colors.js',
                    __DIR__ . '/../../../admin/files/online-prezence/online-prezence-tooltip.js',
                    __DIR__ . '/../../../admin/files/online-prezence/online-prezence-prepinani-viditelnosti.js',
                    __DIR__ . '/../../../admin/files/online-prezence/online-prezence-ukazatele-zaplnenosti.js',
                    __DIR__ . '/../../../admin/files/online-prezence/online-prezence-potvrzovaci-modal.js',
                    __DIR__ . '/../../../admin/files/online-prezence/online-prezence-keep-alive.js',
                ],
                /*
                 * Pozor, JS moduly se automaticky načítají jako deffer, tedy asynchronně a vykonávají se až někdy po načtení celé stránky.
                 * Zároveň kód načtený jako module nejde volat z HTML.
                 */
                'module' => [
                    __DIR__ . '/../../../admin/files/online-prezence/online-prezence.js',
                    __DIR__ . '/../../../admin/files/online-prezence/online-prezence-posledni-zname-zmeny-prihlaseni.js',
                    __DIR__ . '/../../../admin/files/online-prezence/online-prezence-errors.js',
                    __DIR__ . '/../../../admin/files/online-prezence/online-prezence-sort.js',
                ],
            ],
        ];
        foreach ($localAssets['stylesheets'] as $stylesheet) {
            $template->assign('url', str_replace(__DIR__ . '/../../../admin/', '', $stylesheet));
            $template->assign('version', md5_file($stylesheet));
            $template->parse('onlinePrezence.stylesheet');
        }
        foreach ($localAssets['javascripts'] as $type => $javascripts) {
            foreach ($javascripts as $javascript) {
                $template->assign('url', str_replace(__DIR__ . '/../../../admin/', '', $javascript));
                $template->assign('version', md5_file($javascript));
                if ($type === 'module') {
                    $template->parse('onlinePrezence.javascript.module');
                } else {
                    $template->parse('onlinePrezence.javascript.text');
                }
            }
            $template->parse('onlinePrezence.javascript');
        }
    }

    private function dejOnlinePrezenceTemplate(): XTemplate
    {
        if ($this->onlinePrezenceTemplate === null) {
            $this->onlinePrezenceTemplate = new XTemplate(__DIR__ . '/templates/online-prezence.xtpl');
            $this->onlinePrezenceTemplate->assign(
                'title',
                (new Info($this->systemoveNastaveni))->pridejPrefixPodleVyvoje('Online prezence'),
            );
        }
        return $this->onlinePrezenceTemplate;
    }

    /**
     * @param XTemplate $template
     * @param \Uzivatel $vypravec
     * @param Aktivita[] $organizovaneAktivity
     * @return void
     */
    private function sestavHtmlOnlinePrezence(
        XTemplate $template,
        \Uzivatel $vypravec,
        array     $organizovaneAktivity,
    )
    {
        foreach ($organizovaneAktivity as $aktivita) {
            $editovatelnaOdTimestamp         = $this->dejEditovatelnaOdTimestamp($aktivita);
            $ucastniciPridatelniDoTimestamp  = $this->ucastniciPridatelniDoTimestamp($vypravec, $aktivita);
            $ucastniciOdebratelniDoTimestamp = $this->ucastniciOdebratelniDoTimestamp($vypravec, $aktivita);
            $pridatelniHned                  = $editovatelnaOdTimestamp <= 0 && $ucastniciPridatelniDoTimestamp > 0;
            $odebratelniHned                 = $editovatelnaOdTimestamp <= 0 && $ucastniciOdebratelniDoTimestamp > 0;
            $nejdouAlePujdouPridat           = !$pridatelniHned && $ucastniciPridatelniDoTimestamp > 0;
            $nejdouAlePujdouOdebrat          = !$odebratelniHned && $ucastniciOdebratelniDoTimestamp > 0;
            $uzNepujdePridat                 = $ucastniciPridatelniDoTimestamp <= 0;
            $uzNepujdeOdebrat                = $ucastniciOdebratelniDoTimestamp <= 0;
            $zamcena                         = $aktivita->zamcena();
            $uzavrena                        = $aktivita->uzavrena();
            $neuzavrena                      = !$uzavrena;
            $muzePridatUcastnikyHned         = $pridatelniHned; // TODO připraveno pro právo na změnu historie aktivit
            $muzeOdebratUcastnikyHned        = $odebratelniHned; // TODO připraveno pro právo na změnu historie aktivit
            $zmenaStavuAktivity              = $aktivita->posledniZmenaStavuAktivity();
            $konec                           = $aktivita->konec();

            $template->assign(
                'omniboxUrl',
                getCurrentUrlWithQuery(['ajax' => 1, 'omnibox' => 1, 'idAktivity' => $aktivita->id()]),
            );
            $template->assign('konecAktivityVTimestamp', $konec ? $konec->getTimestamp() : null);
            $template->assign('editovatelnaOdTimestamp', $editovatelnaOdTimestamp);
            $template->assign('ucastniciPridatelniDoTimestamp', $ucastniciPridatelniDoTimestamp);
            $template->assign('ucastniciOdebratelniDoTimestamp', $ucastniciOdebratelniDoTimestamp);
            $template->assign('minutNaPosledniChvili', $this->systemoveNastaveni->prihlaseniNaPosledniChviliXMinutPredZacatkemAktivity());
            $template->assign('kapacita', (int)$aktivita->kapacita());
            $template->assign('idAktivity', $aktivita->id());
            $template->assign('casPosledniZmenyStavuAktivity', $zmenaStavuAktivity ? $zmenaStavuAktivity->casZmenyProJs() : '');
            $template->assign('stavAktivity', $zmenaStavuAktivity ? $zmenaStavuAktivity->stavAktivityProJs() : '');
            $template->assign('idPoslednihoLogu', $zmenaStavuAktivity ? $zmenaStavuAktivity->idLogu() : 0);

            // ⏳ Můžeš ji editovat za ⏳
            $template->assign(
                'showCeka',
                $this->cssZobrazitKdyz($nejdouAlePujdouPridat && $nejdouAlePujdouOdebrat),
            );
            // 🔒 Zamčena pro online přihlašování 🔒
            $template->assign(
                'showZamcena',
                $this->cssZobrazitKdyz($zamcena),
            );
            // Uzavřít 📕
            $template->assign(
                'showUzavrit',
                $this->cssZobrazitKdyz($neuzavrena && !$nejdouAlePujdouPridat),
            );
            // 🧊 ️Už ji nelze editovat ani zpětně 🧊️
            $template->assign(
                'showUzNeeditovatelna',
                $this->cssZobrazitKdyz($neuzavrena && $uzNepujdePridat && $uzNepujdeOdebrat),
            );
            // 📕 Uzavřena 📕
            $template->assign(
                'showUzavrena',
                $this->cssZobrazitKdyz($uzavrena),
            );
            // ✋ Aktivita už skončila, pozor na úpravy ✋
            $template->assign(
                'showAktivitaSkoncila',
                // zobrazíme pouze v případě, že aktivitu lze editovat i po skončení (není tedy ještě uzavřená, nebo má editující zvláštní právo)
                $this->cssZobrazitKdyz(
                    ($muzePridatUcastnikyHned && !$pridatelniHned)
                    || ($muzeOdebratUcastnikyHned && !$odebratelniHned),
                ),
            );
            // ⚠️Pozor, aktivita je už uzavřená! ⚠️
            $template->assign( // určeno pro zvláštní admin právo
                'showPozorUzavrena',
                $this->cssZobrazitKdyz($uzavrena && ($muzePridatUcastnikyHned || $muzeOdebratUcastnikyHned)),
            );

            // případnou změnu je nutné promítnout i do online-prezence.js pridejEmailUcastnikaDoSeznamu()
            $emaily = $this->emailyUcastniku($aktivita);
            $template->assign('emailyHref', implode(',', $emaily));
            $template->assign('emailyText', implode(', ', $emaily));

            $prihlaseni = $this->seradDleStavuPrihlaseni($aktivita->prihlaseni(), $aktivita);
            foreach ($prihlaseni as $ucastnik) {
                $ucastnikHtml = $this->dejOnlinePrezenceUcastnikHtml()->sestavHmlUcastnikaAktivity(
                    $ucastnik,
                    $aktivita,
                    $aktivita->stavPrihlaseni($ucastnik),
                    $muzePridatUcastnikyHned,
                    $muzeOdebratUcastnikyHned,
                );
                $template->assign('ucastnikHtml', $ucastnikHtml);
                $template->parse('onlinePrezence.aktivity.aktivita.form.ucastnik');
            }

            $seznamSledujicich = $this->seradDleStavuPrihlaseni($aktivita->seznamSledujicich(), $aktivita);
            foreach ($seznamSledujicich as $sledujici) {
                $sledujiciHtml = $this->dejOnlinePrezenceUcastnikHtml()->sestavHmlUcastnikaAktivity(
                    $sledujici,
                    $aktivita,
                    $aktivita->stavPrihlaseni($sledujici),
                    $muzePridatUcastnikyHned,
                    $muzeOdebratUcastnikyHned,
                );
                $template->assign('ucastnikHtml', $sledujiciHtml);
                $template->parse('onlinePrezence.aktivity.aktivita.form.ucastnik');
            }

            $template->assign('disabledPridatUcastnika', $muzePridatUcastnikyHned ? '' : 'disabled');
            $template->assign('idAktivity', $aktivita->id());
            $template->assign('urlAktivity', $aktivita->url());
            $template->parse('onlinePrezence.aktivity.aktivita.form.pridatUcastnika');

            $template->assign('nadpis', self::nazevProAnchor($aktivita));
            $template->assign('pocetPrihlasenych', $aktivita->pocetPrihlasenych());
            $template->assign('zacatek', $aktivita->zacatek() ? $aktivita->zacatek()->format('l H:i') : '-nevíme-');
            $template->assign('konec', $aktivita->konec() ? $aktivita->konec()->format('l H:i') : '-nevíme-');

            $jePrezenceRozbalena = $this->jePrezenceRozbalena($aktivita);
            $template->assign('showMinimize', $this->cssZobrazitKdyz($jePrezenceRozbalena));
            $template->assign('showMaximize', $this->cssZobrazitKdyz(!$jePrezenceRozbalena));

            $template->parse('onlinePrezence.aktivity.aktivita.form');

            $template->parse('onlinePrezence.aktivity.aktivita');
        }
        $razitkoPosledniZmeny = new RazitkoPosledniZmenyPrihlaseni(
            $vypravec,
            Aktivita::posledniZmenaStavuAktivit($organizovaneAktivity),
            AktivitaPrezence::posledniZmenaPrihlaseniAktivit(
                null, // bereme každého účastníka
                $organizovaneAktivity,
            ),
            $this->filesystem,
            OnlinePrezenceAjax::RAZITKO_POSLEDNI_ZMENY
        );
        $template->assign('razitkoPosledniZmeny', $razitkoPosledniZmeny->dejPotvrzeneRazitkoPosledniZmeny());
        $template->assign('urlRazitkaPosledniZmeny', $razitkoPosledniZmeny->dejUrlRazitkaPosledniZmeny());
        $template->assign('urlAkcePosledniZmeny', OnlinePrezenceAjax::dejUrlAkcePosledniZmeny());
        $template->assign('urlAkceKeepAlive', OnlinePrezenceAjax::dejUrlAkceKeepAlive());
        $template->assign('posledniLogyAktivitAjaxKlic', OnlinePrezenceAjax::POSLEDNI_LOGY_AKTIVIT_AJAX_KLIC);
        $template->assign('posledniLogyUcastnikuAjaxKlic', OnlinePrezenceAjax::POSLEDNI_LOGY_UCASTNIKU_AJAX_KLIC);

        $template->parse('onlinePrezence.aktivity');
    }

    private function jePrezenceRozbalena(Aktivita $aktivita): bool
    {
        return $aktivita->cenaZaklad() > 0 // u aktivit zadarmo nás prezence tolik nezajímá a ještě k tomu mívají strašně moc účastníků, přednášky třeba
            || !$aktivita->uzavrena();
    }

    /**
     * @param Aktivita $aktivita
     * @return string[]
     */
    private function emailyUcastniku(Aktivita $aktivita): array
    {
        return array_map(
            static function (\Uzivatel $ucastnik) {
                return trim((string)$ucastnik->mail());
            },
            $aktivita->prihlaseni(),
        );
    }

    /**
     * @param \Uzivatel[] $prihlaseni
     * @return \Uzivatel[]
     */
    private function seradDleStavuPrihlaseni(array $prihlaseni, Aktivita $aktivita): array
    {
        usort($prihlaseni, function (\Uzivatel $nejakyPrihlaseny, $jinyPrihlaseny) use ($aktivita) {
            // běžní účastníci první, náhradníci druzí, sledující poslední
            $porovnaniStavuPrihlaseni = $aktivita->stavPrihlaseni($nejakyPrihlaseny) <=> $aktivita->stavPrihlaseni($jinyPrihlaseny);
            if ($porovnaniStavuPrihlaseni !== 0) {
                return $porovnaniStavuPrihlaseni;
            }
            $prezence = $aktivita->dejPrezenci();
            // později přidaný / změněný nakonec
            $nejakaPosledniZmena = $prezence->posledniZmenaPrihlaseni($nejakyPrihlaseny);
            $jinaPosledniZmena   = $prezence->posledniZmenaPrihlaseni($jinyPrihlaseny);
            if ($nejakaPosledniZmena === null || $jinaPosledniZmena === null) {
                return $nejakaPosledniZmena <=> $jinaPosledniZmena;
            }
            return $nejakaPosledniZmena->idLogu() <=> $jinaPosledniZmena->idLogu();
        });
        return $prihlaseni;
    }

    private function dejEditovatelnaOdTimestamp(Aktivita $aktivita): int
    {
        $zacatek = $aktivita->zacatek();
        if (!$zacatek) {
            return 0;
        }
        $hnedEditovatelnaSeZacatkemDo = (clone $zacatek)
            ->modify("-{$this->systemoveNastaveni->aktivitaEditovatelnaXMinutPredJejimZacatkem()} minutes");
        return $hnedEditovatelnaSeZacatkemDo <= $this->systemoveNastaveni->ted()
            ? 0 // aktivitu může editovat hned
            // pokud je editovatelná například od 12:10, ale "teď" je 12:00, tak musíme počkat oněch rozdílových 10 minut
            : time() + ($hnedEditovatelnaSeZacatkemDo->getTimestamp() - $this->systemoveNastaveni->ted()->getTimestamp());
    }

    private function ucastniciOdebratelniDoTimestamp(\Uzivatel $odhlasujici, Aktivita $aktivita): int
    {
        $ucastniciOdebratelniDo = $aktivita->ucastniciOdebratelniDo($odhlasujici);
        return $ucastniciOdebratelniDo <= $this->systemoveNastaveni->ted()
            ? 0 // už je nelze odebrat
            : $ucastniciOdebratelniDo->getTimestamp();
    }

    private function ucastniciPridatelniDoTimestamp(\Uzivatel $prihlasujici, Aktivita $aktivita): int
    {
        $ucastniciPridatelniDo = $aktivita->ucastniciPridatelniDo($prihlasujici, $this->systemoveNastaveni);
        return $ucastniciPridatelniDo <= $this->systemoveNastaveni->ted()
            ? 0 // už je nelze přidat
            : $ucastniciPridatelniDo->getTimestamp();
    }

    private function cssZobrazitKdyz(bool $zobrazit): string
    {
        return $zobrazit
            ? ''
            : 'display-none';
    }

    private function dejOnlinePrezenceUcastnikHtml(): OnlinePrezenceUcastnikHtml
    {
        if (!$this->onlinePrezenceUcastnikHtml) {
            $this->onlinePrezenceUcastnikHtml = new OnlinePrezenceUcastnikHtml($this->systemoveNastaveni);
        }
        return $this->onlinePrezenceUcastnikHtml;
    }

    public function sestavHmlUcastnikaAktivity(
        \Uzivatel $ucastnik,
        Aktivita  $aktivita,
        \Uzivatel $vypravec,
        int       $stavPrihlaseni,
    ): string
    {
        // i při "Testovat" (bool $this->testujeme) tohle vrací skutečný čas namísto potřebného testovacího, takže to často vrátí zablokvaný checkbox
        $editovatelnaOdTimestamp         = $this->dejEditovatelnaOdTimestamp($aktivita);
        $ucastniciPridatelniDoTimestamp  = $this->ucastniciPridatelniDoTimestamp($vypravec, $aktivita);
        $ucastniciOdebratelniDoTimestamp = $this->ucastniciOdebratelniDoTimestamp($vypravec, $aktivita);
        $pridatelnyHned                  = $editovatelnaOdTimestamp <= 0 && $ucastniciPridatelniDoTimestamp > 0;
        $odebratelnyHned                 = $editovatelnaOdTimestamp <= 0 && $ucastniciOdebratelniDoTimestamp > 0;

        return $this->dejOnlinePrezenceUcastnikHtml()
            ->sestavHmlUcastnikaAktivity($ucastnik, $aktivita, $stavPrihlaseni, $pridatelnyHned, $odebratelnyHned);
    }
}
