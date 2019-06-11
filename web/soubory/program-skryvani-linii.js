/**
 * @param programy jquery element set s tabulkami programu (tj. víc tabulek,
 *  jedna per den).
 * @param ovladani jquery element set s divem, kam se nagenerují ovládací
 *  tlačítka pro skrývání linií.
 */
function programSkryvaniLinii(programy, ovladani) {
  var linie = {}

  // načíst názvy linií
  programy.find('> tbody > tr > td:first-child[rowspan]').each(function() {
    var nazev = $(this).text().trim()
    var radek = $(this).parent()
    if (nazev) {
      if (nazev in linie) {
        linie[nazev] = linie[nazev].add(radek)
      } else {
        linie[nazev] = radek
      }

      // je potřeba připočítat (rowspan - 1) dalších řádků
      var rows = Number($(this).attr('rowspan'))
      for (var i = 0; i < rows - 1; i++) {
        linie[nazev] = linie[nazev].add(linie[nazev].last().next())
      }
    }
  })

  // vytvořit tlačítka
  for (var i in linie) {
    // obalovací fce pro zachování kontextu proměnných
    ;(function() {

      // vytvořit tlačítko
      var polozka = $('<label><input type="checkbox">' + i + '</label>')
      var checkbox = polozka.find('input')
      ovladani.append(polozka)

      // nabindovat akci
      var nazev = i
      var ls = window.localStorage
      var lsNazev = 'program-' + nazev
      var radky = linie[i]
      checkbox.change(function () {
        var checked = $(this).prop('checked')
        radky.css('display', checked ? '' : 'none')
        polozka.toggleClass('aktivni', checked)
        ls.setItem(lsNazev, checked ? '1' : '')
      })

      // aktualizovat tlačítka z local storage
      var lsStav = ls.getItem(lsNazev)
      if (lsStav !== null) {
        checkbox.prop('checked', lsStav)
      } else {
        checkbox.prop('checked', true) // default, pokud v ls není
      }
      checkbox.change()
    })()
  }
}
