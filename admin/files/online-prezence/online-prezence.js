import {ZmenaMetadatAktivity} from "./online-prezence-eventy.js"
import {AkceAktivity} from "./online-prezence-tridy-akce-aktivity.js"

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
          reagujNaZamceniAktivity(
            aktivitaNode.dataset.id,
            event.detail.ucastniciPridatelniDoTimestamp,
            event.detail.ucastniciOdebratelniDoTimestamp,
          )
        } else if (event.detail.stavAktivity === 'uzavrena') {
          reagujNaUzavreniAktivity(
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
      hlidejZmenyMetadatUcastnika(ucastnikNode)
      aktivujTooltipUcastnika(ucastnikNode.dataset.id, ucastnikNode.dataset.idAktivity)
    })

    /**
     * @param {number|string} idUzivatele
     * @param {number|string} idAktivity
     */
    function zaznamenejNovehoUcastnika(idUzivatele, idAktivity) {
      const ucastnikNode = akceAktivity.dejNodeUcastnika(idUzivatele, idAktivity)
      hlidejZmenyMetadatUcastnika(ucastnikNode)
      aktivujTooltipUcastnika(idUzivatele, idAktivity)
      akceAktivity.upravUkazateleZaplnenostiAktivity(akceAktivity.dejNodeAktivity(idAktivity))
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
     * @param {HTMLElement} ucastnikNode
     * @param {string} stavPrihlaseni
     */
    function zobrazTypUcastnika(ucastnikNode, stavPrihlaseni) {
      const idUzivatele = ucastnikNode.dataset.id
      const idAktivity = ucastnikNode.dataset.idAktivity
      const naPosledniChvili = ucastnikNode.querySelector('.na-posledni-chvili')
      const jeNahradnik = document.getElementById(`ucastnik-${idUzivatele}-je-nahradnik-na-aktivite-${idAktivity}`)
      const jeSledujici = document.getElementById(`ucastnik-${idUzivatele}-je-sledujici-aktivity-${idAktivity}`)
      const jeSpici = document.getElementById(`ucastnik-${idUzivatele}-je-spici-na-aktivite-${idAktivity}`)
      switch (stavPrihlaseni) {
        case 'sledujici_se_prihlasil' :
          skryt(jeNahradnik)
          zobrazit(jeSledujici)
          skryt(jeSpici)
          break
        case 'nahradnik_nedorazil' :
          skryt(jeNahradnik)
          skryt(jeSledujici)
          zobrazit(jeSpici)
          break
        case 'nahradnik_dorazil' :
          skryt(jeSledujici)
          skryt(jeSpici)
          zobrazit(jeNahradnik)
          break
        case 'ucastnik_dorazil' :
          skryt(jeSledujici)
          skryt(jeNahradnik)
          skryt(jeSpici)
          if (naPosledniChvili) {
            skryt(naPosledniChvili)
          }
          break
        case 'ucastnik_se_prihlasil' :
          skryt(jeSledujici)
          skryt(jeNahradnik)
          skryt(jeSpici)
          if (naPosledniChvili) {
            zobrazit(naPosledniChvili)
          }
          break
        default :
          skryt(jeNahradnik)
          skryt(jeSledujici)
          skryt(jeSpici)
      }
    }

    /**
     * @param {HTMLElement} node
     */
    function skryt(node) {
      node.classList.add('display-none')
    }

    /**
     * @param {HTMLElement} node
     */
    function zobrazit(node) {
      node.classList.remove('display-none')
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

    /**
     * @param {HTMLElement} ucastnikNode
     */
    function hlidejZmenyMetadatUcastnika(ucastnikNode) {
      ucastnikNode.addEventListener('zmenaMetadatUcastnika', function (/** @param {{detail: {casPosledniZmenyPrihlaseni: string, stavPrihlaseni: string, idPoslednihoLogu: number}}} event */event) {
        zapisMetadataUcastnika(ucastnikNode, event.detail)
        zobrazTypUcastnika(ucastnikNode, event.detail.stavPrihlaseni)
        akceAktivity.upravUkazateleZaplnenostiAktivity(akceAktivity.dejNodeAktivity(ucastnikNode.dataset.idAktivity))
      })
    }

    /**
     * @param {HTMLElement} ucastnikNode
     * @param {{casPosledniZmenyPrihlaseni: string, stavPrihlaseni: string, idPoslednihoLogu: number, callback: function|undefined}} metadata
     */
    function zapisMetadataUcastnika(ucastnikNode, metadata) {
      if (ucastnikNode.dataset.idPoslednihoLogu && Number(ucastnikNode.dataset.idPoslednihoLogu) >= metadata.idPoslednihoLogu) {
        return // změna je stejná nebo dokonce starší, než už známe
      }
      ucastnikNode.dataset.casPosledniZmenyPrihlaseni = metadata.casPosledniZmenyPrihlaseni
      ucastnikNode.dataset.stavPrihlaseni = metadata.stavPrihlaseni
      ucastnikNode.dataset.idPoslednihoLogu = metadata.idPoslednihoLogu.toString()

      if (typeof metadata.callback === 'function') {
        metadata.callback()
      }
    }

    /**
     * Bude zpracováno v event listeneru přes zaznamenejNovehoUcastnika()
     * @param {number} idUzivatele
     * @param {number} idAktivity
     */
    function vypustEventONovemUcastnikovi(idUzivatele, idAktivity) {
      const novyUcastnik = new CustomEvent('novyUcastnik', {
        detail: {
          idAktivity: idAktivity, idUzivatele: idUzivatele,
        },
      })
      akceAktivity.dejNodeAktivity(idAktivity).dispatchEvent(novyUcastnik)
    }


    // OMNIBOX
    intializePrezenceOmnibox()

    function intializePrezenceOmnibox() {
      const omnibox = $('.online-prezence .omnibox')
      omnibox.on('autocompleteselect', function (event, ui) {
        const idAktivity = Number(this.dataset.idAktivity)
        const idUzivatele = Number(ui.item.value)
        const ucastniciAktivityNode = $(`#ucastniciAktivity${idAktivity}`)
        const novyUcastnik = $(ui.item.html)

        akceAktivity.zmenitPritomnostUcastnika(
          idUzivatele,
          idAktivity,
          novyUcastnik.find('input')[0],
          this /* kde vznikl požadavek a kde ukázat případné errory */,
          function () {
            /**
             * Teprve až backend potvrdí uložení vybraného účastníka, tak můžeme přidat řádek s tímto účastníkem.
             */
            ucastniciAktivityNode.append(novyUcastnik)
            hlidejZmenyMetadatUcastnika(akceAktivity.dejNodeUcastnika(idUzivatele, idAktivity))
          },
          function () {
            /**
             * Přidáme řádek účastníka a JS přidá čas poslední změny a stav přihlášení, tak můžeme vypustit event o upečené novince.
             * Data z řádku totiž potřebujeme pro kontrolu změn v online-prezence-posledni-zname-zmeny-prihlaseni.js
             */
            vypustEventONovemUcastnikovi(idUzivatele, idAktivity)
          },
        )

        // vyrušení default výběru do boxu
        event.preventDefault()
        $(this).val('')

        // skrytí výchozí ošklivé hlášky
        $('.ui-helper-hidden-accessible').addClass('display-none')
      })

      omnibox.on('autocompleteresponse', function (event, ui) {
        const idAktivity = this.dataset.idAktivity
        $(`#omniboxHledam${idAktivity}`).addClass('display-none')
        if (!ui || ui.content === undefined || ui.content.length === 0) {
          $(`#omniboxNicNenalezeno${idAktivity}`).removeClass('display-none')
        } else {
          $(`#omniboxNicNenalezeno${idAktivity}`).addClass('display-none')
        }
      })

      omnibox.on('input', function () {
        const idAktivity = this.dataset.idAktivity
        $(`#omniboxNicNenalezeno${idAktivity}`).addClass('display-none')
        const minLength = this.dataset.omniboxMinLength
        const length = this.value.length
        if (minLength <= length) {
          $(`#omniboxHledam${idAktivity}`).removeClass('display-none')
        }
      })

      $('.formAktivita').submit(function () {
        const $aktivita = $(this).closest('.blokAktivita')
        // test na vyplnění políček / potvrzení
        const policek = $aktivita.find('[type=checkbox]').length
        const vybrano = $aktivita.find('[type=checkbox]:checked').length
        if (vybrano < policek / 2) {
          if (!confirm('Opravdu uložit s účastí menší jak polovina?')) {
            return false
          }
        }
        // odeslání
        $aktivita.find('[type=submit]').attr('disabled', true)
        $aktivita.load(document.URL + ' .blokAktivita[data-id=' + $aktivita.data('id') + '] > *', $(this).serializeObject(), function () {
          initializeOmnibox($)
          intializePrezenceOmnibox()
        })
        return false
      })
    }

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
        hlidatUcastnikyPridavatelneDo(aktivitaNode)
        hlidatUcastnikyOdebratelneDo(aktivitaNode)
      }
    })

    /**
     * @param {HTMLElement} aktivitaNode
     */
    function hlidatUcastnikyPridavatelneDo(aktivitaNode) {
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
    function hlidatUcastnikyOdebratelneDo(aktivitaNode) {
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
      odblokovatAktivituProEditaci(idAktivity)
      return true
    }

    function odblokovatAktivituProEditaci(idAktivity) {
      const $aktivitaNode = $(`#aktivita-${idAktivity}`)
      $aktivitaNode.find('input').prop('disabled', false)
      $aktivitaNode.find('.text-ceka').addClass('display-none')
      $aktivitaNode.find(`.zobrazit-pokud-aktivitu-nelze-editovat`).addClass('display-none')
      $aktivitaNode.find('.tlacitko-uzavrit-aktivitu').removeClass('display-none')
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


const akceAktivity = new AkceAktivity()

/**
 * @param {string} idAktivity
 * @param {number} ucastniciPridatelniDoTimestamp
 * @param {number} ucastniciOdebratelniDoTimestamp
 */
function reagujNaZamceniAktivity(idAktivity, ucastniciPridatelniDoTimestamp, ucastniciOdebratelniDoTimestamp) {
  akceAktivity.reagujNaZamceniAktivity(idAktivity, ucastniciPridatelniDoTimestamp, ucastniciOdebratelniDoTimestamp)
}

/**
 * @param {string} idAktivity
 * @param {number} ucastniciPridatelniDoTimestamp
 * @param {number} ucastniciOdebratelniDoTimestamp
 */
function reagujNaUzavreniAktivity(idAktivity, ucastniciPridatelniDoTimestamp, ucastniciOdebratelniDoTimestamp) {
  akceAktivity.reagujNaUzavreniAktivity(idAktivity, ucastniciPridatelniDoTimestamp, ucastniciOdebratelniDoTimestamp)
}

document.addEventListener('aktivitaVyrenderovana', function (event) {
  const aktivitaNode = event.detail
  akceAktivity.upravUkazateleZaplnenostiAktivity(aktivitaNode)
})

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
