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

    Array.from(document.querySelectorAll('[data-bs-toggle="tooltip"]')).forEach(function (tooltipElement) {
      bootstrap.Tooltip.getOrCreateInstance(tooltipElement).update()
    })

    // ZMENA METADAT PREZENCE UCASTNIKA

    const $aktivity = $('.aktivita')

    $aktivity.each(function (index, aktivitaNode) {
      aktivitaNode.addEventListener('novyUcastnik', function (/** @param {{detail: {idAktivity: number, idUzivatele: number}}} event */event) {
        hlidejNovehoUcastnika(event.detail.idUzivatele, event.detail.idAktivity)
      })
    })

    $('.ucastnik').each(function (index, ucastnikNode) {
      hlidejZmenyMetadatUcastnika(ucastnikNode)
      aktivujTooltipUcastnika(ucastnikNode.dataset.id, ucastnikNode.dataset.idAktivity)
    })

    /**
     * @param {number|string} idUzivatele
     * @param {number|string} idAktivity
     */
    function hlidejNovehoUcastnika(idUzivatele, idAktivity) {
      hlidejZmenyMetadatUcastnika(dejNodeUcastnika(idUzivatele, idAktivity))
      aktivujTooltipUcastnika(idUzivatele, idAktivity)
      upravUkazateleZaplnenostiAktivity(dejNodeAktivity(idAktivity))
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
      ucastnikNode.addEventListener(
        'zmenaMetadatUcastnika',
        function (/** @param {{detail: {casPosledniZmenyPrihlaseni: string, stavPrihlaseni: string, idPoslednihoLogu: number}}} event */event) {
          zapisMetadataUcastnika(ucastnikNode, event.detail)
          zobrazTypUcastnika(ucastnikNode, event.detail.stavPrihlaseni)
          upravUkazateleZaplnenostiAktivity(dejNodeAktivity(ucastnikNode.dataset.idAktivity))
        },
      )
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
     * Bude zpracováno v event listeneru přes hlidejNovehoUcastnika()
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

        zmenitPritomnostUcastnika(
          idUzivatele,
          idAktivity,
          novyUcastnik.find('input')[0],
          this, // kde vznikl požadavek a kde ukázat případné errory
          function () {
            /**
             * Teprve až backend potvrdí uložení vybraného účastníka a JS přidá čas poslední změny a stav přihlášení,
             * tak můžeme přidat řádek s tímto účastníkem.
             * Data z řádku totiž potřebujeme pro kontrolu změn v online-prezence-posledni-zname-zmeny-prihlaseni.js
             */
            ucastniciAktivityNode.append(novyUcastnik)
            vypustEventONovemUcastnikovi(idUzivatele, idAktivity)
          },
        )

        // vyrušení default výběru do boxu
        event.preventDefault()
        $(this).val('')

        // skrytí výchozí oklivé hlášky
        $('.ui-helper-hidden-accessible').hide()
      })

      omnibox.on('autocompleteresponse', function (event, ui) {
        const idAktivity = this.dataset.idAktivity
        $(`#omniboxHledam${idAktivity}`).hide()
        if (!ui || ui.content === undefined || ui.content.length === 0) {
          $(`#omniboxNicNenalezeno${idAktivity}`).show()
        } else {
          $(`#omniboxNicNenalezeno${idAktivity}`).hide()
        }
      })

      omnibox.on('input', function () {
        const idAktivity = this.dataset.idAktivity
        $(`#omniboxNicNenalezeno${idAktivity}`).hide()
        const minLength = this.dataset.omniboxMinLength
        const length = this.value.length
        if (minLength <= length) {
          $(`#omniboxHledam${idAktivity}`).show()
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
      const $aktivitaNode = $(this)
      $aktivitaNode.find('.text-ceka .odpocet').each(function () {
        if ($(this).data('editovatelna-od') > 0) {
          zablokovatAktivituProEditaciSOdpoctem($aktivitaNode.data('id'))
        }
      })
    })

    function zablokovatAktivituProEditaciSOdpoctem(idAktivity) {
      const $aktivitaNode = $(`#aktivita-${idAktivity}`)
      $aktivitaNode.find('input').prop('disabled', true)
      $aktivitaNode.find('.tlacitko-uzavrit-aktivitu').hide()
      $aktivitaNode.find('.text-ceka').show()
      spustitOdpocet($aktivitaNode, idAktivity)
    }

    function spustitOdpocet(aktivitaNode, idAktivity) {
      const $odpocetNode = aktivitaNode.find(`#odpocet-${idAktivity}`)
      const editovatelnaOdTimestamp = Number.parseInt($odpocetNode.data('editovatelna-od'))

      if (dokoncitOdpocetProEditaci($odpocetNode, idAktivity, editovatelnaOdTimestamp)) {
        return
      }

      const interval = 1000
      const intervalId = setInterval(function () {
        if (dokoncitOdpocetProEditaci($odpocetNode, idAktivity, editovatelnaOdTimestamp)) {
          clearInterval(intervalId)
        }
      }, interval)
    }

    /**
     * @param {object} $odpocetNode
     * @param idAktivity
     * @param {number} editovatelnaOdTimestamp
     * @return {boolean}
     */
    function dokoncitOdpocetProEditaci($odpocetNode, idAktivity, editovatelnaOdTimestamp) {
      if (obnovitOdpocet($odpocetNode, editovatelnaOdTimestamp)) {
        return false // ještě nemůžeme odpočet dokončit, stále musí běžet
      }
      odblokovatAktivituProEditaci(idAktivity)
      return true
    }

    function odblokovatAktivituProEditaci(idAktivity) {
      const $aktivitaNode = $(`#aktivita-${idAktivity}`)
      $aktivitaNode.find('input').prop('disabled', false)
      $aktivitaNode.find('.text-ceka').hide()
      $aktivitaNode.find('.tlacitko-uzavrit-aktivitu').show()
    }

    /**
     * @param {object} odpocetNode
     * @param {number} editovatelnaOdTimestamp
     * @return {boolean}
     */
    function obnovitOdpocet(odpocetNode, editovatelnaOdTimestamp) {
      const zbyvaSekund = spoctiKolikZbyvaSekund(editovatelnaOdTimestamp)

      if (zbyvaSekund <= 0) {
        return false
      }

      odpocetNode.text(sekundyNaLidskyCas(zbyvaSekund))
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

    // ✋ AKTIVITA UŽ SKONČILA, POZOR NA ÚPRAVY ✋
    $aktivity.each(function () {
      const $aktivitaNode = $(this)
      $aktivitaNode.find('.text-skoncila').each(function () {
        if (this.classList.contains('display-none')) {
          return
        }
        const $textSkoncilaNode = $(this)
        hlidatUpozorneniNaSkoncenouAktivitu($textSkoncilaNode)
      })
    })

    function hlidatUpozorneniNaSkoncenouAktivitu($textSkoncilaNode) {
      const konecAktivityVTimestamp = Number.parseInt($textSkoncilaNode.data('konec-aktivity-v'))
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
      $textSkoncilaNode.show()
      return true
    }
  })
})(jQuery)

