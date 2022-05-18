document.addEventListener('DOMContentLoaded', function () {
  Array.from(document.getElementsByClassName('collapse')).forEach(function (collapseNode) {
    // https://getbootstrap.com/docs/5.0/components/collapse/
    collapseNode.addEventListener('shown.bs.collapse', function () {
      document.cookie = `collapse-${collapseNode.id}=shown;max-age=31536000`
      const buttonNode = document.getElementById(this.dataset.triggerButtonId)
      zvyrazniTlacitkoPokudJeNavodSkryty(buttonNode, false)
    })
    collapseNode.addEventListener('hidden.bs.collapse', function (event) {
      document.cookie = `collapse-${collapseNode.id}=hidden;max-age=31536000`
      const buttonNode = document.getElementById(this.dataset.triggerButtonId)
      zvyrazniTlacitkoPokudJeNavodSkryty(buttonNode, true)
    })
  })
})

/**
 * @param {HTMLElement} buttonNode
 * @param {HTMLElement} collapseNode
 */
function zobrazNavodPodlePosledniVolby(buttonNode, collapseNode) {
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
    zvyrazniTlacitkoPokudJeNavodSkryty(buttonNode, true)
  } else {
    buttonNode.classList.remove('collapsed')
    buttonNode.setAttribute('aria-expanded', 'true')
    collapseNode.classList.add('show')
    zvyrazniTlacitkoPokudJeNavodSkryty(buttonNode, false)
  }
}

/**
 *
 * @param {HTMLElement} buttonNode
 * @param {boolean} skryty
 */
function zvyrazniTlacitkoPokudJeNavodSkryty(buttonNode, skryty) {
  if (skryty) {
    buttonNode.classList.add('border', 'border-warning')
    const navodElement = buttonNode.parentElement
    navodElement.title = 'Zobazit návod'
    const staryTooltipOnElement = bootstrap.Tooltip.getOrCreateInstance(navodElement)
    staryTooltipOnElement.hide()
    staryTooltipOnElement.dispose()
    bootstrap.Tooltip.getOrCreateInstance(navodElement).update()
  } else {
    buttonNode.classList.remove('border', 'border-warning')
    const navodElement = buttonNode.parentElement
    navodElement.title = 'Skrýt návod'
    const staryTooltipOnElement = bootstrap.Tooltip.getOrCreateInstance(navodElement)
    staryTooltipOnElement.hide()
    staryTooltipOnElement.dispose()
    bootstrap.Tooltip.getOrCreateInstance(navodElement).update()
  }
}
