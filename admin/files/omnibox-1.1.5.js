const initializeOmnibox = function ($) {
  const $omnibox = $('.omnibox')
  let posledniDotazSelhal = false
  /**
   * @param {{term: string}} request
   * @param {function} response
   * @return {{}}
   */
  getSource = function (request, response) {
    const data = {term: request.term}
    const input = $omnibox.filter(function (index, input) {
      return input.value === request.term
    })[0]
    const encodedParameters = input.dataset.omnibox || '{}'
    const parameters = JSON.parse(encodedParameters)
    for (const [key, value] of Object.entries(parameters)) {
      data[key] = value
    }
    const omniboxUrl = input.dataset.omniboxUrl || 'ajax-omnibox'
    const method = input.dataset.omniboxMethod || 'get'
    let responseData
    $[method]({
      url: omniboxUrl,
      data: data,
      success: function (omniboxResponseData) {
        responseData = omniboxResponseData
      },
      dataType: 'json',
      async: false,
    })
    // bez úspěchu (server selhal, nebo vrátil přihlašovací stránku po odhlášení) zůstane responseData nedefinované
    posledniDotazSelhal = responseData === undefined
    response(posledniDotazSelhal ? [] : responseData)
  }
  // Našeptávátko pro omnibox
  /** https://api.jqueryui.com/autocomplete/ */
  $omnibox.autocomplete({
    source: getSource, // schválně jen odkaz na funkci, nikoli jeji výsledek
    minLength: $omnibox.data('omnibox-min-length') || 2,
    autoFocus: true, // automatický výběr první hodnoty, aby uživatel mohl zmáčknout rovnou enter
    focus: function (event, ui) {
      event.preventDefault() // neměnit text inputu při výběru
    },
    select: function (event, ui) {
      // automatické odeslání, pokud je nastaveno
      const $this = $(this)
      if ($this.hasClass('autosubmit')) {
        const $form = $this.closest('form')
        if ($form.length > 0) {
          $this.val(ui.item.value) // nutno nastavit před submitem
          $form.submit()
        } else {

        }
      }
    },
    search: function (event, ui) {
      if (this.parentNode.getElementsByClassName('cekame').length === 0) {
        const cekameElement = document.createElement('span')
        cekameElement.classList.add('cekame')
        cekameElement.classList.add('remove-on-omnibox-change')
        cekameElement.title = 'Čekáme na výsledek'
        this.parentNode.insertBefore(cekameElement, this.nextElementSibling)
      }
    },
    response: function (event, ui) {
      Array.from(this.parentNode.getElementsByClassName('cekame'))
        .forEach((element) => element.remove())
      if (ui.content === undefined) {
        throw Error('Chybná odpověď pro Omnibox, chybí ui.content');
      }
      if (!Array.isArray(ui.content)) {
        throw Error('Chybný formát odpovědi pro Omnibox, ui.content má být Array: ' + JSON.stringify(event.content));
      }
      if (ui.content.length === 0) {
        const trida = posledniDotazSelhal ? 'omnibox-selhalo' : 'nic-nenalezeno'
        if (this.parentNode.getElementsByClassName(trida).length === 0) {
          const znackaElement = document.createElement('span')
          znackaElement.classList.add(trida)
          znackaElement.classList.add('remove-on-omnibox-change')
          znackaElement.title = posledniDotazSelhal
            ? 'Našeptávání selhalo, obnov stránku (možná došlo k odhlášení)'
            : 'Nic nenalezeno'
          this.parentNode.insertBefore(znackaElement, this.nextElementSibling)
        }
      }
    }
  })

  $omnibox.on('keydown', function () {
    const $this = $(this)
    const $form = $this.closest('form')
    $form.find('.remove-on-omnibox-change').remove()
  })

  // Klávesové zkratky
  // ID z admin/templates/uzivatel.xtpl a admin/templates/moderni/uzivatel.xtpl
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
