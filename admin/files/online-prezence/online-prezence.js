import {ZmenaMetadatAktivity} from "./online-prezence-eventy.js"
import {AkceAktivity} from "./online-prezence-akce-aktivity-class.js"
import {OnlinePrezenceOmnibox} from "./online-prezence-omnibox.js"

(function ($) {
  $(function () {
    const akceAktivity = new AkceAktivity()

    /*
    Ve Firefoxu je zvláštní chyba, kdy pokud se checkbox změní na checked pomocí JS, poté se stránka přenačte, backend stránku
    pošle bez checked (což obvykle znamená "nezaškrtnuto"), tak Firefox ponechá zaškrtnutí z předchozí akce JS.
    Toto je workaround.
     */
    $('input.dorazil[type=checkbox]').each(function (index, checkbox) {
      if (!checkbox.dataset.initialChecked) {
        checkbox.checked = false
      }
    })

    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (tooltipElement) {
      bootstrap.Tooltip.getOrCreateInstance(tooltipElement).update()
    })

    // ZMENA METADAT PREZENCE UCASTNIKA

    const $aktivity = $('.aktivita')

    $aktivity.each(function (index, aktivitaNode) {
      aktivitaNode.addEventListener('novyUcastnik', function (/** @param {{detail: {idAktivity: number, idUzivatele: number}}} event */event) {
        zaznamenejNovehoUcastnika(event.detail.idUzivatele, event.detail.idAktivity)
      })
      aktivitaNode.addEventListener(ZmenaMetadatAktivity.eventName, function (/** @param {ZmenaMetadatAktivity} */event) {
        zapisMetadataAktivity(aktivitaNode, event.detail)

        if (event.detail.stavAktivity === 'zamcena') {
          akceAktivity.reagujNaZamceniAktivity(
            aktivitaNode.dataset.id,
            event.detail.ucastniciPridatelniDoTimestamp,
            event.detail.ucastniciOdebratelniDoTimestamp,
          )
        } else if (event.detail.stavAktivity === 'uzavrena') {
          akceAktivity.reagujNaUzavreniAktivity(
            aktivitaNode.dataset.id,
            event.detail.ucastniciPridatelniDoTimestamp,
            event.detail.ucastniciOdebratelniDoTimestamp,
          )
        }
      })
    })

    /**
     * @param {HTMLElement} aktivitaNode
     * @param {{ casPosledniZmenyStavuAktivity: string, stavAktivity: string, idPoslednihoLogu: string, ucastniciPridatelniDoTimestamp: number, ucastniciOdebratelniDoTimestamp: number}} metadata
     */
    function zapisMetadataAktivity(aktivitaNode, metadata) {
      if (aktivitaNode.dataset.idPoslednihoLogu && Number(aktivitaNode.dataset.idPoslednihoLogu) >= metadata.idPoslednihoLogu) {
        return // změna je stejná nebo dokonce starší, než už známe
      }
      aktivitaNode.dataset.casPosledniZmenyStavuAktivity = metadata.casPosledniZmenyStavuAktivity
      aktivitaNode.dataset.stavAktivity = metadata.stavAktivity
      aktivitaNode.dataset.idPoslednihoLogu = metadata.idPoslednihoLogu.toString()
      aktivitaNode.dataset.ucastniciPridatelniDoTimestamp = metadata.ucastniciPridatelniDoTimestamp.toString()
      aktivitaNode.dataset.ucastniciOdebratelniDoTimestamp = metadata.ucastniciOdebratelniDoTimestamp.toString()
    }

    $('.ucastnik').each(function (index, ucastnikNode) {
      akceAktivity.hlidejZmenyMetadatUcastnika(ucastnikNode)
      aktivujTooltipUcastnika(ucastnikNode.dataset.id, ucastnikNode.dataset.idAktivity)
    })

    /**
     * @param {number|string} idUzivatele
     * @param {number|string} idAktivity
     */
    function zaznamenejNovehoUcastnika(idUzivatele, idAktivity) {
      const ucastnikNode = akceAktivity.dejNodeUcastnika(idUzivatele, idAktivity)
      akceAktivity.hlidejZmenyMetadatUcastnika(ucastnikNode)
      aktivujTooltipUcastnika(idUzivatele, idAktivity)
      upravUkazateleZaplnenostiAktivity(akceAktivity.dejNodeAktivity(idAktivity))
      obnovSeznamMailu(ucastnikNode)
    }

    /**
     * @param {HTMLElement} ucastnikNode
     */
    function obnovSeznamMailu(ucastnikNode) {
      const email = ucastnikNode.dataset.email
      if (email) {
        const idAktivity = ucastnikNode.dataset.idAktivity
        const emailyNode = document.getElementById(`emaily-${idAktivity}`)
        const aktivitaNode = akceAktivity.dejNodeAktivity(idAktivity)
        const vsechnyMaily = []
        aktivitaNode.querySelectorAll(`.ucastnik`).forEach(function (nejakyUcastnkNode) {
          if (nejakyUcastnkNode.dataset.email) {
            vsechnyMaily.push(nejakyUcastnkNode.dataset.email)
          }
        })
        const emailyAnchor = emailyNode.querySelector('a')
        emailyAnchor.href = 'mailto:?bcc=' + vsechnyMaily.join(',')
        emailyAnchor.innerText = vsechnyMaily.join(', ')
      }
    }

    /**
     * @param {number} idUzivatele
     * @param {number} idAktivity
     */
    function aktivujTooltipUcastnika(idUzivatele, idAktivity) {
      const tooltipTriggerList = Array.from(document.querySelectorAll(`#ucastnik-${idUzivatele}-na-aktivite-${idAktivity} [data-bs-toggle="tooltip"]`))
      tooltipTriggerList.map(function (tooltipTriggerElement) {
        bootstrap.Tooltip.getOrCreateInstance(tooltipTriggerElement).update()
      })
    }

    // OMNIBOX
    const omnibox = new OnlinePrezenceOmnibox(akceAktivity, $)
    omnibox.inicializujOmnibox()

    // ⏳ ČEKÁNÍ NA EDITACI ⏳

    $aktivity.each(function () {
      const aktivitaNode = this
      if (aktivitaNode.dataset.editovatelnaOdTimestamp > 0) {
        zablokovatAktivituProEditaciSOdpoctem(aktivitaNode)
      }
    })

    /**
     * @param {HTMLElement} aktivitaNode
     */
    function zablokovatAktivituProEditaciSOdpoctem(aktivitaNode) {
      akceAktivity.zablokovatPridavaniNaAktivitu(aktivitaNode.dataset.id, true)
      akceAktivity.zablokovatOdebiraniZAktivity(aktivitaNode.dataset.id, true)
      const $aktivitaNode = $(aktivitaNode)
      $aktivitaNode.find('.text-ceka').removeClass('display-none')
      spustitOdpocet(aktivitaNode)
    }

    /**
     * @param {HTMLElement} aktivitaNode
     */
    function spustitOdpocet(aktivitaNode) {
      const idAktivity = aktivitaNode.dataset.id
      const odpocetNode = document.getElementById(`odpocet-${idAktivity}`)
      const editovatelnaOdTimestamp = Number.parseInt(aktivitaNode.dataset.editovatelnaOdTimestamp)

      if (dokoncitOdpocetProEditaci(odpocetNode, idAktivity, editovatelnaOdTimestamp)) {
        return
      }

      const interval = 1000
      const intervalId = setInterval(function () {
        if (dokoncitOdpocetProEditaci(odpocetNode, idAktivity, editovatelnaOdTimestamp)) {
          clearInterval(intervalId)
        }
      }, interval)
    }

    $aktivity.each(function () {
      const aktivitaNode = this
      if (aktivitaNode.dataset.ucastniciOdebratelniDoTimestamp > 0) {
        hlidatDoKdyJeMoznePridavatUcastniky(aktivitaNode)
        hlidatDoKdyJeMozneOdebiratUcastniky(aktivitaNode)
      }
    })

    /**
     * @param {HTMLElement} aktivitaNode
     */
    function hlidatDoKdyJeMoznePridavatUcastniky(aktivitaNode) {
      const ucastniciPridatelniDoTimestamp = Number.parseInt(aktivitaNode.dataset.ucastniciPridatelniDoTimestamp)

      if (!ucastniciPridatelniDoTimestamp) {
        return
      }

      const interval = 1000
      const intervalId = setInterval(function () {
        if (akceAktivity.spoctiKolikZbyvaSekund(ucastniciPridatelniDoTimestamp) <= 0) {
          akceAktivity.zablokovatPridavaniNaAktivitu(aktivitaNode.dataset.id, false)
          clearInterval(intervalId)
        }
      }, interval)
    }

    /**
     * @param {HTMLElement} aktivitaNode
     */
    function hlidatDoKdyJeMozneOdebiratUcastniky(aktivitaNode) {
      const ucastniciOdebratelniDoTimestamp = Number.parseInt(aktivitaNode.dataset.ucastniciOdebratelniDoTimestamp)

      if (!ucastniciOdebratelniDoTimestamp) {
        return
      }

      const interval = 1000
      const intervalId = setInterval(function () {
        if (akceAktivity.spoctiKolikZbyvaSekund(ucastniciOdebratelniDoTimestamp) <= 0) {
          akceAktivity.zablokovatOdebiraniZAktivity(aktivitaNode.dataset.id, false)
          clearInterval(intervalId)
        }
      }, interval)
    }

    /**
     * @param {HTMLElement} odpocetNode
     * @param idAktivity
     * @param {number} editovatelnaOdTimestamp
     * @return {boolean}
     */
    function dokoncitOdpocetProEditaci(odpocetNode, idAktivity, editovatelnaOdTimestamp) {
      if (obnovitOdpocet(odpocetNode, editovatelnaOdTimestamp)) {
        return false // ještě nemůžeme odpočet dokončit, stále musí běžet
      }
      akceAktivity.odblokovatAktivituProEditaci(idAktivity)
      return true
    }

    /**
     * @param {HTMLElement} odpocetNode
     * @param {number} editovatelnaOdTimestamp
     * @return {boolean}
     */
    function obnovitOdpocet(odpocetNode, editovatelnaOdTimestamp) {
      const zbyvaSekund = akceAktivity.spoctiKolikZbyvaSekund(editovatelnaOdTimestamp)

      if (zbyvaSekund <= 0) {
        return false
      }

      odpocetNode.innerText = sekundyNaLidskyCas(zbyvaSekund)
      return true
    }

    /**
     * @param {number} sekundy
     * @return {string}
     */
    function sekundyNaLidskyCas(sekundy) {
      const sekundVeDni = 3600 * 24
      const zbyvaDni = Math.floor(sekundy / sekundVeDni)
      const sekundyBezDni = sekundy - (zbyvaDni * sekundVeDni)
      const zbyvaHodin = Math.floor(sekundyBezDni / 3600)
      const sekundyBezDniAHodin = sekundyBezDni - (zbyvaHodin * 3600)
      const zbyvaMinut = Math.floor(sekundyBezDniAHodin / 60)
      const zbyvaSekund = sekundyBezDniAHodin - (zbyvaMinut * 60)

      let lidskyCas = ''
      if (zbyvaDni) {
        lidskyCas += `${zbyvaDni} d`
      }
      if (zbyvaDni || zbyvaHodin) {
        lidskyCas += ` ${zbyvaHodin} h`
      }
      if (zbyvaDni || zbyvaHodin || zbyvaMinut) {
        lidskyCas += ` ${zbyvaMinut} m`
      }
      if (zbyvaDni || zbyvaHodin || zbyvaMinut || zbyvaSekund) {
        lidskyCas += ` ${zbyvaSekund} s`
      }

      return lidskyCas
    }

    // ✋ AKTIVITA UŽ SKONČILA, POZOR NA ÚPRAVY ✋
    $aktivity.each(function () {
      const aktivitaNode = this
      aktivitaNode.querySelectorAll('.text-skoncila').forEach(function (textSkoncilaNode) {
        if (textSkoncilaNode.classList.contains('display-none')) {
          return
        }
        const $textSkoncilaNode = $(textSkoncilaNode)
        hlidatUpozorneniNaSkoncenouAktivitu(aktivitaNode, $textSkoncilaNode)
      })
    })

    function hlidatUpozorneniNaSkoncenouAktivitu(aktivitaNode, $textSkoncilaNode) {
      const konecAktivityVTimestamp = Number.parseInt(aktivitaNode.dataset.konecAktivityVTimestamp)
      if (!konecAktivityVTimestamp) {
        return
      }

      if (zobrazVarovaniPokudAktivitaUzSkoncila($textSkoncilaNode, konecAktivityVTimestamp)) {
        return
      }

      const interval = 1000
      const intervalId = setInterval(function () {
        if (zobrazVarovaniPokudAktivitaUzSkoncila($textSkoncilaNode, konecAktivityVTimestamp)) {
          clearInterval(intervalId)
        }
      }, interval)
    }

    /**
     * @param {object} $textSkoncilaNode
     * @param {number} konecAktivityVTimestamp
     */
    function zobrazVarovaniPokudAktivitaUzSkoncila($textSkoncilaNode, konecAktivityVTimestamp) {
      const konecZaSekund = akceAktivity.spoctiKolikZbyvaSekund(konecAktivityVTimestamp)
      if (konecZaSekund > 0) {
        return false
      }
      $textSkoncilaNode.removeClass('display-none')
      return true
    }
  })
})(jQuery)

document.addEventListener('aktivitaVyrenderovana', function (event) {
  const aktivitaNode = event.detail
  if (aktivitaNode.dataset.editovatelnaOdTimestamp > 0) {
    zablokovatAktivituProEditaciSOdpoctem(aktivitaNode)
  }
})

const onlinePrezence = document.getElementById('online-prezence')

if (onlinePrezence) {

  const akceAktivity = new AkceAktivity()

  document.getElementById('online-prezence').addEventListener('uzavritAktivitu', function (event) {
    const idAktivity = event.detail
    akceAktivity.uzavritAktivitu(idAktivity)
  })

  document.getElementById('online-prezence').addEventListener('zmenitPritomnostUcastnika', function (event) {
    const {
      idUcastnika: idUcastnika,
      idAktivity: idAktivity,
      checkboxNode: checkboxNode,
      triggeringNode: triggeringNode,
    } = event.detail
    akceAktivity.zmenitPritomnostUcastnika(
      idUcastnika,
      idAktivity,
      checkboxNode,
      triggeringNode,
    )
  })
}
