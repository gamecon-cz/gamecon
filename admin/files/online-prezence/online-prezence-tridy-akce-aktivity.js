import {ProbihajiZmeny} from "./online-prezence-eventy.js"

class AkceAktivity {

  /**
   * @param {boolean} probihaji
   */
  vypustEventOProbihajicichZmenach(probihaji) {
    const probihajiZmenyEvent = ProbihajiZmeny.vytvor(probihaji)
    this.dejNodeOnlinePrezence().dispatchEvent(probihajiZmenyEvent)
  }

  /**
   * @public
   * @param {number} idAktivity
   */
  uzavritAktivitu(idAktivity) {
    this.vypustEventOProbihajicichZmenach(true)

    const that = this
    $.post(location.href, {
      /** viz \Gamecon\Aktivita\OnlinePrezence\OnlinePrezenceAjax::ajaxUzavritAktivitu */
      akce: 'uzavrit', id: idAktivity, ajax: true,
    }).done(function (/** @param {{ucastnici_pridatelni_do_timestamp: number, ucastnici_odebratelni_do_timestamp: number}} data */data) {
      that.reagujNaUzavreniAktivity(
        idAktivity,
        data.ucastnici_pridatelni_do_timestamp,
        data.ucastnici_odebratelni_do_timestamp,
      )
    }).always(function () {
      that.vypustEventOProbihajicichZmenach(false)
    })
  }

  /**
   * @param {number} idAktivity
   * @param {number} ucastniciPridatelniDoTimestamp
   * @param {number} ucastniciOdebratelniDoTimestamp
   */
  reagujNaZamceniAktivity(idAktivity, ucastniciPridatelniDoTimestamp, ucastniciOdebratelniDoTimestamp) {
    const zobrazitElement = document.getElementById(`zamcena-${idAktivity}`)
    this.zobrazitElement(zobrazitElement)

    this.zpracovatEditovatelnostDoPoZamknuti(idAktivity, ucastniciPridatelniDoTimestamp, ucastniciOdebratelniDoTimestamp)
  }

  /**
   * @private
   * @param {number} idAktivity
   * @param {number} ucastniciPridatelniDoTimestamp
   * @param {number} ucastniciOdebratelniDoTimestamp
   */
  zpracovatEditovatelnostDoPoZamknuti(idAktivity, ucastniciPridatelniDoTimestamp, ucastniciOdebratelniDoTimestamp) {
    const ucastniciPridatelniSekund = this.spoctiKolikZbyvaSekund(ucastniciPridatelniDoTimestamp)
    const ucastniciOdebratelniSekund = this.spoctiKolikZbyvaSekund(ucastniciOdebratelniDoTimestamp)

    if (ucastniciPridatelniSekund > 0 || ucastniciOdebratelniSekund > 0) {
      this.zobrazitVarovaniZeAktivitaJeZamcena(idAktivity)
    }

    if (ucastniciPridatelniSekund > 0) {
      const that = this
      setTimeout(function () {
        that.zablokovatPridavaniNaAktivitu(idAktivity)
      }, ucastniciPridatelniSekund * 1000)
    } else {
      this.zablokovatPridavaniNaAktivitu(idAktivity)
    }

    if (ucastniciOdebratelniSekund > 0) {
      const that = this
      setTimeout(function () {
        that.zablokovatOdebiraniZAktivity(idAktivity)
      }, ucastniciOdebratelniSekund * 1000)
    } else {
      this.zablokovatOdebiraniZAktivity(idAktivity)
    }

    if (ucastniciPridatelniSekund <= 0 && ucastniciOdebratelniSekund <= 0) {
      this.oznacitAktivituJakoKompletneZablokovanou(idAktivity)
    } else {
      const that = this
      setTimeout(function () {
        that.oznacitAktivituJakoKompletneZablokovanou(idAktivity)
      }, Math.max(ucastniciPridatelniSekund, ucastniciOdebratelniSekund) * 1000)
    }
  }

  /**
   * @private
   * @param {number} idAktivity
   */
  oznacitAktivituJakoKompletneZablokovanou(idAktivity) {
    const that = this
    const nodeAktivity = this.dejNodeAktivity(idAktivity)
    nodeAktivity.querySelectorAll('.skryt-pokud-aktivitu-nelze-editovat').forEach(function (element) {
      that.skrytElement(element)
    })
    nodeAktivity.querySelectorAll('.zobrazit-pokud-aktivitu-nelze-editovat').forEach(function (element) {
      that.zobrazitElement(element)
    })
    this.skrytVarovaniZeAktivitaJeZamcena(idAktivity)
  }

