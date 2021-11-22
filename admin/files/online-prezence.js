(function ($) {
  $(function () {

    const intializePrezenceOmnibox = function () {
      const omnibox = $('.online-prezence .omnibox')
      omnibox.on('autocompleteselect', function (event, ui) {
        const ucastniciAktivityNode = $('#ucastniciAktivity')
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
        if (!ui || ui.content.length === 0) {
          $('.online-prezence .omnibox-nic-nenalezeno').show()
        } else {
          $('.online-prezence .omnibox-nic-nenalezeno').hide()
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

    intializePrezenceOmnibox()
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
