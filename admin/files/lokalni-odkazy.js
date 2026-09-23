document.addEventListener('DOMContentLoaded', function () {
  const zablikejCilKotvy = function (hash) {
    const cil = document.getElementById(hash.replace('#', ''))
    if (cil) {
      zablikej(cil)
    }
  }

  if (window.location.hash) {
    zablikejCilKotvy(window.location.hash)
  }

  Array.from(document.getElementsByClassName('lokalni-odkaz')).forEach(function (odkaz) {
    odkaz.addEventListener('click', function (event) {
      event.preventDefault()
      window.location.hash = odkaz.hash
      zablikejCilKotvy(odkaz.hash)
    })
  })
})
