document.addEventListener('DOMContentLoaded', function () {
  const onlinePrezence = document.getElementById('online-prezence')
  onlinePrezence.addEventListener('probihajiZmeny', function (/** @param {{detail: probihaji: boolean}} */event) {
    onlinePrezence.dataset.probihajiZmeny = event.detail.probihaji
  })
})