const akceAktivity = new class AkceAktivity {

  /**
   * @public
   * @param {number} idAktivity
   * @param {HTMLElement} skrytElement
   * @param {HTMLElement} zobrazitElement
   */
  uzavritAktivitu(idAktivity, skrytElement, zobrazitElement) {
    vypustEventOProbihajicichZmenach(true)

    const that = this
    $.post(location.href, {
      /** viz \Gamecon\Aktivita\OnlinePrezence\OnlinePrezenceAjax::ajaxUzavritAktivitu */
      akce: 'uzavrit', id: idAktivity, ajax: true,
    }).done(function (/** @param {{editovatelnaSekund: number}} data */data) {
      that.prohoditZobrazeni(skrytElement, zobrazitElement)
      if (data.editovatelnaSekund > 0) {
        that.zobrazitVarovaniZeAktivitaUzJeUzavrena(idAktivity)
        setTimeout(function () {
          that.zablokovatEditaciAktivity(idAktivity)
          that.skrytVarovaniZeAktivitaUzJeUzavrena(idAktivity)
        }, data.editovatelnaSekund * 1000)
      } else {
        that.zablokovatEditaciAktivity(idAktivity)
      }
    }).always(function () {
      vypustEventOProbihajicichZmenach(false)
    })
  }

  /**
   * @private
   * @param idAktivity
   */
  zablokovatEditaciAktivity(idAktivity) {
    this.zablokovatInputyAktivity(idAktivity)
    $(`.skryt-pokud-aktivitu-nelze-editovat-${idAktivity}`).hide()
  }

  /**
   * @private
   * @param idAktivity
   */
  zablokovatInputyAktivity(idAktivity) {
    const aktivitaNode = $(`#aktivita-${idAktivity}`)
    aktivitaNode.find('input').prop('disabled', true)
  }

  /**
   * @private
   * @param idAktivity
   */
  zobrazitVarovaniZeAktivitaUzJeUzavrena(idAktivity) {
    $(`#pozor-uzavrena-${idAktivity}`).show()
  }

  /**
   * @private
   * @param idAktivity
   */
  skrytVarovaniZeAktivitaUzJeUzavrena(idAktivity) {
    $(`#pozor-uzavrena-${idAktivity}`).hide()
  }

  /**
   * @param {HTMLElement} skrytElement
   * @param {HTMLElement} zobrazitElement
   */
  prohoditZobrazeni(skrytElement, zobrazitElement) {
    skrytElement.style.display = 'none'
    zobrazitElement.style.display = 'initial'
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
function zmenitPritomnostUcastnika(
  idUzivatele,
  idAktivity,
  checkboxNode,
  triggeringNode,
  callbackOnSuccess,
) {
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
      // bude zpracovano v zapisMetadataPrezence()
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
 * @param {number} idAktivity
 * @param {HTMLElement} skrytElement
 * @param {HTMLElement} zobrazitElement
 */
function uzavritAktivitu(idAktivity, skrytElement, zobrazitElement) {
  akceAktivity.uzavritAktivitu(idAktivity, skrytElement, zobrazitElement)
}

/**
 * @param {HTMLElement} skrytElement
 * @param {HTMLElement} zobrazitElement
 */
function prohoditZobrazeni(skrytElement, zobrazitElement) {
  akceAktivity.prohoditZobrazeni(skrytElement, zobrazitElement)
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
 * @param {HTMLElement} aktivitaNode
 */
function upravUkazateleZaplnenostiAktivity(aktivitaNode) {
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
  Array.from(aktivitaNode.querySelectorAll('.styl-pro-dorazil-checkbox > input[type=checkbox]:not(:checked)')).forEach(function (checkbox) {
    const stylNode = checkbox.parentElement
    stylNode.style.backgroundColor = 'inherit'
  })
  const jePlno = pocetPritomnych >= kapacita
  // tooltip se zbývající kapacitou
  const tooltipText = (jePlno
      ? 'Plno'
      : `Volno ${kapacita - pocetPritomnych}`
  ) + ` (kapacita ${pocetPritomnych}/${kapacita})`
  const tooltipHtml = `<span class="${jePlno ? 'plno' : 'volno'}">${tooltipText}</span>`
  Array.from(aktivitaNode.querySelectorAll('.styl-pro-dorazil-checkbox')).forEach(function (stylProCheckboxNode) {
    zmenTooltip(tooltipHtml, stylProCheckboxNode)
  })
  Array.from(aktivitaNode.getElementsByClassName('omnibox')).forEach(function (omniboxElement) {
    zmenTooltip(tooltipHtml, omniboxElement)
    omniboxElement.placeholder = `${omniboxElement.dataset.vychoziPlaceholder} ${tooltipText.toLowerCase()}`
  })
}
