<?php

declare(strict_types=1);

namespace Gamecon\Aktivita\Korektura;

/**
 * The file a proofreader hands to an AI chat: instructions first, then the texts in tags.
 * Tags rather than JSON, as a long markdown text in JSON has to be escaped and that is where
 * a model goes wrong; one broken tag also spoils only its own activity, not the whole answer.
 *
 * @see OdpovedAi reads the answer back
 */
class SouborKorektur
{
    public const ZNACKA_AKTIVITA = 'gc-aktivita';
    public const ZNACKA_NAZEV = 'gc-nazev';
    public const ZNACKA_POPIS_KRATKY = 'gc-popis-kratky';
    public const ZNACKA_POPIS = 'gc-popis';
    public const ZNACKA_ZMENY = 'gc-zmeny';
    public const ZNACKA_DRIVEJSI = 'gc-drivejsi';

    private const string POKYNY = <<<'TEXT'
# Korektura textů aktivit GameConu %rocnik%

Jsi zkušený korektor češtiny. Níže jsou texty aktivit GameConu, největšího festivalu nepočítačových her v Česku a na Slovensku. Napsali je pořadatelé aktivit a vyjdou v programu festivalu. Oprav v nich chyby.

## Co opravuj

- pravopis, překlepy, interpunkci (hlavně čárky), shodu podmětu s přísudkem, velká písmena
- typografii: české uvozovky „takto“, pomlčku s mezerami mezi slovy ( – ), tři tečky …, mezeru mezi číslem a jednotkou
- gramaticky rozbité věty, ale jen nejmenším zásahem, který je spraví

## Co neměň

- smysl, obsah, styl, tón ani oslovení (tykání a vykání). Texty jsou často záměrně hravé a to je v pořádku.
- názvy her, světů, postav a herních systémů ani zavedené anglické herní termíny
- markdown (nadpisy, odrážky, tučné písmo, odkazy), HTML značky jako <p hidden>…</p> i s jejich obsahem a odkazy a adresy, ty nech přesně tak, jak jsou
- název aktivity, pokud v něm není zjevná chyba

## Dřívější opravená verze

U některých aktivit je v bloku <gc-drivejsi> verze stejnojmenné aktivity z dřívějšího ročníku, kterou už opravil korektor. Kde se letošní text liší jen chybou, kterou tehdy korektor opravil, převezmi jeho opravu. Kde se text věcně změnil, oprav ho běžně. Samotnou dřívější verzi neopravuj a nevracej.

## Formát odpovědi

Odpověz jen bloky <gc-aktivita> ve stejném pořadí jako na vstupu, bez dalšího textu okolo.

- Pole, ve kterém něco měníš, vrať celé, ne jen opravené místo. Pole beze změny vynech.
- Do <gc-zmeny> napiš na jeden řádek, co jsi změnil.
- Aktivitu bez chyb vrať jako prázdný blok, ať je vidět, že jsi ji zkontroloval.
- Nevynechej žádnou aktivitu. Když se celá odpověď nevejde, skonči po celém bloku a pokračuj dalším blokem, až tě o to požádám.

Příklad odpovědi:

<gc-aktivita id="123">
<gc-popis>
Celý opravený popis…
</gc-popis>
<gc-zmeny>čárka před „který“, české uvozovky</gc-zmeny>
</gc-aktivita>
<gc-aktivita id="124"></gc-aktivita>

## Texty ke korektuře
TEXT;

    /**
     * @param array<KorekturaTextu>     $korektury
     * @param array<int, DrivejsiTexty> $drivejsiPodleAktivit by activity ID
     */
    public function vytvor(array $korektury, array $drivejsiPodleAktivit, int $rocnik): string
    {
        $bloky = [str_replace('%rocnik%', (string) $rocnik, self::POKYNY)];
        foreach ($korektury as $korektura) {
            $bloky[] = $this->blokAktivity($korektura, $drivejsiPodleAktivit[$korektura->idAktivity] ?? null);
        }

        return implode("\n\n", $bloky) . "\n";
    }

    public function nazevSouboru(int $rocnik, ?int $balik): string
    {
        return $balik !== null
            ? "korektura-gamecon-{$rocnik}-balik-{$balik}.txt"
            : "korektura-gamecon-{$rocnik}.txt";
    }

    private function blokAktivity(KorekturaTextu $korektura, ?DrivejsiTexty $drivejsi): string
    {
        $radky = ['<' . self::ZNACKA_AKTIVITA . ' id="' . $korektura->idAktivity . '">'];
        $radky[] = $this->texty($korektura->puvodni);
        if ($drivejsi !== null) {
            $radky[] = '<' . self::ZNACKA_DRIVEJSI . ' rok="' . $drivejsi->rok . '">';
            $radky[] = $this->texty($drivejsi->texty);
            $radky[] = '</' . self::ZNACKA_DRIVEJSI . '>';
        }
        $radky[] = '</' . self::ZNACKA_AKTIVITA . '>';

        return implode("\n", $radky);
    }

    private function texty(TextyAktivity $texty): string
    {
        $texty = $texty->normalizovane();

        return implode("\n", [
            $this->pole(self::ZNACKA_NAZEV, $texty->nazev),
            $this->pole(self::ZNACKA_POPIS_KRATKY, $texty->popisKratky),
            '<' . self::ZNACKA_POPIS . ">\n" . $texty->popis . "\n</" . self::ZNACKA_POPIS . '>',
        ]);
    }

    private function pole(string $znacka, string $text): string
    {
        return "<{$znacka}>{$text}</{$znacka}>";
    }
}
