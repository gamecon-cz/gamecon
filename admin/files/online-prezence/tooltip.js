/**
 * @param {string} title
 * @param {HTMLElement} tooltipNode
 */
function zmenTooltip(title, tooltipNode) {
  tooltipNode.title = title
  const staryTooltipOnElement = bootstrap.Tooltip.getInstance(tooltipNode)
  if (staryTooltipOnElement) {
    staryTooltipOnElement.dispose()
  }
  new bootstrap.Tooltip(tooltipNode)
}
