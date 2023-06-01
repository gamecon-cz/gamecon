document.addEventListener('tagyNahrane', function () {
  var $tagsMultiselect = $('#vyberTaguProgram')
  var clientWidthProgramLegenda = document.querySelector('.program_legenda').clientWidth
  var tagsMultiselectColumns = parseInt(clientWidthProgramLegenda / 250)
  if (tagsMultiselectColumns < 1) {
    tagsMultiselectColumns = 1
  }

  function hideSelectAllIfSomethingChecked(msOptionsElement) {
    var $msOptionsElement = $(msOptionsElement)
    var somethingChecked = $msOptionsElement.find('ul').find('input[type=checkbox]:checked').length > 0
    $msOptionsElement.find('.ms-selectall').css('visibility', somethingChecked ? '' : 'hidden')
  }

  function dispatchEventTagyVybrany() {
    document.dispatchEvent(
      new CustomEvent('tagyVybrany',
        {
          detail: Array.from(document.getElementById('vyberTaguProgram').querySelectorAll('option:checked')).map((optionElement) => optionElement.value)
        }
      )
    )
  }

  $tagsMultiselect.multiselect({
    columns: tagsMultiselectColumns,
    search: true,
    texts: {
      'placeholder': 'Vyber tagy',
      'search': 'Hledej tag',
      'selectedOptions': ' vybraných tagů',
      'selectAll': 'Vybrat vše',
      'unselectAll': 'Zrušit vše',
    },
    maxPlaceholderOpts: 8,
    selectAll: true,
    checkboxAutoFit: true,
    onOptionClick: function (originalSelectElementOrNull, optionCheckboxElement) {
      hideSelectAllIfSomethingChecked($(optionCheckboxElement).parents('.ms-options'))
      dispatchEventTagyVybrany()
    },
    onSelectAll: function (originalSelectElement) {
      hideSelectAllIfSomethingChecked(originalSelectElement.parentElement.querySelector('.ms-options'))
      dispatchEventTagyVybrany()
    }
  })
})