  /**
   * @param {number} idAktivity
   * @param {number} ucastniciPridatelniDoTimestamp
   * @param {number} ucastniciOdebratelniDoTimestamp
   */
  reagujNaUzavreniAktivity(idAktivity, ucastniciPridatelniDoTimestamp, ucastniciOdebratelniDoTimestamp) {
    const skrytElementy = [
      document.getElementById(`otevrena-${idAktivity}`),
      document.getElementById(`zamcena-${idAktivity}`),
    ]
    const zobrazitElement = document.getElementById(`uzavrena-${idAktivity}`)
    this.prohoditZobrazeni(skrytElementy, zobrazitElement)

    this.zpracovatEditovatelnostDoPoZamknuti(idAktivity, ucastniciPridatelniDoTimestamp, ucastniciOdebratelniDoTimestamp)
  }

  /**
   * @param {number|string} idAktivity
   * @param {boolean} vcetneTlacitkaNaUzavreni
   */
  zablokovatPridavaniNaAktivitu(idAktivity, vcetneTlacitkaNaUzavreni = true) {
    this.zablokovatInputyAktivityProPridani(idAktivity)
  }

  /**
   * @param {number|string} idAktivity
   * @param {boolean} vcetneTlacitkaNaUzavreni
   */
  zablokovatOdebiraniZAktivity(idAktivity, vcetneTlacitkaNaUzavreni = true) {
    this.zablokovatInputyAktivityProOdebrani(idAktivity)
  }

  /**
   * @private
   * @param idAktivity
   */
  zablokovatInputyAktivityProPridani(idAktivity) {
    const $aktivitaNode = $(`#aktivita-${idAktivity}`)
    $aktivitaNode.find('input[type=checkbox]:not(:checked)').prop('disabled', true)
    $aktivitaNode.find('input.omnibox').prop('disabled', true)
  }

  /**
   * @private
   * @param idAktivity
   */
  zablokovatInputyAktivityProOdebrani(idAktivity) {
    const $aktivitaNode = $(`#aktivita-${idAktivity}`)
    $aktivitaNode.find('input[type=checkbox]:checked').prop('disabled', true)
  }

  /**
   * @private
   * @param idAktivity
   */
  zobrazitVarovaniZeAktivitaJeZamcena(idAktivity) {
    this.zobrazitElement(document.getElementById(`pozor-zamcena-${idAktivity}`))
  }

  /**
   * @private
   * @param idAktivity
   */
  skrytVarovaniZeAktivitaJeZamcena(idAktivity) {
    this.skrytElement(document.getElementById(`pozor-zamcena-${idAktivity}`))
  }

  /**
   * @param {HTMLElement[]} skrytElementy
   * @param {HTMLElement} zobrazitElement
   */
  prohoditZobrazeni(skrytElementy, zobrazitElement) {
    const that = this
    skrytElementy.forEach(function (skrytElement) {
      that.skrytElement(skrytElement)
    })
    this.zobrazitElement(zobrazitElement)
  }

  /**
   * @private
   * @param {HTMLElement} element
   */
  zobrazitElement(element) {
    element.classList.remove('display-none')
  }

  /**
   * @private
   * @param {HTMLElement} element
   */
  skrytElement(element) {
    element.classList.add('display-none')
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

    const ucastnici = aktivitaNode.querySelectorAll('.ucastnik')
    const prihlaseni = Array.from(ucastnici).filter((ucastnik) => this.jeToUcastnikPodleStavu(ucastnik.dataset.stavPrihlaseni))
    const pocetPrihlasenychCisloNode = aktivitaNode.querySelector('.pocet-prihlasenych-cislo')
    pocetPrihlasenychCisloNode.textContent = prihlaseni.length
  }


  /**
   * viz \Gamecon\Aktivita\ZmenaPrihlaseni::stavPrihlaseniProJs
   * @private
   * @param {string} stavPrihlaseni
   * @return {boolean}
   */
  jeToUcastnikPodleStavu(stavPrihlaseni) {
    switch (stavPrihlaseni) {
      case 'ucastnik_se_odhlasil' :
      case 'ucastnik_nedorazil' :
      case 'nahradnik_nedorazil' :
      case 'ucastnik_se_prihlasil' :
      case 'ucastnik_dorazil' :
      case 'nahradnik_dorazil' :
        return true
      default :
        return false
    }
  }

