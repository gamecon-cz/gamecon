/**
 * @param {HTMLElement} aktivitaNode
 */
function upravUkazateleZaplnenostiAktivity(aktivitaNode) {
  const kapacita = Number.parseInt(aktivitaNode.dataset.kapacita)
  const zaskrtnuteCheckboxy = aktivitaNode.querySelectorAll('.styl-pro-dorazil-checkbox > input[type=checkbox]:checked')
  const pocetPritomnych = zaskrtnuteCheckboxy.length
  const barvaZaplnenosti = tempToColor(pocetPritomnych, 1, kapacita + 1 /* poslední barva je fialová, my chceme po plnou aktivitu předposlední, červenou */, 'half')
  const {r, g, b} = barvaZaplnenosti
  const intenzitaBarvy = 0.1
  // zaškrtnuté checkboxy dostanou barvu od zelené po fialovou, jak se bued blížit vyčerpání kapacity
  Array.from(zaskrtnuteCheckboxy).forEach(function (checkbox) {
    const stylNode = checkbox.parentElement
    stylNode.style.backgroundColor = `rgb(${r},${g},${b},${intenzitaBarvy})`
  })
  // nezaškrtnutým checkboxům zresetujeme barvy
  aktivitaNode.querySelectorAll('.styl-pro-dorazil-checkbox > input[type=checkbox]:not(:checked)').forEach(function (checkbox) {
    const stylNode = checkbox.parentElement
    stylNode.style.backgroundColor = 'inherit'
  })
  const jePlno = pocetPritomnych >= kapacita
  // tooltip se zbývající kapacitou
  const tooltipText = (jePlno ? 'Plno' : `Volno ${kapacita - pocetPritomnych}`) + ` (kapacita ${pocetPritomnych}/${kapacita})`
  const tooltipHtml = `<span class="${jePlno ? 'plno' : 'volno'}">${tooltipText}</span>`
  aktivitaNode.querySelectorAll('.styl-pro-dorazil-checkbox').forEach(function (stylProCheckboxNode) {
    zmenTooltip(tooltipHtml, stylProCheckboxNode)
  })
  Array.from(aktivitaNode.getElementsByClassName('omnibox')).forEach(function (omniboxElement) {
    zmenTooltip(tooltipHtml, omniboxElement)
    omniboxElement.placeholder = `${omniboxElement.dataset.vychoziPlaceholder} ${tooltipText.toLowerCase()}`
  })

  const ucastnici = aktivitaNode.querySelectorAll('.ucastnik')
  const prihlaseni = Array.from(ucastnici).filter((ucastnik) => jeToUcastnikPodleStavu(ucastnik.dataset.stavPrihlaseni))
  const pocetPrihlasenychCisloNode = aktivitaNode.querySelector('.pocet-prihlasenych-cislo')
  pocetPrihlasenychCisloNode.textContent = prihlaseni.length
}

/**
 * viz \Gamecon\Aktivita\ZmenaPrihlaseni::stavPrihlaseniProJs
 * @param {string} stavPrihlaseni
 * @return {boolean}
 */
function jeToUcastnikPodleStavu(stavPrihlaseni) {
  switch (stavPrihlaseni) {
    case 'ucastnik_se_odhlasil' :
    case 'ucastnik_nedorazil' :
    case 'nahradnik_nedorazil' :
    case 'ucastnik_se_prihlasil' :
    case 'ucastnik_dorazil' :
    case 'nahradnik_dorazil' :
      return true
    default :
      return false
  }
}

document.addEventListener('aktivitaVyrenderovana', function (event) {
  const aktivitaNode = event.detail
  upravUkazateleZaplnenostiAktivity(aktivitaNode)
})
