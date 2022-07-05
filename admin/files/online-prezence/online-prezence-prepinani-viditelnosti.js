document.addEventListener('DOMContentLoaded', function () {
  Array.from(document.getElementsByClassName('collapse')).forEach(function (collapseNode) {
    // https://getbootstrap.com/docs/5.0/components/collapse/
    collapseNode.addEventListener('shown.bs.collapse', function () {
      document.cookie = `collapse-${collapseNode.id}=shown;max-age=31536000`
      const buttonNode = document.getElementById(this.dataset.triggerButtonId)
      zvyrazniTlacitkoPokudJeObsahSkryty(buttonNode, false)
      prohodObsahyDleZobrazeni(buttonNode, false)
    })
    collapseNode.addEventListener('hidden.bs.collapse', function (event) {
      document.cookie = `collapse-${collapseNode.id}=hidden;max-age=31536000`
      const buttonNode = document.getElementById(this.dataset.triggerButtonId)
      zvyrazniTlacitkoPokudJeObsahSkryty(buttonNode, true)
      prohodObsahyDleZobrazeni(buttonNode, true)
    })
  })
})

function zobrazElementyPodlePosledniVolby() {
  Array.from(document.getElementsByClassName('collapse')).forEach(function (collapseNode) {
    const buttonNode = document.getElementById(collapseNode.dataset.triggerButtonId)
    zobrazElementPodlePosledniVolby(buttonNode, collapseNode)
  })
}

/**
 * @param {HTMLElement} buttonNode
 * @param {HTMLElement} collapseNode
 */
function zobrazElementPodlePosledniVolby(buttonNode, collapseNode) {
  let collapseState
  const collapseCookieState = document.cookie.split('; ')
    .find(row => row.startsWith(`collapse-${collapseNode.id}`))
  if (collapseCookieState) {
    collapseState = collapseCookieState.split('=')[1]
  }
  if (collapseState === 'hidden') {
    buttonNode.classList.add('collapsed')
    buttonNode.setAttribute('aria-expanded', 'false')
    collapseNode.classList.remove('show')
    zvyrazniTlacitkoPokudJeObsahSkryty(buttonNode, true)
    prohodObsahyDleZobrazeni(buttonNode, true)
  } else {
    buttonNode.classList.remove('collapsed')
    buttonNode.setAttribute('aria-expanded', 'true')
    collapseNode.classList.add('show')
    zvyrazniTlacitkoPokudJeObsahSkryty(buttonNode, false)
    prohodObsahyDleZobrazeni(buttonNode, false)
  }
}

/**
 *
 * @param {HTMLElement} buttonNode
 * @param {boolean} skryty
 */
function zvyrazniTlacitkoPokudJeObsahSkryty(buttonNode, skryty) {
  let novyTitle
  if (skryty) {
    buttonNode.classList.add('border', 'border-warning')
    novyTitle = buttonNode.dataset.titleProZobrazeni
  } else {
    buttonNode.classList.remove('border', 'border-warning')
    novyTitle = buttonNode.dataset.titleProSkryti
  }
  if (novyTitle) {
    const navodElement = buttonNode.parentElement
    zmenTooltip(novyTitle, navodElement)
  }
}

/**
 *
 * @param {HTMLElement} buttonNode
 * @param {boolean} skryte
 */
function prohodObsahyDleZobrazeni(buttonNode, skryte) {
  Array.from(buttonNode.getElementsByClassName('show-when-collapsed')).forEach(function (showWhenCollapsed) {
    if (skryte) {
      showWhenCollapsed.classList.remove('display-none')
    } else {
      showWhenCollapsed.classList.add('display-none')
    }
  })

  Array.from(buttonNode.getElementsByClassName('show-when-expanded')).forEach(function (showWhenExpanded) {
    if (skryte) {
      showWhenExpanded.classList.add('display-none')
    } else {
      showWhenExpanded.classList.remove('display-none')
    }
  })
}
