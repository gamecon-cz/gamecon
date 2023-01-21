function odscrollujElementyDoprava(elements) {
  Array.from(elements).forEach(element => {
    odscrollujDoprava(element)
  })
}

function odscrollujDoprava(element) {
  element.scrollLeft = element.scrollWidth
}
