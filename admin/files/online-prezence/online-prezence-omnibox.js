class OnlinePrezenceOmnibox {

  /** @private */
  akceAktivity
  /** @private */
  jQuery

  /**
   * @param {AkceAktivity} akceAktivity
   * @param {jQuery} jQuery
   */
  constructor(akceAktivity, jQuery) {
    this.akceAktivity = akceAktivity
    this.jQuery = jQuery
  }

  inicializujOmnibox() {
    const $ = this.jQuery
    const omnibox = $('.online-prezence .omnibox')
    const akceAktivity = this.akceAktivity

    omnibox.on('autocompleteselect', function (event, ui) {
      const idAktivity = Number(this.dataset.idAktivity)
      const idUzivatele = Number(ui.item.value)
      const ucastniciAktivityNode = $(`#ucastniciAktivity${idAktivity}`)
      const novyUcastnik = $(ui.item.html)

      akceAktivity.zmenitPritomnostUcastnika(
        idUzivatele,
        idAktivity,
        novyUcastnik.find('input')[0],
        this /* kde vznikl požadavek a kde ukázat případné errory */,
        function () {
          /**
           * Teprve až backend potvrdí uložení vybraného účastníka, tak můžeme přidat řádek s tímto účastníkem.
           */
          ucastniciAktivityNode.append(novyUcastnik)
          akceAktivity.hlidejZmenyMetadatUcastnika(akceAktivity.dejNodeUcastnika(idUzivatele, idAktivity))
        },
        function () {
          /**
           * Přidáme řádek účastníka a JS přidá čas poslední změny a stav přihlášení, tak můžeme vypustit event o upečené novince.
           * Data z řádku totiž potřebujeme pro kontrolu změn v online-prezence-posledni-zname-zmeny-prihlaseni.js
           */
          akceAktivity.vypustEventONovemUcastnikovi(idUzivatele, idAktivity)
        },
      )

      // vyrušení default výběru do boxu
      event.preventDefault()
      $(this).val('')

      // skrytí výchozí ošklivé hlášky
      $('.ui-helper-hidden-accessible').addClass('display-none')
    })

    omnibox.on('autocompleteresponse', function (event, ui) {
      const idAktivity = this.dataset.idAktivity
      $(`#omniboxHledam${idAktivity}`).addClass('display-none')
      if (!ui || ui.content === undefined || ui.content.length === 0) {
        $(`#omniboxNicNenalezeno${idAktivity}`).removeClass('display-none')
      } else {
        $(`#omniboxNicNenalezeno${idAktivity}`).addClass('display-none')
      }
    })

    omnibox.on('input', function () {
      const idAktivity = this.dataset.idAktivity
      $(`#omniboxNicNenalezeno${idAktivity}`).addClass('display-none')
      const minLength = this.dataset.omniboxMinLength
      const length = this.value.length
      if (minLength <= length) {
        $(`#omniboxHledam${idAktivity}`).removeClass('display-none')
      }
    })
  }
}

export {OnlinePrezenceOmnibox}
