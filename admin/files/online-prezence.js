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

      omnibox.on('input', function (event) {
        const idAktivity = this.dataset.idAktivity
        $(`#omniboxNicNenalezeno${idAktivity}`).hide()
        const minLength = this.dataset.omniboxMinLength
        const length = this.value.length
        if (minLength <= length) {
          $(`#omniboxHledam${idAktivity}`).show()
        }
      })

      $('.formAktivita').submit(function () {
        var aktivita = $(this).closest('.blokAktivita')
        // test na vyplnění políček / potvrzení
        var policek = aktivita.find('[type=checkbox]').length
        var vybrano = aktivita.find('[type=checkbox]:checked').length
        if (vybrano < policek / 2) {
          if (!confirm('Opravdu uložit s účastí menší jak polovina?')) {
            return false
          }
        }
        // odeslání
        aktivita.find('[type=submit]').attr('disabled', true)
        aktivita.load(
          document.URL + ' .blokAktivita[data-id=' + aktivita.data('id') + '] > *',
          $(this).serializeObject(),
          function () {
            initializeOmnibox($)
            intializePrezenceOmnibox()
          },
        )
        return false
      })
    }

    // ⏳ ČEKÁNÍ NA EDITACI ⏳

    $('.aktivita').each(function () {
      const $aktivitaNode = $(this)
      $aktivitaNode.find('.text-ceka .odpocet').each(function () {
        if ($(this).data('editovatelna-od') > 0) {
          zablokovatAktivituProEditaciSOdpoctem($aktivitaNode.data('id'))
        }
      })
    })

    function zablokovatAktivituProEditaciSOdpoctem(idAktivity) {
      const aktivitaNode = $(`#aktivita-${idAktivity}`)
      aktivitaNode.find('input').prop('disabled', true)
      aktivitaNode.find('.tlacitko-uzavrit-aktivitu').hide()
      aktivitaNode.find('.text-ceka').show()
      spustitOdpocet(aktivitaNode, idAktivity)
    }

    function spustitOdpocet(aktivitaNode, idAktivity) {
      const odpocetNode = aktivitaNode.find(`#odpocet-${idAktivity}`)
      const editovatelnaOdTimestamp = Number.parseInt(odpocetNode.data('editovatelna-od'))
      const interval = 1000

      const intervalId = setInterval(function () {
        const sekundOdpoctu = spoctiKolikZbyvaSekundOdpoctu(editovatelnaOdTimestamp)
        if (!obnovitOdpocet(odpocetNode, sekundOdpoctu)) {
          clearInterval(intervalId)
          odblokovatAktivituProEditaci(idAktivity)
        }
      }, interval)
      obnovitOdpocet(odpocetNode, spoctiKolikZbyvaSekundOdpoctu(editovatelnaOdTimestamp)) // initial
    }

    function odblokovatAktivituProEditaci(idAktivity) {
      const akivitaNode = $(`#aktivita-${idAktivity}`)
      akivitaNode.find('input').prop('disabled', false)
      akivitaNode.find('.text-ceka').hide()
      akivitaNode.find('.tlacitko-uzavrit-aktivitu').show()
    }

    /**
     * @param {object} odpocetNode
     * @param {number} zbyvaSekund
     * @return {boolean}
     */
    function obnovitOdpocet(odpocetNode, zbyvaSekund) {
      if (zbyvaSekund <= 0) {
        return false
      }
      odpocetNode.text(zbyvaSekund + ' s')
      return true
    }

    /**
     * @param {int} editovatelnaOdTimestamp
     * @return {number}
     */
    function spoctiKolikZbyvaSekundOdpoctu(editovatelnaOdTimestamp) {
      return Math.round(editovatelnaOdTimestamp - (new Date().getTime() / 1000))
    }
  })
})(jQuery)

function uzavritAktivitu(idAktivity, skrytElement, zobrazitElement) {
  $.post(
    location.href,
    {akce: 'uzavrit', id: idAktivity, ajax: true},
  ).done(function (data) {
    prohoditZobrazeni(skrytElement, zobrazitElement)
  })
}

function prohoditZobrazeni(skrytElement, zobrazitElement) {
  skrytElement.style.display = 'none'
  zobrazitElement.style.display = 'initial'
}

function zmenitUcastnika(idUzivatele, idAktivity, checkboxNode) {
  checkboxNode.disabled = true
  dorazil = checkboxNode.checked
  $.post(
    location.href,
    {akce: 'zmenitUcastnika', idAktivity: idAktivity, idUzivatele: idUzivatele, dorazil: dorazil ? 1 : 0, ajax: 1},
  ).done(function (data) {
    checkboxNode.disabled = false
    if (data && typeof data.prihlasen == 'boolean') {
      checkboxNode.checked = data.prihlasen
    }
  })
}
