/*
 * Varianta C moderního vzhledu (Ruka karet): do hlavičky titulky rozdá vějíř pěti karet linií z balíčku níž
 * na stránce. Bez JS hlavička vějíř nemá (c.css ji pak nechá jen s textem), karty jsou dál v balíčku.
 */
document.addEventListener('DOMContentLoaded', function () {
  'use strict'

  var ruka = document.querySelector('[data-mw-c-ruka]')
  if (!ruka) {
    return
  }
  var karty = Array.prototype.slice.call(document.querySelectorAll('.mw-c-balicek .mw-c-karta'))
  // linie s nejvýraznějšími fotkami, které chybí, doplní další karty z balíčku
  var oblibene = ['larpy', 'rpg', 'deskoherna', 'wargaming', 'bonusy']
  var vybrane = []
  oblibene.forEach(function (url) {
    karty.forEach(function (karta) {
      if (karta.getAttribute('href') === url) {
        vybrane.push(karta)
      }
    })
  })
  karty.forEach(function (karta) {
    if (vybrane.indexOf(karta) === -1) {
      vybrane.push(karta)
    }
  })
  vybrane.slice(0, 5).forEach(function (karta) {
    var kopie = karta.cloneNode(true)
    kopie.tabIndex = -1
    ruka.appendChild(kopie)
  })
})
