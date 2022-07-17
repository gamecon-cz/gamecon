// ZOBRAZENI ERRORS

import {AjaxErrors} from "./online-prezence-eventy.js"

document.addEventListener('DOMContentLoaded', function () {
  Array.from(document.getElementsByClassName('aktivita')).forEach(function (aktivita) {
    aktivita.addEventListener(AjaxErrors.eventName, function (
      /** @var {{detail: {errors: string[]|undefined, warnings: string[]|undefined, triggeringNode: HTMLElement|undefined}}} */event,
    ) {
      if (event.detail.errors) {
        const errorTemplate = aktivita.getElementsByClassName('error-template')[0]
        event.detail.errors.forEach(function (errorText) {
          showAjaxError(errorText, 'error', errorTemplate, event.detail.triggeringNode || errorTemplate)
        })
      }
      if (event.detail.warnings) {
        const errorTemplate = aktivita.getElementsByClassName('warning-template')[0]
        event.detail.warnings.forEach(function (warningText) {
          showAjaxError(warningText, 'warning', errorTemplate, event.detail.triggeringNode || errorTemplate)
        })
      }
    })
  })

  /**
   * @param {string} message
   * @param {string} errorLevel
   * @param {Element} errorTemplate
   * @param {Element} triggeringNode
   */
  function showAjaxError(message, errorLevel, errorTemplate, triggeringNode) {
    // nad čím se error zobrazí
    const parentElement = triggeringNode.parentElement
    const errorTooltipNode = parentElement.getElementsByClassName('error-tooltip').item(0) // má potomka pro ten účel
      || (parentElement.classList.contains('error-tooltip') ? parentElement : null) // nebo je sám k tomu určený

    if (errorTooltipNode) {
      const errorHtml = `<div class="${errorLevel === 'warning' ? 'text-warning' : 'text-danger'}" role="alert">${message}</div>`
      zmenTooltip(errorHtml, errorTooltipNode) // zobrazíme error jako tooltip
      zobrazTooltip(errorTooltipNode, 4 /* na tolik sekund pokud nemá hover */)
    } else {
      const errorNode = errorTemplate.cloneNode(true)
      const errorTextNode = errorNode.getElementsByClassName('message')[0]
      errorTextNode.innerHTML = message
      parentElement.appendChild(errorNode) // zobrazíme error jako flash message
      errorNode.classList.remove('display-none')
    }
  }
})
