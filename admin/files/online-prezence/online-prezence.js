(function ($) {
  $(function () {

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
      aktivitaNode.addEventListener('zmenaMetadatAktivity', function (/** @param {{ detail: { casPosledniZmenyStavuAktivity: string, stavAktivity: string, idPoslednihoLogu: number, editovatelnaDoTimestamp: number} }} */event) {
        zapisMetadataAktivity(aktivitaNode, event.detail)

        if (event.detail.stavAktivity === 'zamcena') {
          reagujNaZamceniAktivity(aktivitaNode.dataset.id, event.detail.editovatelnaDoTimestamp)
        } else if (event.detail.stavAktivity === 'uzavrena') {
          reagujNaUzavreniAktivity(aktivitaNode.dataset.id, event.detail.editovatelnaDoTimestamp)
        }
      })
    })

    /**
     * @param {HTMLElement} aktivitaNode
     * @param {{ casPosledniZmenyStavuAktivity: string, stavAktivity: string, idPoslednihoLogu: string, editovatelnaDoTimestamp: number}} metadata
     */
    function zapisMetadataAktivity(aktivitaNode, metadata) {
      if (aktivitaNode.dataset.idPoslednihoLogu && Number(aktivitaNode.dataset.idPoslednihoLogu) >= metadata.idPoslednihoLogu) {
        return // změna je stejná nebo dokonce starší, než už známe
      }
      aktivitaNode.dataset.casPosledniZmenyStavuAktivity = metadata.casPosledniZmenyStavuAktivity
      aktivitaNode.dataset.stavAktivity = metadata.stavAktivity
      aktivitaNode.dataset.idPoslednihoLogu = metadata.idPoslednihoLogu.toString()
      aktivitaNode.dataset.editovatelnaDoTimestamp = metadata.editovatelnaDoTimestamp.toString()
    }

    /**
     * viz \Gamecon\Aktivita\ZmenaStavuAktivity::stavAktivityProJs
     * @param {string} stavAktivity
     * @return {boolean}
     */
    function editovatelnaPodleStavu(stavAktivity) {
      return ['aktivovana', 'systemova'].includes(stavAktivity)
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
      const nodeUcastnika = dejNodeUcastnika(idUzivatele, idAktivity)
      hlidejZmenyMetadatUcastnika(nodeUcastnika)
      aktivujTooltipUcastnika(idUzivatele, idAktivity)
      upravUkazateleZaplnenostiAktivity(dejNodeAktivity(idAktivity))
      obnovSeznamMailu(nodeUcastnika)
    }

    /**
     * @param {HTMLElement} nodeUcastnika
     */
    function obnovSeznamMailu(nodeUcastnika) {
      const email = nodeUcastnika.dataset.email
      if (email) {
        const idAktivity = nodeUcastnika.dataset.idAktivity
        const emailyNode = document.getElementById(`emaily-${idAktivity}`)
        const aktivitaNode = dejNodeAktivity(idAktivity)
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
      const jePryc = document.getElementById(`ucastnik-${idUzivatele}-je-spici-na-aktivite-${idAktivity}`)
      switch (stavPrihlaseni) {
        case 'sledujici_se_prihlasil' :
        /*
        Když je náhradník přidán z online prezence, tak při opětovném odkškrtnutí je vlastně smazán, tedy není z něj náhradník.
        Ale prezence ho neodstraní, kdyby to snad byl překlik aby šel zas hned vrátit, proto ho ponecháme jako "spícího".
         */
        case 'nahradnik_nedorazil' :
          skryt(jeNahradnik)
          skryt(jeSledujici)
          zobrazit(jePryc)
          break
        case 'nahradnik_dorazil' :
          skryt(jeSledujici)
          skryt(jePryc)
          zobrazit(jeNahradnik)
          break
        case 'ucastnik_dorazil' :
          skryt(jeSledujici)
          skryt(jeNahradnik)
          skryt(jePryc)
          if (naPosledniChvili) {
            skryt(naPosledniChvili)
          }
          break
        case 'ucastnik_se_prihlasil' :
          skryt(jeSledujici)
          skryt(jeNahradnik)
          skryt(jePryc)
          if (naPosledniChvili) {
            zobrazit(naPosledniChvili)
          }
          break
        default :
          skryt(jeNahradnik)
          skryt(jeSledujici)
          skryt(jePryc)
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
        upravUkazateleZaplnenostiAktivity(dejNodeAktivity(ucastnikNode.dataset.idAktivity))
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
      dejNodeAktivity(idAktivity).dispatchEvent(novyUcastnik)
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

        zmenitPritomnostUcastnika(idUzivatele, idAktivity, novyUcastnik.find('input')[0], this /*kde vznikl požadavek a kde ukázat případné errory*/, function () {
          /**
           * Teprve až backend potvrdí uložení vybraného účastníka a JS přidá čas poslední změny a stav přihlášení,
           * tak můžeme přidat řádek s tímto účastníkem.
           * Data z řádku totiž potřebujeme pro kontrolu změn v online-prezence-posledni-zname-zmeny-prihlaseni.js
           */
          ucastniciAktivityNode.append(novyUcastnik)
          vypustEventONovemUcastnikovi(idUzivatele, idAktivity)
        })

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
          $(`#omniboxNicNenalezeno${idAktivity}`).removeClass('display-none').show()
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
          $(`#omniboxHledam${idAktivity}`).removeClass('display-none').show()
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
      zablokovatEditaciAktivity(aktivitaNode.dataset.id)
      const $aktivitaNode = $(aktivitaNode)
      $aktivitaNode.find('.text-ceka').removeClass('display-none').show()
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
      if (aktivitaNode.dataset.editovatelnaDoTimestamp > 0) {
        hlidatEditovatelnouDo(aktivitaNode)
      }
    })

    /**
     * @param {HTMLElement} aktivitaNode
     */
    function hlidatEditovatelnouDo(aktivitaNode) {
      const editovatelnaDoTimestamp = Number.parseInt(aktivitaNode.dataset.editovatelnaDoTimestamp)

      if (!editovatelnaDoTimestamp) {
        return
      }

      const interval = 1000
      const intervalId = setInterval(function () {
        if (spoctiKolikZbyvaSekund(editovatelnaDoTimestamp) <= 0) {
          zablokovatEditaciAktivity(aktivitaNode.dataset.id, false)
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
      $aktivitaNode.find(`#uz-needitovatelna-${idAktivity}`).addClass('display-none')
      $aktivitaNode.find('.tlacitko-uzavrit-aktivitu').removeClass('display-none').show()
    }

    /**
     * @param {HTMLElement} odpocetNode
     * @param {number} editovatelnaOdTimestamp
     * @return {boolean}
     */
    function obnovitOdpocet(odpocetNode, editovatelnaOdTimestamp) {
      const zbyvaSekund = spoctiKolikZbyvaSekund(editovatelnaOdTimestamp)

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
        lidskyCas += `${zbyvaHodin} h`
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
      const konecZaSekund = spoctiKolikZbyvaSekund(konecAktivityVTimestamp)
      if (konecZaSekund > 0) {
        return false
      }
      $textSkoncilaNode.removeClass('display-none').show()
      return true
    }
  })
})(jQuery)

const akceAktivity = new class AkceAktivity {

  /**
   * @public
   * @param {number} idAktivity
   */
  uzavritAktivitu(idAktivity) {
    vypustEventOProbihajicichZmenach(true)

    const that = this
    $.post(location.href, {
      /** viz \Gamecon\Aktivita\OnlinePrezence\OnlinePrezenceAjax::ajaxUzavritAktivitu */
      akce: 'uzavrit', id: idAktivity, ajax: true,
    }).done(function (/** @param {{editovatelna_do_timestamp: number}} data */data) {
      that.reagujNaUzavreniAktivity(idAktivity, data.editovatelna_do_timestamp)
    }).always(function () {
      vypustEventOProbihajicichZmenach(false)
    })
  }

  /**
   * @param {number} idAktivity
   * @param {number} editovatelnaDoTimestamp
   */
  reagujNaZamceniAktivity(idAktivity, editovatelnaDoTimestamp) {
    const skrytElement = document.getElementById(`otevrena-${idAktivity}`)
    const zobrazitElement = document.getElementById(`zamcena-${idAktivity}`)
    this.prohoditZobrazeni([skrytElement], zobrazitElement)

    this.zpracovatEditovatelnostDo(idAktivity, editovatelnaDoTimestamp)
  }

  /**
   * @param {number} idAktivity
   * @param {number} editovatelnaDoTimestamp
   */
  zpracovatEditovatelnostDo(idAktivity, editovatelnaDoTimestamp) {
    const editovatelnaSekund = spoctiKolikZbyvaSekund(editovatelnaDoTimestamp)
    if (editovatelnaSekund > 0) {
      this.zobrazitVarovaniZeAktivitaJeZamcena(idAktivity)
      const that = this
      setTimeout(function () {
        that.zablokovatEditaciAktivity(idAktivity)
      }, editovatelnaSekund * 1000)
    } else {
      this.zablokovatEditaciAktivity(idAktivity)
    }
  }

  /**
   * @param {number} idAktivity
   * @param {number} editovatelnaDoTimestamp
   */
  reagujNaUzavreniAktivity(idAktivity, editovatelnaDoTimestamp) {
    const skrytElementy = [document.getElementById(`otevrena-${idAktivity}`), document.getElementById(`zamcena-${idAktivity}`)]
    const zobrazitElement = document.getElementById(`uzavrena-${idAktivity}`)
    this.prohoditZobrazeni(skrytElementy, zobrazitElement)

    this.zpracovatEditovatelnostDo(idAktivity, editovatelnaDoTimestamp)
  }

  /**
   * @param {number|string} idAktivity
   * @param {boolean} vcetneTlacitkaNaUzavreni
   */
  zablokovatEditaciAktivity(idAktivity, vcetneTlacitkaNaUzavreni = true) {
    this.zablokovatInputyAktivity(idAktivity)
    const $aktivitaNode = $(`#aktivita-${idAktivity}`)
    $aktivitaNode.find('.skryt-pokud-aktivitu-nelze-editovat').addClass('display-none')
    if (vcetneTlacitkaNaUzavreni) {
      $aktivitaNode.find('.tlacitko-uzavrit-aktivitu').addClass('display-none')
    } else {
      $aktivitaNode.find(`#uz-needitovatelna-${idAktivity}`).removeClass('display-none').show()
    }
  }

  /**
   * @private
   * @param idAktivity
   */
  zablokovatInputyAktivity(idAktivity) {
    const $aktivitaNode = $(`#aktivita-${idAktivity}`)
    $aktivitaNode.find('input').prop('disabled', true)
  }

  /**
   * @private
   * @param idAktivity
   */
  zobrazitVarovaniZeAktivitaJeZamcena(idAktivity) {
    $(`#pozor-zamcena-${idAktivity}`).removeClass('display-none').show()
  }

  /**
   * @param {HTMLElement[]} skrytElementy
   * @param {HTMLElement} zobrazitElement
   */
  prohoditZobrazeni(skrytElementy, zobrazitElement) {
    skrytElementy.forEach(function (skrytElement) {
      skrytElement.style.display = 'none'
    })
    zobrazitElement.style.display = 'initial'
  }

  /**
   * @param {HTMLElement} aktivitaNode
   */
  upravUkazateleZaplnenostiAktivity(aktivitaNode) {
    const kapacita = Number.parseInt(aktivitaNode.dataset.kapacita)
    const zaskrtnuteCheckboxy = aktivitaNode.querySelectorAll('.styl-pro-dorazil-checkbox > input[type=checkbox]:checked')
    const pocetPritomnych = zaskrtnuteCheckboxy.length
    const barvaZaplnenosti = tempToColor(pocetPritomnych, 1, kapacita + 1 /* poslední barva je fialová, my chceme po plnou aktivitu předposlední, červenou */, 'half')
    const {r, g, b} = barvaZaplnenosti
    const intenzitaBarvy = 0.1
    // zaškrtnuté checkboxy dostanou barvu od zelené po fialovou, jak se bued blížit vyčerpání kapacity
    Array.from(zaskrtnuteCheckboxy).forEach(function (checkbox) {
      const stylNode = checkbox.parentElement
      stylNode.style.backgroundColor = `rgb(${r},${g},${b},${intenzitaBarvy})`
    })
    // nezaškrtnutým checkboxům zresetujeme barvy
    aktivitaNode.querySelectorAll('.styl-pro-dorazil-checkbox > input[type=checkbox]:not(:checked)').forEach(function (checkbox) {
      const stylNode = checkbox.parentElement
      stylNode.style.backgroundColor = 'inherit'
    })
    const jePlno = pocetPritomnych >= kapacita
    // tooltip se zbývající kapacitou
    const tooltipText = (jePlno ? 'Plno' : `Volno ${kapacita - pocetPritomnych}`) + ` (kapacita ${pocetPritomnych}/${kapacita})`
    const tooltipHtml = `<span class="${jePlno ? 'plno' : 'volno'}">${tooltipText}</span>`
    aktivitaNode.querySelectorAll('.styl-pro-dorazil-checkbox').forEach(function (stylProCheckboxNode) {
      zmenTooltip(tooltipHtml, stylProCheckboxNode)
    })
    Array.from(aktivitaNode.getElementsByClassName('omnibox')).forEach(function (omniboxElement) {
      zmenTooltip(tooltipHtml, omniboxElement)
      omniboxElement.placeholder = `${omniboxElement.dataset.vychoziPlaceholder} ${tooltipText.toLowerCase()}`
    })
  }
}

/**
 * @param {boolean} probihaji
 */
function vypustEventOProbihajicichZmenach(probihaji) {
  const provadimeZmenyEvent = new CustomEvent('probihajiZmeny', {detail: {probihaji: probihaji}})
  dejNodeOnlinePrezence().dispatchEvent(provadimeZmenyEvent)
}

/**
 * @param {number} idUzivatele
 * @param {number} idAktivity
 * @param {HTMLElement} checkboxNode
 * @param {HTMLElement|undefined} triggeringNode
 * @param {function|undefined} callbackOnSuccess
 */
function zmenitPritomnostUcastnika(idUzivatele, idAktivity, checkboxNode, triggeringNode, callbackOnSuccess) {
  vypustEventOProbihajicichZmenach(true)

  checkboxNode.disabled = true
  dorazil = checkboxNode.checked
  $.post(location.href, {
    /**
     * @see \Gamecon\Aktivita\OnlinePrezence\OnlinePrezenceAjax::odbavAjax
     * @see \Gamecon\Aktivita\OnlinePrezence\OnlinePrezenceAjax::ajaxZmenitPritomnostUcastnika
     */
    akce: 'zmenitPritomnostUcastnika',
    idAktivity: idAktivity,
    idUzivatele: idUzivatele,
    dorazil: dorazil ? 1 : 0,
    ajax: 1,
  }).done(/** @param {void|{prihlasen: boolean, cas_posledni_zmeny_prihlaseni: string, stav_prihlaseni: string, id_logu: string, razitko_posledni_zmeny: string}} data */function (data) {
    checkboxNode.disabled = false
    if (data && typeof data.prihlasen == 'boolean') {
      checkboxNode.checked = data.prihlasen

      const zmenaMetadatUcastnika = new CustomEvent('zmenaMetadatUcastnika', {
        detail: {
          casPosledniZmenyPrihlaseni: data.cas_posledni_zmeny_prihlaseni,
          stavPrihlaseni: data.stav_prihlaseni,
          idPoslednihoLogu: data.id_logu,
        },
      })
      const ucastnikNode = $(checkboxNode).parents('.ucastnik')[0]
      // bude zpracovano v zapisMetadataUcastnika()
      ucastnikNode.dispatchEvent(zmenaMetadatUcastnika)

      const zmenaMetadatPrezence = new CustomEvent('zmenaMetadatPrezence', {
        detail: {
          razitkoPosledniZmeny: data.razitko_posledni_zmeny,
        },
      })
      dejNodeOnlinePrezence().dispatchEvent(zmenaMetadatPrezence)

      if (callbackOnSuccess) {
        callbackOnSuccess()
      }
    }
  }).fail(function (response) {
    checkboxNode.checked = !checkboxNode.checked // vrátit zpět
    checkboxNode.disabled = false

    const detail = {
      triggeringNode: triggeringNode || checkboxNode,
    }

    if (response.status === 400 && response.responseJSON && response.responseJSON.errors) {
      detail.warnings = response.responseJSON.errors
    } else {
      detail.errors = ['Něco se pokazilo 😢']
    }

    triggeringNode = triggeringNode || checkboxNode
    const errorsEvent = new CustomEvent('ajaxErrors', {detail: detail})
    dejNodeAktivity(idAktivity).dispatchEvent(errorsEvent)
  }).always(function () {
    vypustEventOProbihajicichZmenach(false)
  })
}

/**
 * @param {number|string} idAktivity
 * @param {boolean} vcetneTlacitkaNaUzavreni
 */
function zablokovatEditaciAktivity(idAktivity, vcetneTlacitkaNaUzavreni = true) {
  akceAktivity.zablokovatEditaciAktivity(idAktivity, vcetneTlacitkaNaUzavreni)
}

/**
 * @param {number} idAktivity
 */
function uzavritAktivitu(idAktivity) {
  akceAktivity.uzavritAktivitu(idAktivity)
}

/**
 * @param {string} idAktivity
 * @param {number} editovatelnaDoTimestamp
 */
function reagujNaZamceniAktivity(idAktivity, editovatelnaDoTimestamp) {
  akceAktivity.reagujNaZamceniAktivity(idAktivity, editovatelnaDoTimestamp)
}

/**
 * @param {string} idAktivity
 * @param {number} editovatelnaDoTimestamp
 */
function reagujNaUzavreniAktivity(idAktivity, editovatelnaDoTimestamp) {
  akceAktivity.reagujNaUzavreniAktivity(idAktivity, editovatelnaDoTimestamp)
}

/**
 * @param {number|string} idUzivatele
 * @param {number|string} idAktivity
 * @return {HTMLElement}
 */
function dejNodeUcastnika(idUzivatele, idAktivity) {
  return document.getElementById(`ucastnik-${idUzivatele}-na-aktivite-${idAktivity}`)
}

/**
 * @param {number|string} idAktivity
 * @return {HTMLElement}
 */
function dejNodeAktivity(idAktivity) {
  return document.getElementById(`aktivita-${idAktivity}`)
}

/**
 * @return {HTMLElement}
 */
function dejNodeOnlinePrezence() {
  return document.getElementById('online-prezence')
}

/**
 * @param {number} unixTimestampInSeconds
 * @return {number}
 */
function spoctiKolikZbyvaSekund(unixTimestampInSeconds) {
  return Math.round(unixTimestampInSeconds - getNowAsUnixTimestampInSeconds())
}

/**
 * @return {number}
 */
function getNowAsUnixTimestampInSeconds() {
  return new Date().getTime() / 1000
}

/**
 * @param {HTMLElement} aktivitaNode
 */
function upravUkazateleZaplnenostiAktivity(aktivitaNode) {
  akceAktivity.upravUkazateleZaplnenostiAktivity(aktivitaNode)
}
