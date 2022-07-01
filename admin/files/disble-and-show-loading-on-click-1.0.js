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
            document.body.style.cursor = originalBodyCursor
            element.style.cursor = originalElementCursor
            element.disabled = false
          }, 10000) // něco se pokazilo, už to trvá déle než deset sekund...
        })
      }
    })
  })
}
