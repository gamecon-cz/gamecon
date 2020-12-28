<?php

$this->blackarrowStyl(true);

$dny = [];
for ($den = new DateTimeCz(PROGRAM_OD); $den->pred(PROGRAM_DO); $den->plusDen()) {
    $dny[slugify($den->format('l'))] = clone $den;
}

$nastaveni = [];
$alternativniUrl = null;
if ($url->cast(1) == 'muj') {
    if (!$u) throw new Neprihlasen();
    $nastaveni['osobni'] = true;
} else if (isset($dny[$url->cast(1)])) {
    $nastaveni['den'] = $dny[$url->cast(1)]->format('z');
} else if (!$url->cast(1)) {
    $nastaveni['den'] = reset($dny)->format('z');
    $alternativniUrl = 'program/' . slugify(reset($dny)->format('l'));
} else {
    throw new Nenalezeno();
}

$program = new Program($u, $nastaveni);
$program->zpracujPost();

// pomocná funkce pro zobrazení aktivního odkazu
$odkaz = function ($urlOdkazu, $tridy, $tridaAktivni, $text) use ($url, $alternativniUrl) {
    $vysledneTridy = $tridy;
    if ($urlOdkazu == $url->cela() || $urlOdkazu == $alternativniUrl) {
        $vysledneTridy .= ' ' . $tridaAktivni;
    }
    return '<a href="'.$urlOdkazu.'" class="'.$vysledneTridy.'">'.$text.'</a>';
};

$zobrazitMujProgramOdkaz = isset($u);

?>

<style>
    .menu { position: initial; } /* TODO */

    .program_obaltabulky2 {
        position: relative;
        /*margin-right: 200px;*/
    }

    .program_obaltabulky {
        overflow-y: hidden;
        scrollbar-width: none;
        position: relative;
    }

    table.program {
        border-spacing: 10px;
        margin: -10px;
    }

    .program tr {
        border: none;
    }

    .program th {
        font-weight: normal;
        text-align: left;
        font-size: 14px;
        padding: 0;
        height: 20px;
        vertical-align: bottom;
    }

    .program th::before {
        content: '';
        display: block;
        position: absolute;
        height: 100%;
        border-right: dashed 1px #0002;
        margin: 28px 0 0 -1px;
    }
    .program th:nth-child(1)::before {
        display: none;
    }

    .program td[rowspan] {
        padding: 16px;
        font-weight: bold;
        font-size: 14px;
        vertical-align: top;
        position: sticky;
        left: 0;
    }

    .program td {
        min-width: 100px;
        max-width: 100px;
        padding: 0;
    }

    /*.program td a {
        font-size: 14px;
        font-weight: bold;
        text-decoration: none;
        color: inherit;
        display: block;
        margin: 15px 0 0 16px;
    }

    .program td span {
        font-size: 12px;
        display: block;
        margin: 12px 16px;
    }*/

    .program td > div {
        background-color: #F6F1EA;
        height: 72px;
        overflow: hidden;
    }

    .program_posuv {
        position: absolute;
        top: 30px;
        height: calc(100% - 30px);
    }

    .program_lposuv {
        left: 132px;
    }

    .program_rposuv {
        right: 0;
    }

    .program_posuv > div {
        position: sticky;
        top: 0;
        left: 0;
        width: 40px;
        height: 100vh;
        background-color: #E22630;
    }

    .program_posuv > div::before {
        content: '';
        display: block;
        position: absolute;
        top: 48%;
        left: 6px;
        width: 15px;
        height: 15px;
        border-bottom: solid 4px #fff;
        border-right:  solid 4px #fff;
        transform: rotate(-45deg);
    }
    .program_lposuv > div::before {
        left: 14px;
        transform: rotate(135deg);
    }

    .program .plno { background-color: #EBEBEB }
    .program .prihlasen { background-color: #B7E4B6 }
</style>

<div class="program_hlavicka">
    <h1>Program <?=ROK?></h1>
    <div class="program_dny">
        <?php foreach ($dny as $denSlug => $den)
            echo $odkaz('program/'.$denSlug, 'program_den', 'program_den-aktivni', $den->format('l d.n.'));
        ?>
        <?php if ($zobrazitMujProgramOdkaz)
            echo $odkaz('program/muj', 'program_den', 'program_den-aktivni', 'můj program');
        ?>
    </div>
</div>

<?php $program->tisk(); ?>

<div style="height: 70px"></div>

<script>
// TODO foreach?
posuvniky(document.getElementsByClassName('program_obaltabulky2')[0])

function posuvniky(obal2) {
    const posun = 220

    let lposuv = document.createElement('div')
    lposuv.innerHTML = '<div></div>'
    lposuv.className = 'program_posuv program_lposuv'
    lposuv.style.display = 'none'

    let rposuv = document.createElement('div')
    rposuv.innerHTML = '<div></div>'
    rposuv.className = 'program_posuv program_rposuv'
    rposuv.style.display = 'none'

    let obal = obal2.getElementsByClassName('program_obaltabulky')[0]

    lposuv.firstElementChild.onclick = () => obal.scrollBy({left: -posun, behavior: 'smooth'})
    rposuv.firstElementChild.onclick = () => obal.scrollBy({left:  posun, behavior: 'smooth'})
    obal.onscroll = () => checkScroll()

    obal2.append(lposuv)
    obal2.append(rposuv)

    checkScroll()
    // TODO přepočítat na resize?

    function checkScroll() {
        let left = obal.scrollLeft
        if (left <= 0) {
            ldisplay('none')
        } else {
            ldisplay('block')
        }

        let innerWidth = obal.scrollWidth
        let outerWidth = obal.clientWidth
        let right = innerWidth - (left + outerWidth)
        if (right <= 0) {
            rdisplay('none')
        } else {
            rdisplay('block')
        }
    }

    var soucasnyLdisplay = lposuv.style.display
    function ldisplay(val) {
        if (soucasnyLdisplay != val) {
            lposuv.style.display = val
            soucasnyLdisplay = val
        }
    }

    var soucasnyRdisplay = rposuv.style.display
    function rdisplay(val) {
        if (soucasnyRdisplay != val) {
            rposuv.style.display = val
            soucasnyRdisplay = val
        }
    }
}
</script>

<script>
(() => {
    // TODO nutno pořešit v adminu, kde asi bude víc program_obaltabulky :/
    scrollObnov()
    document.querySelectorAll('.program a').forEach(e => {
        e.addEventListener('click', () => scrollUloz())
    })

    function scrollObnov() {
        let top = window.localStorage.getItem('scrollUloz_top')
        window.localStorage.removeItem('scrollUloz_top')
        if (top) {
            window.scrollTo({top: top})
        }

        let left = window.localStorage.getItem('scrollUloz_left')
        window.localStorage.removeItem('scrollUloz_left')
        if (left) {
            document.getElementsByClassName('program_obaltabulky')[0].scrollLeft = left
        }
    }

    function scrollUloz() {
        let left = document.getElementsByClassName('program_obaltabulky')[0].scrollLeft
        window.localStorage.setItem('scrollUloz_top', window.scrollY)
        window.localStorage.setItem('scrollUloz_left', left)
    }
})()
</script>
