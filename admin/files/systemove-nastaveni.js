document.addEventListener('DOMContentLoaded', function () {
  const nastaveni = new SystemoveNastaveni(document.getElementById('nastaveni').dataset.ajaxKlic)

  const inputNodes = document.getElementsByClassName('hodnota-nastaveni')
  Array.from(inputNodes).forEach(/** @param {HTMLElement} inputNode */function (inputNode) {
    nastaveni.nastavPosledniUlozenouHodnotu(inputNode)
    nastaveni.odesilejPriZmnene(inputNode)
  })
})

class SystemoveNastaveni {

  /**
   * {string}
   * @private
   */
  _postUrl

  /**
   * @param {string} ajaxKlic
   */
  constructor(ajaxKlic) {
    const url = new URL(window.location.href)
    url.searchParams.set(ajaxKlic, '1')
    this._postUrl = url.toString()
  }

  /**
   * @param {HTMLElement} inputNode
   */
  nastavPosledniUlozenouHodnotu(inputNode) {
    inputNode.dataset.lastSavedValue = inputNode.value
  }

  /**
   * @param {HTMLElement} inputNode
   */
  odesilejPriZmnene(inputNode) {
    const nastaveni = this
    const ulozNastaveni = function () {
      inputNode = this // protože funkce bude volána v kontextu eventu na inputNode
      nastaveni.ulozNastaveni(
        inputNode,
        () => nastaveni.zrusPredchoziPotvrzeniUlozeni(inputNode),
        function (ulozenaData) {
          nastaveni.zobrazZmeny(ulozenaData)
          nastaveni.oznamUlozeni(inputNode)
        },
        () => nastaveni.oznamChybu(inputNode),
      )
    }
    inputNode.addEventListener('keyup', ulozNastaveni)
    inputNode.addEventListener('change', ulozNastaveni)
  }

  /**
   * @param {HTMLElement} inputNode
   * @param {function} callableOnStart
   * @param {function} callableOnSuccess
   * @param {function} callableOnFailure
   */
  ulozNastaveni(inputNode, callableOnStart, callableOnSuccess, callableOnFailure) {
    callableOnStart()

    const nastaveni = this
    const currentTimeoutId = setTimeout(function () {
      // byla tohle poslední zmněna za posledních 150 milisekund?
      if (inputNode.dataset.lastTimeoutId === currentTimeoutId.toString()) {
        delete inputNode.dataset.lastTimeoutId
        nastaveni.odesliNastaveni(
          inputNode,
          callableOnSuccess /* ukládání je asynchronní - skutečný konec zná až tahle funkce */,
          callableOnFailure,
        )
      }
    }, 150)
    inputNode.dataset.lastTimeoutId = currentTimeoutId.toString()
  }

  /**
   * @param {HTMLElement} inputNode
   * @param {function} callableOnSuccess
   * @param {function} callableOnFailure
   */
  odesliNastaveni(inputNode, callableOnSuccess, callableOnFailure) {
    if (this.jeHodnotaBezeZmeny(inputNode)) {
      return // tahle data už byla odeslána
    }
    this.nastavPosledniUlozenouHodnotu(inputNode)

    const request = new XMLHttpRequest()
    request.addEventListener('loadend', function () {
      if (request.status >= 200 && request.status < 300) {
        callableOnSuccess(JSON.parse(request.responseText))
      } else {
        callableOnFailure()
      }
    })

    const formData = new FormData
    formData.set(inputNode.name, inputNode.value)
    request.open('POST', this._postUrl)
    request.send(formData)
  }

  /**
   * @param {HTMLElement} inputNode
   * @return {boolean}
   */
  jeHodnotaBezeZmeny(inputNode) {
    return inputNode.dataset.lastSavedValue === inputNode.value
  }

  /**
   * @param {HTMLElement} element
   */
  zrusPredchoziPotvrzeniUlozeni(element) {
    const vysledekNodes = element.parentElement.getElementsByClassName('vysledek')
    Array.from(vysledekNodes).forEach(function (savedNode) {
      savedNode.remove()
    })
  }

  /**
   * @param {
   * 	{
   * 		"klic": string,
   * 		"hodnota": string,
   * 		"datovy_typ": string,
   * 		"nazev": string,
   * 		"popis": string,
   * 		"kdy": string,
   * 		"id_uzivatele": string,
   * 		"posledniZmena": string,
   * 		"zmenil": string,
   * 		"inputType": string
   * 	}
   * } novaData
   */
  zobrazZmeny(novaData) {
    const posledniZmenaElement = document.getElementById(`posledni-zmena-${novaData.klic}`)
    posledniZmenaElement.innerHTML = novaData.posledniZmena

    const zmenilElement = document.getElementById(`zmenil-${novaData.klic}`)
    zmenilElement.innerHTML = novaData.zmenil

    const popisElement = document.getElementById(`popis-${novaData.klic}`)
    popisElement.innerHTML = novaData.popis
  }

  /**
   * @param {HTMLElement} element
   */
  oznamUlozeni(element) {
    this.oznamVysledek(element, '✔️' /* to nekonci mezerou, to je soucast smajliku, nemazat ji */, 'Změny jsou uloženy')
  }

  /**
   * @param {HTMLElement} element
   */
  oznamChybu(element) {
    this.oznamVysledek(element, '❗', 'Změny se nepodařilo uložit')
  }

  /**
   * @param {HTMLElement} element
   * @param {string} text
   * @param {string} hint
   */
  oznamVysledek(element, text, hint) {
    const parent = element.parentNode
    /** https://developer.mozilla.org/en-US/docs/Web/HTML/Element/template */
    const vysledek = document.createElement('template')
    vysledek.innerHTML = '<span class="vysledek hinted" style="position: absolute">' + text + '<span class="hint">' + hint + '</span></span>'
    parent.appendChild(vysledek.content)
  }
}
