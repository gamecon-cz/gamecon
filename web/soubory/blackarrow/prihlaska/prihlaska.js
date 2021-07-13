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
  const xhr = new XMLHttpRequest()

  xhr.open("POST", window.location.href, true)
  // xhr.setRequestHeader("Content-type", "multipart/form-data")

  xhr.onreadystatechange = function () {
    xhr.onreadystatechange = function () {
      // In local files, status is 0 upon success in Mozilla Firefox
      if (xhr.readyState === XMLHttpRequest.DONE) {
        const status = xhr.status
        if (status === 0 || (status >= 200 && status < 400)) {
          const responseText = xhr.responseText
          if (responseText) {
            const responseJson = JSON.parse(responseText)
            if (responseJson.covidSekce) {
              const covidSekceElement = document.getElementById('covidSekce')
              if (covidSekceElement) {
                covidSekceElement.outerHTML = responseJson.covidSekce
                pripravSeNaNahravaniPotvrzeniProtiCovidu()
                const pridatPotvrzeniProtiCoviduInputy = document.getElementsByName('pridatPotvrzeniProtiCovidu')
                pridatPotvrzeniProtiCoviduInputy.forEach(function (pridatPotvrzeniProtiCoviduInput) {
                  pridatPotvrzeniProtiCoviduInput.style.display = 'none'
                })
              }
            }
          }
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

function smazCovidPotvrzeni(url) {
  const xhr = new XMLHttpRequest()

  xhr.open("GET", url + '&ajax=1', true)

  xhr.onreadystatechange = function () {
    xhr.onreadystatechange = function () {
      // In local files, status is 0 upon success in Mozilla Firefox
      if (xhr.readyState === XMLHttpRequest.DONE) {
        const status = xhr.status
        if (status === 0 || (status >= 200 && status < 400)) {
          const responseText = xhr.responseText
          if (responseText) {
            const responseJson = JSON.parse(responseText)
            if (responseJson.covidSekce) {
              const covidSekceElement = document.getElementById('covidSekce')
              if (covidSekceElement) {
                covidSekceElement.outerHTML = responseJson.covidSekce
                pripravSeNaNahravaniPotvrzeniProtiCovidu()
                const pridatPotvrzeniProtiCoviduInputy = document.getElementsByName('pridatPotvrzeniProtiCovidu')
                pridatPotvrzeniProtiCoviduInputy.forEach(function (pridatPotvrzeniProtiCoviduInput) {
                  pridatPotvrzeniProtiCoviduInput.style.display = 'inherit'
                })
              }
            }
          }
        } else {
          // nekdy jindy
        }
      }
    }
  }

  xhr.send()

  return true
}
