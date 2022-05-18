document.addEventListener('DOMContentLoaded', function () {
  Array.from(document.getElementsByClassName('collapse')).forEach(function (collapseNode) {
    // https://getbootstrap.com/docs/5.0/components/collapse/
    collapseNode.addEventListener('shown.bs.collapse', function () {
      document.cookie = `collapse-${collapseNode.id}=shown;max-age=31536000`
    })
    collapseNode.addEventListener('hidden.bs.collapse', function () {
      document.cookie = `collapse-${collapseNode.id}=hidden;max-age=31536000`
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
  } else {
    buttonNode.classList.remove('collapsed')
    buttonNode.setAttribute('aria-expanded', 'true')
    collapseNode.classList.add('show')
  }
}
