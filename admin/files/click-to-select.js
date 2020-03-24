(function ($) {
    const clickToSelectElements = $('.click-to-select')
    clickToSelectElements.on('click', function () {
      const clickToSelect = this
      $(clickToSelect).parent().find('input[type=radio]').each(function () {
        if (this !== clickToSelect) {
          this.checked = false
        }
      })

      const radio = $(clickToSelect).find('input[type=radio]')
      if (!radio.prop('checked')) {
        radio.prop('checked', true)
        radio.trigger('change')
      }
    })

    clickToSelectElements.css('cursor', 'pointer')
    clickToSelectElements.find('a, input[type=radio]').on('click', function (event) {
      event.stopPropagation()
    })

    const solveImportSubmitActivation = function () {
      let submitIsAllowed = false
      $('input[type=radio][name=googleSheetId]').each(function (index, radio) {
        if (radio.checked) {
          submitIsAllowed = true
        }
      })
      const disabled = submitIsAllowed
        ? null
        : true
      $('#importSubmit').attr('disabled', disabled)
      if (submitIsAllowed) {
        $('#googleSheetNotSelected').hide()
      } else {
        $('#googleSheetNotSelected').show()
      }
    }
    $('input[type=radio][name=googleSheetId]').on('change', function () {
      solveImportSubmitActivation()
    })
    solveImportSubmitActivation()
  }
)(jQuery)
