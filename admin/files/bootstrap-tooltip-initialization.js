window.addEventListener('load', (event) => {
  const tooltipsNodeList = document.querySelectorAll('[data-bs-toggle="tooltip"]')
  const tooltipsArray = Array.prototype.slice.call(tooltipsNodeList)
  tooltipsArray.map(function (tooltipTriggerElement) {
    return new bootstrap.Tooltip(tooltipTriggerElement)
  })
})
