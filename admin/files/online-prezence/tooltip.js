/**
 * @param {string} title
 * @param {HTMLElement} tooltipNode
 * @param {string} placement
 */
function zmenTooltip(title, tooltipNode, placement = '') {
  tooltipNode.title = title
  const tooltip = bootstrap.Tooltip.getOrCreateInstance(tooltipNode)
  tooltip.hide() // jinak zůstává na checkboxech viset
  tooltip._fixTitle() // sice jako private, ale Bootstap Tooltip nemá oficiální cestu jak změnit tooltip za běhu
}
