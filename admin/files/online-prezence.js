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

    /**
     * @param {HTMLElement} ucastnikNode
     * @param {{casPosledniZmenyPrihlaseni: string, stavPrihlaseni: string}} metadataPrezence
     */
    function zapisMetadataPrezence(ucastnikNode, metadataPrezence) {
      ucastnikNode.dataset.casPosledniZmenyPrihlaseni = metadataPrezence.casPosledniZmenyPrihlaseni
      ucastnikNode.dataset.stavPrihlaseni = metadataPrezence.stavPrihlaseni
    }

    // ZMENA METADAT PREZENCE UCASTNIKA
    $('.ucastnik').each(function (index, ucastnikNode) {
      ucastnikNode.addEventListener(
        'zmenaMetadatPrezence',
        function (/** @param {{detail: {casPosledniZmenyPrihlaseni: string, stavPrihlaseni: string}}} event */event) {
          zapisMetadataPrezence(ucastnikNode, event.detail)
        })
    })

    // OMNIBOX
    intializePrezenceOmnibox()

    function intializePrezenceOmnibox() {
      const omnibox = $('.online-prezence .omnibox')
      omnibox.on('autocompleteselect', function (event, ui) {
        const idAktivity = this.dataset.idAktivity
        const idUcastnika = ui.item.value
        const ucastniciAktivityNode = $(`#ucastniciAktivity${idAktivity}`)
        const novyUcastnik = $(ui.item.html)

        zmenitUcastnika(
          idUcastnika,
          idAktivity,
          novyUcastnik.find('input')[0],
          function () {
            /**
             * Teprve až backend potvrdí uložení vybraného účastníka a JS přidá čas poslední změny a stav přihlášení,
             * tak můžeme přidat řádek s tímto účastníkem.
             * Data z řádku totiž potřebujeme pro kontrolu změn v online-prezence-posledni-zname-zmeny-prihlaseni.js
             */
            ucastniciAktivityNode.append(novyUcastnik)
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

    const $aktivity = $('.aktivita')

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
      const akivitaNode = $(`#aktivita-${idAktivity}`)
      akivitaNode.find('input').prop('disabled', false)
      akivitaNode.find('.text-ceka').hide()
      akivitaNode.find('.tlacitko-uzavrit-aktivitu').show()
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

      odpocetNode.text(zbyvaSekund + ' s')
      return true
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

    // ✋ AKTIVITA UŽ SKONČILA, ÚPRAVY SI DOBŘE ROZMYSLI ✋
    $aktivity.each(function () {
      const $aktivitaNode = $(this)
      $aktivitaNode.find('.text-skoncila').each(function () {
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

/**
 * @param {number} idUzivatele
 * @param {number} idAktivity
 * @param {HTMLElement} checkboxNode
 * @param {function|undefined} callbackOnSuccess
 */
function zmenitUcastnika(idUzivatele, idAktivity, checkboxNode, callbackOnSuccess) {
  checkboxNode.disabled = true
  dorazil = checkboxNode.checked
  $.post(location.href, {
    /**
     * @see \Gamecon\Aktivita\OnlinePrezence\OnlinePrezenceAjax::odbavAjax
     * @see \Gamecon\Aktivita\OnlinePrezence\OnlinePrezenceAjax::ajaxZmenitUcastnikaAktivity
     */
    akce: 'zmenitUcastnika', idAktivity: idAktivity, idUzivatele: idUzivatele, dorazil: dorazil ? 1 : 0, ajax: 1,
  }).done(/** @param {void|{prihlasen: boolean, cas_posledni_zmeny_prihlaseni: string, stav_prihlaseni: string}} data */function (data) {
    checkboxNode.disabled = false
    if (data && typeof data.prihlasen == 'boolean') {
      checkboxNode.checked = data.prihlasen

      const zmenaMetadatPrezence = new CustomEvent(
        'zmenaMetadatPrezence',
        {
          detail: {
            casPosledniZmenyPrihlaseni: data.cas_posledni_zmeny_prihlaseni,
            stavPrihlaseni: data.stav_prihlaseni,
          },
        },
      )
      const ucastnikNode = $(checkboxNode).parents('.ucastnik')[0]
      // bude zpracovano v zapisMetadataPrezence()
      ucastnikNode.dispatchEvent(zmenaMetadatPrezence)

      if (callbackOnSuccess) {
        callbackOnSuccess()
      }
    }
  })
}

const akceAktivity = new class AkceAktivity {

  /**
   * @public
   * @param {number} idAktivity
   * @param {HTMLElement} skrytElement
   * @param {HTMLElement} zobrazitElement
   */
  uzavritAktivitu(idAktivity, skrytElement, zobrazitElement) {
    const that = this
    $.post(location.href, {akce: 'uzavrit', id: idAktivity, ajax: true}).done(function (data) {
      that.prohoditZobrazeni(skrytElement, zobrazitElement)
      if (data.maPravoNaZmenuHistorieAktivit) {
        that.zobrazitVarovaniZeAktivitaUzJeVyplena(idAktivity)
      } else {
        that.zablokovatEditaciAktivity(idAktivity)
      }
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
  zobrazitVarovaniZeAktivitaUzJeVyplena(idAktivity) {
    $(`#pozor-vyplnena-${idAktivity}`).show()
  }

  /**
   * @private
   * @param {HTMLElement} skrytElement
   * @param {HTMLElement} zobrazitElement
   */
  prohoditZobrazeni(skrytElement, zobrazitElement) {
    skrytElement.style.display = 'none'
    zobrazitElement.style.display = 'initial'
  }
}

/**
 * @param {number} idAktivity
 * @param {HTMLElement} skrytElement
 * @param {HTMLElement} zobrazitElement
 */
function uzavritAktivitu(idAktivity, skrytElement, zobrazitElement) {
  akceAktivity.uzavritAktivitu(idAktivity, skrytElement, zobrazitElement)
}
