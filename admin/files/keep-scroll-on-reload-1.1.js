{ // local scope
  function scrollToPreviousPosition() {

    const scrollPosition = sessionStorage.getItem('scrollPosition')
    if (scrollPosition) {
      window.scrollTo(0, Number.parseInt(scrollPosition))
    }
  }

  document.addEventListener("DOMContentLoaded", function (event) {
    scrollToPreviousPosition()
  })

  document.addEventListener("contentReadyToScrollToPreviousPosition", function (event) {
    scrollToPreviousPosition()
  })

  window.addEventListener('beforeunload', function (e) {
    sessionStorage.setItem('scrollPosition', window.scrollY.toString())
  })
}
