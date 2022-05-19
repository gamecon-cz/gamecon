/**
 * @param {string} title
 * @param {HTMLElement} tooltipNode
 */
function zmenTooltip(title, tooltipNode) {
  tooltipNode.title = title
  const tooltipInstance = bootstrap.Tooltip.getOrCreateInstance(tooltipNode)
  const focusout = new Event('focusout')
  tooltipNode.dispatchEvent(focusout) // jinak se nezobrazí, pokud byl kurzor mimo a vrátí se
  tooltipInstance._fixTitle() // sice jakoby private, ale Bootstap Tooltip nemá oficiální cestu jak změnit tooltip za běhu
  zobrazTooltip(tooltipNode)
}

/**
 * @param {HTMLElement} tooltipNode
 * @param {number} zobrazNaSekundPokudNemaHover
 */
function zobrazTooltip(tooltipNode, zobrazNaSekundPokudNemaHover = 0) {
  const tooltipInstance = bootstrap.Tooltip.getOrCreateInstance(tooltipNode)
  const focusout = new Event('focusout')
  tooltipNode.dispatchEvent(focusout) // jinak se nezobrazí, pokud byl kurzor mimo a vrátí se
  if (tooltipNode.matches(':hover')) { // pokud je kurzor už mimo tooltip trigger element, tak by se tooltip zobrazil, ale už neskryl (asi chyba v Tooltip)
    tooltipInstance.show()
  } else {
    if (zobrazNaSekundPokudNemaHover > 0) {
      const focusin = new Event('focusin')
      tooltipNode.dispatchEvent(focusin)
      setTimeout(function () {
        const focusout = new Event('focusout')
        tooltipNode.dispatchEvent(focusout) // nevím proč, ale naštěstí i když tohle doběhne a opět má tooltip trigger element hover, tak to ani neproblikne a tooltip se zobrazuje dál
      }, zobrazNaSekundPokudNemaHover * 1000)
    }
  }
}