  /**
   * @public
   * @param {number} unixTimestampInSeconds
   * @return {number}
   */
  spoctiKolikZbyvaSekund(unixTimestampInSeconds) {
    return Math.round(unixTimestampInSeconds - this.getNowAsUnixTimestampInSeconds())
  }

  /**
   * @private
   * @return {number}
   */
  getNowAsUnixTimestampInSeconds() {
    return new Date().getTime() / 1000
  }

  /**
   * @param {number} idUcastnika
   * @param {number} idAktivity
   * @param {HTMLElement} checkboxNode
   * @param {HTMLElement} triggeringNode
   * @param {function|undefined} callbackOnSuccessBeforeMetadataChange
   * @param {function|undefined} callbackOnSuccessAfterMetadataChange
   */
  zmenitPritomnostUcastnika(
    idUcastnika,
    idAktivity,
    checkboxNode,
    triggeringNode,
    callbackOnSuccessBeforeMetadataChange,
    callbackOnSuccessAfterMetadataChange,
  ) {
    this.vypustEventOProbihajicichZmenach(true)

    checkboxNode.disabled = true
    const dorazil = checkboxNode.checked
    const that = this
    $.post(location.href, {
      /**
       * @see \Gamecon\Aktivita\OnlinePrezence\OnlinePrezenceAjax::odbavAjax
       * @see \Gamecon\Aktivita\OnlinePrezence\OnlinePrezenceAjax::ajaxZmenitPritomnostUcastnika
       */
      akce: 'zmenitPritomnostUcastnika',
      idAktivity: idAktivity,
      idUcastnika: idUcastnika,
      dorazil: dorazil ? 1 : 0,
      ajax: 1,
    }).done(/** @param {void|{prihlasen: boolean, cas_posledni_zmeny_prihlaseni: string, stav_prihlaseni: string, id_logu: string, razitko_posledni_zmeny: string}} data */function (data) {
      checkboxNode.disabled = false
      if (data && typeof data.prihlasen == 'boolean') {
        checkboxNode.checked = data.prihlasen

        if (callbackOnSuccessBeforeMetadataChange) {
          callbackOnSuccessBeforeMetadataChange()
        }

        const zmenaMetadatUcastnika = new CustomEvent('zmenaMetadatUcastnika', {
          detail: {
            casPosledniZmenyPrihlaseni: data.cas_posledni_zmeny_prihlaseni,
            stavPrihlaseni: data.stav_prihlaseni,
            idPoslednihoLogu: data.id_logu,
          },
        })
        const ucastnikNode = that.dejNodeUcastnika(idUcastnika, idAktivity)
        // const ucastnikNode = $(checkboxNode).closest('.ucastnik')
        // bude zpracovano v zapisMetadataUcastnika()
        ucastnikNode.dispatchEvent(zmenaMetadatUcastnika)

        const zmenaMetadatPrezence = new CustomEvent('zmenaMetadatPrezence', {
          detail: {
            razitkoPosledniZmeny: data.razitko_posledni_zmeny,
          },
        })
        that.dejNodeOnlinePrezence().dispatchEvent(zmenaMetadatPrezence)

        if (callbackOnSuccessAfterMetadataChange) {
          callbackOnSuccessAfterMetadataChange()
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
      that.dejNodeAktivity(idAktivity).dispatchEvent(errorsEvent)
    }).always(function () {
      that.vypustEventOProbihajicichZmenach(false)
    })
  }

  /**
   * @param {number|string} idUzivatele
   * @param {number|string} idAktivity
   * @return {HTMLElement}
   */
  dejNodeUcastnika(idUzivatele, idAktivity) {
    return document.getElementById(`ucastnik-${idUzivatele}-na-aktivite-${idAktivity}`)
  }

  /**
   * @param {number|string} idAktivity
   * @return {HTMLElement}
   */
  dejNodeAktivity(idAktivity) {
    return document.getElementById(`aktivita-${idAktivity}`)
  }

  /**
   * @return {HTMLElement}
   */
  dejNodeOnlinePrezence() {
    return document.getElementById(`online-prezence`)
  }
}

export {AkceAktivity}
