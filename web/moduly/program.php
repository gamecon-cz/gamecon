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
$aktivni = function ($urlOdkazu) use ($url, $alternativniUrl) {
    $tridy = 'program_den';

    if ($urlOdkazu == $url->cela() || $urlOdkazu == $alternativniUrl) {
        $tridy .= ' program_den-aktivni';
    }

    return 'href="'.$urlOdkazu.'" class="'.$tridy.'"';
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
        z-index: 1; /* aby bylo nad symboly v programu */
        background-color: #FDC689;
        color: #10111A;
    }

    .program td {
        min-width: 100px;
        max-width: 100px;
        padding: 0;
    }

    .program td > div {
        background-color: #F6F1EA;
        height: 72px;
        overflow: hidden;
        padding: 15px;
        box-sizing: border-box;

        font-size: 12px;
    }

    .program td > div > a {
        text-decoration: none;
        font-weight: bold;
        color: inherit;
        font-size: 14px;
        display: block;
        min-height: 28px;
        max-height: 35px;
        margin-bottom: 2px;
        overflow: hidden;
        width: -moz-fit-content;
        width: fit-content;
    }

    .program td > div > a:hover {
        text-decoration: underline;
    }

    .program td > div > form > a {
        color: inherit;
        text-decoration: none;
        border-left: solid 1px #0002;
        padding-left: 9px;
        margin-left: 7px;
    }

    .program td > div > a + form > a {
        border: 0;
        padding: 0;
        margin: 0;
    }

    .program td > div > form > a:hover {
        text-decoration: underline;
    }

    .program_osobniTyp {
        border-left: solid 1px #0002;
        padding-left: 9px;
        margin-left: 9px;
    }

    .program_obsazenost {
        display: inline-block;
        background-color: inherit;
        padding-left: 16px;
    }

    .program_obsazenost::before {
        content: '';
        display: block;
        position: absolute;
        width: 9px;
        height: 14px;
        margin: -2px 0 0 -17px;
        background: url('soubory/blackarrow/program/clovek.svg');
    }

    .program_obsazenost .f {
        background-color: inherit;
    }

    .program_obsazenost .f::before {
        content: '';
        display: block;
        position: absolute;
        width: 9px;
        height: 16px;
        margin: -2px 0 0 -17px;
        background: url('soubory/blackarrow/program/zena.svg') 0 2px no-repeat;
        background-color: inherit;
    }

    .program_obsazenost .m {
        display: inline-block;
        padding-left: 32px;
    }

    .program_obsazenost .m::before {
        content: '';
        display: block;
        position: absolute;
        width: 13px;
        height: 13px;
        margin: 0 0 0 -19px;
        background: url('soubory/blackarrow/program/muz.svg');
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
        max-height: 100%;
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
    .program .nahradnik { background-color: #FEE2C4; }
    .program .organizator { background-color: #DCE3F1; }
</style>



<div class="program_hlavicka">
    <?php if ($u) { ?>
        <a href="program-k-tisku" class="program_tisk" target="_blank">Program v PDF</a>
    <?php } ?>
    <h1>Program <?=ROK?></h1>
    <div class="program_dny">
        <?php foreach ($dny as $denSlug => $den) { ?>
            <a <?=$aktivni('program/'.$denSlug)?>><?=$den->format('l d.n.')?></a>
        <?php } ?>
        <?php if ($zobrazitMujProgramOdkaz) { ?>
            <a <?=$aktivni('program/muj')?>>můj program</a>
        <?php } ?>
    </div>
</div>



<!-- TODO relative obal nutný kvůli sidebaru -->
<div style="position: relative">
    <?php require __DIR__ . '/../soubory/blackarrow/program-nahled/program-nahled.html'; ?>

    <div class="programNahled_obalProgramu">
        <?php $program->tisk(); ?>
    </div>
</div>

<div style="height: 70px"></div>



<script>
// TODO teď se načítá na tvrdo v hlavičce, nejspíš přes proměnnou / param
// nastavit do modulu a vyzvednout v indexu a pak do hlavičky ne/dat dle toho
// (viz také co bude s dalšími assety programu)
programNahled(
    document.querySelector('.programNahled_obalNahledu'),
    document.querySelector('.programNahled_obalProgramu'),
    document.querySelectorAll('.programNahled_odkaz'),
    document.querySelectorAll('.program form > a')
)
</script>

<script>
(() => {
    // TODO nutno pořešit v adminu, kde asi bude víc program_obaltabulky :/
    // první po porgramu, aby se zamezilo skákání
    scrollObnov()
    document.querySelectorAll('.program form > a').forEach(e => {
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
    new ResizeObserver(checkScroll).observe(obal)

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
