document.addEventListener('DOMContentLoaded', function () {
  pripravSeNaNahravaniPotvrzeniProtiCovidu()
})

function pripravSeNaNahravaniPotvrzeniProtiCovidu() {
  const potvrzeniProtiCoviduElement = document.getElementById('potvrzeniProtiCovidu')
  if (potvrzeniProtiCoviduElement) {
    potvrzeniProtiCoviduElement.addEventListener("change", nahrajCovidPotvrzeni)
  }
}

function nahrajCovidPotvrzeni(event) {
  pracujiNaZmeneCovidPotvrzeni()
  const xhr = new XMLHttpRequest()

  xhr.open("POST", window.location.href, true)
  // xhr.setRequestHeader("Content-type", "multipart/form-data")

  xhr.onreadystatechange = function () {
    xhr.onreadystatechange = function () {
      // In local files, status is 0 upon success in Mozilla Firefox
      if (xhr.readyState === XMLHttpRequest.DONE) {
        const status = xhr.status
        if (status === 0 || (status >= 200 && status < 400)) {
          aktualizujCovidSekci(xhr.responseText, false)
        } else {
          // nekdy jindy
        }
      }
    }
  }

  const file = event.target.files[0]
  const formData = new FormData()
  formData.append('ajax', '1')
  formData.append('pridatPotvrzeniProtiCovidu', '1')
  formData.append("potvrzeniProtiCovidu", file)
  xhr.send(formData)
}

function pracujiNaZmeneCovidPotvrzeni() {
  const placeholderyProIndikatorZmenyCovidPotrvzeni = document.getElementsByClassName('placeholderProIndikatorZmenyCovidPotrvzeni')
  Array.from(placeholderyProIndikatorZmenyCovidPotrvzeni).forEach((placeholder) => placeholder.outerHTML = '<img src="soubory/blackarrow/prihlaska/ajax-loader.gif" alt="Loading" style="vertical-align: middle">')
}

function aktualizujCovidSekci(responseText, zobrazTlacitko) {
  if (!responseText) {
    return
  }
  const responseJson = JSON.parse(responseText)
  if (!responseJson.covidSekce) {
    return
  }
  const covidSekceElement = document.getElementById('covidSekce')
  if (!covidSekceElement) {
    return
  }
  covidSekceElement.outerHTML = responseJson.covidSekce
  pripravSeNaNahravaniPotvrzeniProtiCovidu()
  const pridatPotvrzeniProtiCoviduInputy = document.getElementsByName('pridatPotvrzeniProtiCovidu')
  pridatPotvrzeniProtiCoviduInputy.forEach(function (pridatPotvrzeniProtiCoviduInput) {
    pridatPotvrzeniProtiCoviduInput.style.display = zobrazTlacitko ? 'inherit' : 'none'
  })
}

function smazCovidPotvrzeni(url) {
  pracujiNaZmeneCovidPotvrzeni()
  const xhr = new XMLHttpRequest()

  xhr.open("GET", url + '&ajax=1', true)

  xhr.onreadystatechange = function () {
    xhr.onreadystatechange = function () {
      // In local files, status is 0 upon success in Mozilla Firefox
      if (xhr.readyState === XMLHttpRequest.DONE) {
        const status = xhr.status
        if (status === 0 || (status >= 200 && status < 400)) {
          aktualizujCovidSekci(xhr.responseText, true)
        } else {
          // nekdy jindy
        }
      }
    }
  }

  xhr.send()

  return true
}
