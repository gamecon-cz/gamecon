function prekresliPocetVybranychUctu() {
  var promlcetSubmitElement = document.querySelector('input[type=submit][name=promlcet]')
  if (promlcetSubmitElement) {
    var pocetVybranych = document.querySelectorAll('.vybrany-uzivatel:checked:not(:disabled)').length
    var predchoziText = promlcetSubmitElement.value
    promlcetSubmitElement.value = predchoziText.replace(/[0-9]+/, pocetVybranych)
    promlcetSubmitElement.disabled = pocetVybranych === 0
      ? true
      : ''
  }
}

Array.from(document.getElementsByClassName('vybrany-uzivatel')).forEach(function () {
  this.addEventListener('change', prekresliPocetVybranychUctu)
})
