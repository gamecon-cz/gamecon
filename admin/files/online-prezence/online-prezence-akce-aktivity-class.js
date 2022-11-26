import {
  AjaxErrors,
  NovyUcastnik,
  ProbihajiZmeny,
  ZmenaMetadatPrezence,
  ZmenaMetadatUcastnika,
} from "./online-prezence-eventy.js"

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
   * @param {number|string} idAktivity
   * @param {number} ucastniciPridatelniDoTimestamp
   * @param {number} ucastniciOdebratelniDoTimestamp
   */
  reagujNaZamceniAktivity(idAktivity, ucastniciPridatelniDoTimestamp, ucastniciOdebratelniDoTimestamp) {
    const zobrazitElement = document.getElementById(`zamcena-${idAktivity}`)
    this.zobrazit(zobrazitElement)

    this.zpracovatEditovatelnostDoPoZamknuti(idAktivity, ucastniciPridatelniDoTimestamp, ucastniciOdebratelniDoTimestamp)
  }

  /**
   * @private
   * @param {number|string} idAktivity
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
      that.skryt(element)
    })
    nodeAktivity.querySelectorAll('.zobrazit-pokud-aktivitu-nelze-editovat').forEach(function (element) {
      that.zobrazit(element)
    })
    this.skrytVarovaniZeAktivitaJeZamcena(idAktivity)
  }

  /**
   * @param {string|number} idAktivity
   */
  odblokovatAktivituProEditaci(idAktivity) {
    const $aktivitaNode = $(`#aktivita-${idAktivity}`)
    $aktivitaNode.find('input').prop('disabled', false)
    $aktivitaNode.find('.text-ceka').addClass('display-none')
    $aktivitaNode.find(`.zobrazit-pokud-aktivitu-nelze-editovat`).addClass('display-none')
    $aktivitaNode.find('.tlacitko-uzavrit-aktivitu').removeClass('display-none')
  }

  /**
   * @param {number|string} idAktivity
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
    this.zobrazit(document.getElementById(`pozor-zamcena-${idAktivity}`))
  }

  /**
   * @private
   * @param idAktivity
   */
  skrytVarovaniZeAktivitaJeZamcena(idAktivity) {
    this.skryt(document.getElementById(`pozor-zamcena-${idAktivity}`))
  }

  /**
   * @param {HTMLElement[]} skrytElementy
   * @param {HTMLElement} zobrazitElement
   */
  prohoditZobrazeni(skrytElementy, zobrazitElement) {
    const that = this
    skrytElementy.forEach(function (skrytElement) {
      that.skryt(skrytElement)
    })
    this.zobrazit(zobrazitElement)
  }

  /**
   * @private
   * @param {HTMLElement} element
   */
  zobrazit(element) {
    element.classList.remove('display-none')
  }

  /**
   * @private
   * @param {HTMLElement} element
   */
  skryt(element) {
    element.classList.add('display-none')
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

    const originalDisabled = checkboxNode.disabled
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
      checkboxNode.disabled = originalDisabled
      if (data && typeof data.prihlasen == 'boolean') {
        checkboxNode.checked = data.prihlasen

        if (callbackOnSuccessBeforeMetadataChange) {
          callbackOnSuccessBeforeMetadataChange()
        }

        const zmenaMetadatUcastnika = ZmenaMetadatUcastnika.vytvor(
          data.cas_posledni_zmeny_prihlaseni,
          data.stav_prihlaseni,
          data.id_logu,
        )
        const ucastnikNode = that.dejNodeUcastnika(idUcastnika, idAktivity)
        ucastnikNode.dispatchEvent(zmenaMetadatUcastnika)

        const zmenaMetadatPrezence = ZmenaMetadatPrezence.vytvor(data.razitko_posledni_zmeny)
        that.dejNodeOnlinePrezence().dispatchEvent(zmenaMetadatPrezence)

        if (callbackOnSuccessAfterMetadataChange) {
          callbackOnSuccessAfterMetadataChange()
        }
      }
    }).fail(function (response) {
      checkboxNode.checked = !checkboxNode.checked // vrátit zpět
      checkboxNode.disabled = originalDisabled

      const problems = {
        triggeringNode: triggeringNode || checkboxNode,
      }

      if (response.status === 400 && response.responseJSON && response.responseJSON.errors) {
        problems.warnings = response.responseJSON.errors
      } else {
        problems.errors = ['Něco se pokazilo 😢']
      }

      triggeringNode = triggeringNode || checkboxNode
      const ajaxErrors = AjaxErrors.vytvor(problems)
      that.dejNodeAktivity(idAktivity).dispatchEvent(ajaxErrors)
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

  /**
   * Bude zpracováno v event listeneru přes zaznamenejNovehoUcastnika()
   * @param {number} idUzivatele
   * @param {number} idAktivity
   */
  vypustEventONovemUcastnikovi(idUzivatele, idAktivity) {
    const novyUcastnik = NovyUcastnik.vytvor(idUzivatele, idAktivity)
    this.dejNodeAktivity(idAktivity).dispatchEvent(novyUcastnik)
  }

  /**
   * @param {HTMLElement} ucastnikNode
   */
  hlidejZmenyMetadatUcastnika(ucastnikNode) {
    const that = this
    ucastnikNode.addEventListener(
      ZmenaMetadatUcastnika.eventName,
      function (/** @param {{detail: {casPosledniZmenyPrihlaseni: string, stavPrihlaseni: string, idPoslednihoLogu: number}}} event */event) {
        that.zapisMetadataUcastnika(ucastnikNode, event.detail)
        that.zobrazTypUcastnika(ucastnikNode, event.detail.stavPrihlaseni)
        upravUkazateleZaplnenostiAktivity(that.dejNodeAktivity(ucastnikNode.dataset.idAktivity))
      },
    )
  }

  /**
   * @private
   * @param {HTMLElement} ucastnikNode
   * @param {{casPosledniZmenyPrihlaseni: string, stavPrihlaseni: string, idPoslednihoLogu: number, callback: function|undefined}} metadata
   */
  zapisMetadataUcastnika(ucastnikNode, metadata) {
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
   * @private
   * @param {HTMLElement} ucastnikNode
   * @param {string} stavPrihlaseni
   */
  zobrazTypUcastnika(ucastnikNode, stavPrihlaseni) {
    const idUzivatele = ucastnikNode.dataset.id
    const idAktivity = ucastnikNode.dataset.idAktivity
    const naPosledniChvili = document.getElementById(`ucastnik-${idUzivatele}-na-posledni-chvili-na-aktivitu-${idAktivity}`)
    const jeNahradnik = document.getElementById(`ucastnik-${idUzivatele}-je-nahradnik-na-aktivite-${idAktivity}`)
    const jeSledujici = document.getElementById(`ucastnik-${idUzivatele}-je-sledujici-aktivity-${idAktivity}`)
    const jeSpici = document.getElementById(`ucastnik-${idUzivatele}-je-spici-na-aktivite-${idAktivity}`)
    switch (stavPrihlaseni) {
      case 'sledujici_se_prihlasil' :
        this.skryt(jeNahradnik)
        this.zobrazit(jeSledujici)
        this.skryt(jeSpici)
        break
      case 'nahradnik_nedorazil' :
        this.skryt(jeNahradnik)
        this.skryt(jeSledujici)
        this.zobrazit(jeSpici)
        break
      case 'nahradnik_dorazil' :
        this.skryt(jeSledujici)
        this.skryt(jeSpici)
        this.zobrazit(jeNahradnik)
        break
      case 'ucastnik_dorazil' :
        this.skryt(jeSledujici)
        this.skryt(jeNahradnik)
        this.skryt(jeSpici)
        if (naPosledniChvili) {
          this.skryt(naPosledniChvili)
        }
        break
      case 'ucastnik_se_prihlasil' :
        this.skryt(jeSledujici)
        this.skryt(jeNahradnik)
        this.skryt(jeSpici)
        if (naPosledniChvili) {
          this.zobrazit(naPosledniChvili)
        }
        break
      default :
        this.skryt(jeNahradnik)
        this.skryt(jeSledujici)
        this.skryt(jeSpici)
    }
  }
}

export {AkceAktivity}
