document.addEventListener('stitkyPripravene', function () {
  var $tagsMultiselect = $('#vyberStitkuProgram')
  var clientWidthProgramLegenda = document.querySelector('.program_legenda').clientWidth
  var tagsMultiselectColumns = parseInt(clientWidthProgramLegenda / 250)
  if (tagsMultiselectColumns < 1) {
    tagsMultiselectColumns = 1
  }


  $tagsMultiselect.multiselect({
    columns: tagsMultiselectColumns,
    search: true,
    texts: {
      'placeholder': '🏷 Vyber štítky',
      'search': '🔍 Hledej štítek',
      'selectedOptions': ' vybraných štítků',
      'selectAll': 'Vybrat vše',
      'unselectAll': 'Zrušit vše',
    },
    maxPlaceholderOpts: 8,
    selectAll: true,
    checkboxAutoFit: true,
    onOptionClick: function (originalSelectElementOrNull, optionCheckboxElement) {
      dispatchEventStitkyVybrany()
    },
    onSelectAll: function (originalSelectElement) {
      dispatchEventStitkyVybrany()
    }
  })

  function dispatchEventStitkyVybrany() {
    document.dispatchEvent(new Event('stitkyVybrany'))
  }

  document.addEventListener('stitkyVybrany', function () {
    hideSelectAllIfSomethingChecked()
  })

  function hideSelectAllIfSomethingChecked() {
    var $msOptionsElement = $('.ms-options')
    var somethingChecked = $msOptionsElement.find('ul').find('input[type=checkbox]:checked').length > 0
    $msOptionsElement.find('.ms-selectall').css('visibility', somethingChecked ? '' : 'hidden')
  }

  var buttonElement = document.querySelector('.ms-options-wrap button')
  buttonElement.classList.add('aktivity_stitek-nahled')
  buttonElement.addEventListener('click', function() {
    document.querySelector('.ms-search input[type=text]').focus()
  })
})
