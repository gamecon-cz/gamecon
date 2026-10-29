/*
 * Moderní vzhled veřejného webu (web/sablony/moderni/): vysouvací menu, rozbalovací skupiny menu,
 * odpočet, odložené video, odhalení při posunu a na stránce linie detail hry a hledání s filtrem štítků.
 * Bez JS zůstává web použitelný: odkazy fungují, jen se nerozbalují skupiny menu a nefiltruje se.
 */
(function () {
  'use strict'

  var html = document.documentElement
  var omezenyPohyb = window.matchMedia('(prefers-reduced-motion: reduce)').matches

  // ---------- Menu ----------

  function zavriSkupiny(krome) {
    document.querySelectorAll('.mw-nav-rozbalit[aria-expanded="true"]').forEach(function (tlacitko) {
      if (tlacitko !== krome) {
        tlacitko.setAttribute('aria-expanded', 'false')
      }
    })
  }

  function nastavVysouvaciMenu(otevrit) {
    var burger = document.querySelector('.mw-burger')
    var zavoj = document.querySelector('.mw-zavoj')
    if (!burger) {
      return
    }
    html.classList.toggle('mw-nav-otevrena', otevrit)
    burger.setAttribute('aria-expanded', otevrit ? 'true' : 'false')
    if (zavoj) {
      zavoj.hidden = !otevrit
    }
  }

  document.addEventListener('click', function (event) {
    var rozbalit = event.target.closest('.mw-nav-rozbalit')
    if (rozbalit) {
      var otevrit = rozbalit.getAttribute('aria-expanded') !== 'true'
      zavriSkupiny(rozbalit)
      rozbalit.setAttribute('aria-expanded', otevrit ? 'true' : 'false')
      return
    }
    if (event.target.closest('.mw-burger')) {
      nastavVysouvaciMenu(!html.classList.contains('mw-nav-otevrena'))
      return
    }
    if (event.target.closest('.mw-zavoj')) {
      nastavVysouvaciMenu(false)
      return
    }
    if (!event.target.closest('.mw-nav-panel')) {
      zavriSkupiny(null)
    }
  })

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      zavriSkupiny(null)
      nastavVysouvaciMenu(false)
    }
  })

  // stín pod hlavičkou, když je stránka posunutá
  function oznacPosun() {
    html.classList.toggle('mw-posunuto', window.scrollY > 4)
  }
  window.addEventListener('scroll', oznacPosun, {passive: true})
  oznacPosun()

  // ---------- Odpočet ----------

  document.querySelectorAll('[data-mw-odpocet]').forEach(function (odpocet) {
    var cil = Number(odpocet.getAttribute('data-mw-odpocet'))
    var prvky = {
      dnu: odpocet.querySelector('[data-mw-odpocet-dnu]'),
      hodin: odpocet.querySelector('[data-mw-odpocet-hodin]'),
      minut: odpocet.querySelector('[data-mw-odpocet-minut]'),
      sekund: odpocet.querySelector('[data-mw-odpocet-sekund]'),
    }
    function dvemi(cislo) {
      return String(cislo).padStart(2, '0')
    }
    function obnov() {
      var zbyva = Math.max(0, cil - Math.floor(Date.now() / 1000))
      prvky.sekund.textContent = dvemi(zbyva % 60)
      prvky.minut.textContent = dvemi(Math.floor(zbyva / 60) % 60)
      prvky.hodin.textContent = dvemi(Math.floor(zbyva / 3600) % 24)
      prvky.dnu.textContent = String(Math.floor(zbyva / 86400))
    }
    obnov()
    setInterval(obnov, 1000)
  })

  // ---------- Tah štětcem pod zvýrazněnou frází ----------

  // Fráze na jednom řádku dostane jeden tah místo tahu pod každým slovem (moderni.css, .mw-zvyraznit). Zalomení záleží
  // na šířce i na písmu (motiv ho může změnit), proto se měří, a znovu, když se nadpis změní nebo se načte písmo.
  // Řádky počítá getClientRects() (obdélník za každý řádek), ten nezmate ani natočení nadpisu.
  document.querySelectorAll('.mw-zvyraznit').forEach(function (zvyrazneni) {
    function zmer() {
      zvyrazneni.classList.toggle('mw-zvyraznit-na-radku', zvyrazneni.getClientRects().length === 1)
    }
    zmer()
    if ('ResizeObserver' in window) {
      new ResizeObserver(zmer).observe(zvyrazneni.parentElement)
    }
    document.fonts.ready.then(zmer)
  })

  // ---------- Video až po načtení stránky ----------

  window.addEventListener('load', function () {
    document.querySelectorAll('iframe[data-mw-src]').forEach(function (iframe) {
      var src = iframe.getAttribute('data-mw-src')
      iframe.src = omezenyPohyb ? src.replace('autoplay=1', 'autoplay=0') : src
    })
  })

  // ---------- Odhalení při posunu ----------

  var odhalovane = document.querySelectorAll('.mw-odhalit')
  if (omezenyPohyb || !('IntersectionObserver' in window)) {
    odhalovane.forEach(function (prvek) {
      prvek.classList.remove('mw-odhalit')
    })
  } else {
    var pozorovatel = new IntersectionObserver(function (zaznamy) {
      zaznamy.forEach(function (zaznam) {
        if (!zaznam.isIntersecting) {
          return
        }
        var prvek = zaznam.target
        pozorovatel.unobserve(prvek)
        prvek.classList.add('mw-odhaleno')
        // po odhalení vrátit prvku jeho vlastní přechody (třeba zvednutí karty při najetí myší)
        prvek.addEventListener('transitionend', function () {
          prvek.classList.remove('mw-odhalit', 'mw-odhaleno')
        }, {once: true})
      })
    }, {rootMargin: '0px 0px -8% 0px'})
    odhalovane.forEach(function (prvek) {
      pozorovatel.observe(prvek)
    })
  }

  // ---------- Linie: detail hry ----------

  document.addEventListener('click', function (event) {
    var otevrit = event.target.closest('[data-mw-detail]')
    if (otevrit) {
      var dialog = document.getElementById(otevrit.getAttribute('data-mw-detail'))
      if (dialog && typeof dialog.showModal === 'function') {
        dialog.showModal()
        dialog.scrollTop = 0
      }
      return
    }
    var zavrit = event.target.closest('.mw-detail-zavrit')
    if (zavrit) {
      zavrit.closest('dialog').close()
      return
    }
    // klik na ztmavené pozadí mimo okno detailu
    if (event.target.matches('dialog.mw-detail')) {
      var obdelnik = event.target.getBoundingClientRect()
      var mimo = event.clientX < obdelnik.left || event.clientX > obdelnik.right
                 || event.clientY < obdelnik.top || event.clientY > obdelnik.bottom
      if (mimo) {
        event.target.close()
      }
    }
  })

  // ---------- Linie: hledání a filtr štítků ----------

  var filtr = document.querySelector('[data-mw-filtr]')
  var mrizka = document.querySelector('[data-mw-aktivity]')
  if (!filtr || !mrizka) {
    return
  }
  var karty = Array.prototype.slice.call(mrizka.querySelectorAll('.mw-aktivita'))
  if (karty.length < 2) {
    return
  }

  function bezDiakritiky(text) {
    return text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()
  }

  var pocetPodleStitku = new Map()
  var hry = karty.map(function (karta) {
    var stitky = Array.prototype.map.call(karta.querySelectorAll('.mw-stitky li'), function (li) {
      return li.textContent.trim()
    })
    stitky.forEach(function (stitek) {
      pocetPodleStitku.set(stitek, (pocetPodleStitku.get(stitek) || 0) + 1)
    })
    var text = ['.mw-aktivita-nazev', '.mw-aktivita-vypravec', '.mw-aktivita-popis'].map(function (selektor) {
      var prvek = karta.querySelector(selektor)
      return prvek ? prvek.textContent : ''
    }).concat(stitky).join(' ')
    return {karta: karta, stitky: stitky, text: bezDiakritiky(text)}
  })

  var hledat = filtr.querySelector('[data-mw-filtr-hledat]')
  var stitkyObal = filtr.querySelector('[data-mw-filtr-stitky]')
  var pocet = filtr.querySelector('[data-mw-filtr-pocet]')
  var prazdne = document.querySelector('[data-mw-filtr-prazdne]')
  var vybraneStitky = new Set()

  // štítky od nejčastějších, štítek jen u jedné hry nic nefiltruje
  Array.from(pocetPodleStitku.entries())
    .filter(function (polozka) {
      return polozka[1] > 1 && polozka[1] < hry.length
    })
    .sort(function (a, b) {
      return b[1] - a[1] || a[0].localeCompare(b[0], 'cs')
    })
    .forEach(function (polozka) {
      var tlacitko = document.createElement('button')
      tlacitko.type = 'button'
      tlacitko.setAttribute('aria-pressed', 'false')
      tlacitko.dataset.stitek = polozka[0]
      tlacitko.textContent = polozka[0]
      var pocetHer = document.createElement('small')
      pocetHer.textContent = polozka[1]
      tlacitko.appendChild(pocetHer)
      stitkyObal.appendChild(tlacitko)
    })

  function prefiltruj() {
    var dotaz = bezDiakritiky(hledat.value.trim())
    var zobrazeno = 0
    hry.forEach(function (hra) {
      var odpovida = (dotaz === '' || hra.text.indexOf(dotaz) !== -1)
                     && Array.from(vybraneStitky).every(function (stitek) {
                       return hra.stitky.indexOf(stitek) !== -1
                     })
      hra.karta.hidden = !odpovida
      if (odpovida) {
        zobrazeno++
      }
    })
    pocet.textContent = zobrazeno === hry.length
      ? hry.length + ' ' + (hry.length < 5 ? 'hry' : 'her')
      : zobrazeno + ' z ' + hry.length + ' her'
    prazdne.hidden = zobrazeno > 0
  }

  hledat.addEventListener('input', prefiltruj)
  stitkyObal.addEventListener('click', function (event) {
    var tlacitko = event.target.closest('button')
    if (!tlacitko) {
      return
    }
    var vybrat = tlacitko.getAttribute('aria-pressed') !== 'true'
    tlacitko.setAttribute('aria-pressed', vybrat ? 'true' : 'false')
    if (vybrat) {
      vybraneStitky.add(tlacitko.dataset.stitek)
    } else {
      vybraneStitky.delete(tlacitko.dataset.stitek)
    }
    prefiltruj()
  })

  filtr.hidden = false
  prefiltruj()
})()
