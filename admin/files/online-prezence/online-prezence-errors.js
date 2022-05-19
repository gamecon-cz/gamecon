// ZOBRAZENI ERRORS
document.addEventListener('DOMContentLoaded', function () {
  Array.from(document.getElementsByClassName('aktivita')).forEach(function (aktivita) {
    aktivita.addEventListener('ajaxErrors', function (/** @param {{detail: {errors: string[], triggeringNode: HTMLElement}}} event */event) {
      /** @var {{detail: {errors: string[]|undefined}}} event */
      if (event.detail.errors) {
        const errorTemplate = aktivita.getElementsByClassName('error-template')[0]
        event.detail.errors.forEach(function (errorText) {
          // nad čím se error zobrazí
          const triggeringNode = event.detail.triggeringNode || errorTemplate
          const parentElement = triggeringNode.parentElement
          const errorTooltipNode = parentElement.getElementsByClassName('error-tooltip').item(0) // má potomka pro ten účel
            || (parentElement.classList.contains('error-tooltip') ? parentElement : null) // nebo je sám k tomu určený

          if (errorTooltipNode) {
            const errorHtml = `<div class="text-warning" role="alert">${errorText}</div>`
            zmenTooltip(errorHtml, errorTooltipNode) // zobrazíme error jako tooltip
            zobrazTooltip(errorTooltipNode, 2 /* na tolik sekund pokud nemá hover */)
          } else {
            const errorNode = errorTemplate.cloneNode(true)
            const errorTextNode = errorNode.getElementsByClassName('error-text')[0]
            errorTextNode.innerHTML = errorText
            parentElement.appendChild(errorNode) // zobrazíme error jako flash message
            errorNode.classList.remove('display-none')
          }
        })
      }
    })
  })
})
