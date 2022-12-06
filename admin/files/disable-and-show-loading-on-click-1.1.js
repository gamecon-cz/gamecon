{
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.disable-and-show-loading-on-click').forEach(function (element) {
      if ((element instanceof HTMLInputElement || element instanceof HTMLButtonElement) && element.type === 'submit') {
        element.addEventListener('click', function () {
          const originalBodyCursor = document.body.style.cursor
          const originalElementCursor = this.style.cursor
          document.body.style.cursor = 'wait'
          element.style.cursor = 'wait'

          setTimeout(function () {
            element.disabled = true
          }, 1) // malý trik aby zablokování tlačítka proběhlo až v dalším "tiku" a tím se nezablokoval samotný submit

          setTimeout(function () {
            unblockAndRemoveLoading(element, originalBodyCursor, originalElementCursor)
          }, 10000) // něco se pokazilo, už to trvá déle než deset sekund...
        })
      }
    })
  })
}

/**
 * @param {HTMLInputElement|HTMLButtonElement} element
 * @param {string} originalBodyCursor
 * @param {string} originalElementCursor
 */
function unblockAndRemoveLoading(element, originalBodyCursor = 'inherit', originalElementCursor = 'inherit') {
  document.body.style.cursor = originalBodyCursor
  element.style.cursor = originalElementCursor
  delete element.disabled
  return true
}
