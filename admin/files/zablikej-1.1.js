/**
 * @param {HTMLElement} node
 * @param {string} color
 * @param {int} pocetBliknuti
 */
function zablikej(node, color = '#cdec94', pocetBliknuti = 4) {
  blikni(node, color)
  pocetBliknuti--

  const intervalBarvyId = setInterval(function () {
    if (pocetBliknuti <= 0) {
      clearInterval(intervalBarvyId)
      return
    }
    blikni(node, color)
    pocetBliknuti--
  }, 600)
}

/**
 * @param {HTMLElement} node
 * @param {string} color
 */
function blikni(node, color = '#cdec94') {
  vyradZmenuBarvyPriHover(node)
  zmenBarvuNa(node, color, 0.2)

  const intervalTransparentId = setTimeout(function () {
    zmenBarvuNa(
      node,
      /* kvůli zachování střídaní barvy u řádků tabulky, viz
      .main table tbody tr:nth-child(2n) {
        background-color: #f0f0f0;
      } */
      node.parentElement.style.backgroundColor,
      0.1,
    )
    vratZmenuBarvyPriHover(node)
    clearTimeout(intervalTransparentId)
  }, 300)
}

/**
 * @param {HTMLElement} node
 * @param {string} color
 * @param {number} seconds
 */
function zmenBarvuNa(node, color, seconds) {
  node.style.backgroundColor = color
  const transition = `background ${seconds}s linear`
  node.style.transition = transition
  node.style.webkitTransition = transition
}


/**
 * @param {HTMLElement} node
 */
function vyradZmenuBarvyPriHover(node) {
  node.classList.add('no-hover-style')
}

/**
 * @param {HTMLElement} node
 */
function vratZmenuBarvyPriHover(node) {
  node.classList.remove('no-hover-style')
}

document.addEventListener('DOMContentLoaded', function () {
  const zablikejCilKotvy = function (hash) {
    const id = hash.trim().replace('#', '')
    // klíče nastavení jsou velkými písmeny, ale sdílené odkazy na ně bývají přepsané malými
    const cil = document.getElementById(id) ?? document.getElementById(id.toUpperCase())
    if (cil) {
      zablikej(cil)
    }
  }

  if (window.location.hash) {
    zablikejCilKotvy(window.location.hash)
  }

  Array.from(document.getElementsByClassName('lokalni-odkaz')).forEach(function (odkaz) {
    odkaz.addEventListener('click', function (event) {
      event.preventDefault()
      window.location.hash = odkaz.hash
      zablikejCilKotvy(odkaz.hash)
    })
  })
})
