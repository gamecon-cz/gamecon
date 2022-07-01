{
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.show-loading-on-click').forEach(function (element) {
      if ((element instanceof HTMLInputElement || element instanceof HTMLButtonElement) && element.type === 'submit') {
        element.addEventListener('click', function () {
          const originalBodyCursor = document.body.style.cursor
          const originalElementCursor = this.style.cursor
          document.body.style.cursor = 'wait'
          element.style.cursor = 'wait'

          setTimeout(function () {
            document.body.style.cursor = originalBodyCursor
            element.style.cursor = originalElementCursor
          }, 10000) // něco se pokazilo, už to trvá déle než deset sekund...
        })
        element.addEventListener('submit', function () {
          element.disabled = true
        })
      }
    })
  })
}
