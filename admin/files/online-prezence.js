(function ($) {
  $(function () {

    // OMNIBOX
    intializePrezenceOmnibox()

    function intializePrezenceOmnibox() {
      const omnibox = $('.online-prezence .omnibox')
      omnibox.on('autocompleteselect', function (event, ui) {
        const idAktivity = this.dataset.idAktivity
        const ucastniciAktivityNode = $(`#ucastniciAktivity${idAktivity}`)
        const novyUcastnik = $(ui.item.html)

        ucastniciAktivityNode.append(novyUcastnik)
        // trigger change pro potvrzení vybraného nového účastníka, viz JS funkce 'zmenitUcastnika'
        novyUcastnik.find('input').trigger('change')

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

function uzavritAktivitu(idAktivity, skrytElement, zobrazitElement) {
  $.post(location.href, {akce: 'uzavrit', id: idAktivity, ajax: true}).done(function (data) {
    prohoditZobrazeni(skrytElement, zobrazitElement)
    if (data.maPravoNaZmenuHistorieAktivit) {
      zobrazitVarovaniZeAktivitaUzJeVyplena(idAktivity)
    } else {
      zablokovatEditaciAktivity(idAktivity)
    }
  })
}

function zablokovatEditaciAktivity(idAktivity) {
  zablokovatInputyAktivity(idAktivity)
  $(`.skryt-pokud-aktivitu-nelze-editovat-${idAktivity}`).hide()
}

function zablokovatInputyAktivity(idAktivity) {
  const aktivitaNode = $(`#aktivita-${idAktivity}`)
  aktivitaNode.find('input').prop('disabled', true)
}

function zobrazitVarovaniZeAktivitaUzJeVyplena(idAktivity) {
  $(`#pozor-vyplnena-${idAktivity}`).show()
}

function prohoditZobrazeni(skrytElement, zobrazitElement) {
  skrytElement.style.display = 'none'
  zobrazitElement.style.display = 'initial'
}

function zmenitUcastnika(idUzivatele, idAktivity, checkboxNode) {
  checkboxNode.disabled = true
  dorazil = checkboxNode.checked
  $.post(location.href, {
    akce: 'zmenitUcastnika', idAktivity: idAktivity, idUzivatele: idUzivatele, dorazil: dorazil ? 1 : 0, ajax: 1,
  }).done(function (data) {
    checkboxNode.disabled = false
    if (data && typeof data.prihlasen == 'boolean') {
      checkboxNode.checked = data.prihlasen
    }
  })
}
