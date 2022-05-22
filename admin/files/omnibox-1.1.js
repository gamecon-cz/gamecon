const initializeOmnibox = function ($) {
  const $omnibox = $('.omnibox')
  /**
   * @param {{term: string}} request
   * @param {function} response
   * @return {{}}
   */
  getSourceUrl = function (request, response) {
    const data = {term: request.term}
    const input = $omnibox.filter(function (index, input) {
      return input.value === request.term
    })[0]
    const encodedParameters = input.dataset.omnibox || '{}'
    const parameters = JSON.parse(encodedParameters)
    for (const [key, value] of Object.entries(parameters)) {
      data[key] = value
    }
    let responseData
    $.get({
      url: 'ajax-omnibox',
      data: data,
      success: function (omniboxResponseData) {
        responseData = omniboxResponseData
      },
      dataType: 'json',
      async: false,
    })
    response(responseData)
  }
  // Našeptávátko pro omnibox
  $omnibox.autocomplete({
    source: getSourceUrl,
    minLength: 2,
    autoFocus: true, // automatický výběr první hodnoty, aby uživatel mohl zmáčknout rovnou enter
    focus: function (event, ui) {
      event.preventDefault() // neměnit text inputu při výběru
    },
    select: function (event, ui) {
      // automatické odeslání, pokud je nastaveno
      $this = $(this)
      if ($this.hasClass('autosubmit')) {
        const $form = $this.closest('form')
        $this.val(ui.item.value) // nutno nastavit před submitem
        $form.submit()
      }
    },
  })

  // Klávesové zkratky
  $(document).on('keydown', null, 'alt+u', function () {
    $('#omniboxUzivateleProPraci').focus()
    return false
  }).on('keydown', null, 'alt+z', function () {
    $('#zrusitUzivateleProPraci').submit()
    return false
  })
};

(function ($) {
  $(document).ready(function () {
    initializeOmnibox($)
  })
})(jQuery)
