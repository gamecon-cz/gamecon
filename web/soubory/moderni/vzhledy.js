/*
 * Rozcestník variant moderního vzhledu (web/moduly/vzhledy.php): rám má šířku počítače nebo mobilu a zmenší se
 * na šířku sloupce, přepínač šířky mezi nimi přepíná. Bez JS rám vyplní okno a titulka se v něm vykreslí na jeho šířku.
 */
(function () {
  'use strict'

  var sirka = 1440

  function prepocitej() {
    document.querySelectorAll('.mw-vzhledy-okno').forEach(function (okno) {
      var ram = okno.querySelector('iframe')
      var meritko = Math.min(1, okno.clientWidth / sirka)
      ram.style.left = '50%'
      ram.style.width = sirka + 'px'
      ram.style.height = (okno.clientHeight / meritko) + 'px'
      ram.style.transform = 'translateX(-50%) scale(' + meritko + ')'
    })
  }

  document.querySelectorAll('[data-mw-vzhledy-sirka]').forEach(function (tlacitko) {
    tlacitko.addEventListener('click', function () {
      sirka = Number(tlacitko.getAttribute('data-mw-vzhledy-sirka'))
      document.body.classList.toggle('mw-vzhledy-mobil', sirka < 800)
      document.querySelectorAll('[data-mw-vzhledy-sirka]').forEach(function (jine) {
        jine.setAttribute('aria-pressed', String(jine === tlacitko))
      })
      prepocitej()
    })
  })
  window.addEventListener('resize', prepocitej)
  prepocitej()
})()
