/**
 * @param obalNahledu element, ve kterém je náhled
 * @param obalProgramu element, ve kterém je program (kvůli úpravě šířky při
 *  zobrazení náhledu)
 * @param odkazy NodeList odkazů na aktivity. Elementy musí mít nastavený data
 *  atribut program-nahled-id. Nejde o všechny odkazy, ale jen odkazy na
 *  aktivity.
 */
function programNahled(obalNahledu, obalProgramu, odkazy, odkazyZapamatovat) {

  class Nahled {
    constructor(obalNahledu, obalProgramu) {
      this.obalNahledu = obalNahledu
      this.obalProgramu = obalProgramu
      this.viditelny = false
      this.id = null

      let rozbaleny = window.localStorage.getItem('programNahled_rozbaleny')
      if (rozbaleny) {
        this.zobraz()
        window.localStorage.removeItem('programNahled_rozbaleny')

        let posledniId = window.localStorage.getItem('programNahled_posledniId')
        if (posledniId) {
          nactiId(posledniId)
        }
      }
    }

    zobraz() {
      this.obalProgramu.classList.add('programNahled_obalProgramu-zuzeny')
      this.obalNahledu.classList.add('programNahled_obalNahledu-viditelny')
      this.viditelny = true
    }

    skryj() {
      this.obalProgramu.classList.remove('programNahled_obalProgramu-zuzeny')
      this.obalNahledu.classList.remove('programNahled_obalNahledu-viditelny')
      this.viditelny = false
    }

    set data(data) {
      this.zobraz()

      this.obalNahledu.querySelector('.programNahled_nazev').innerHTML = data.nazev
      this.obalNahledu.querySelector('.programNahled_stitky').innerHTML = data.stitky.map(function(stitek) {
        return '<div class="programNahled_stitek">'+stitek+'</div>'
      }).join('')
      this.obalNahledu.querySelector('.programNahled_vypraveci').innerHTML = data.vypraveci.join(', ')
      this.obalNahledu.querySelector('.programNahled_kratkyPopis').innerHTML = data.kratkyPopis
      this.obalNahledu.querySelector('.programNahled_popis').innerHTML = data.popis
      this.obalNahledu.querySelector('.programNahled_obrazek').setAttribute('src', data.obrazek)
      this.obalNahledu.querySelector('.programNahled_obsazenost').innerHTML = data.obsazenost
      this.obalNahledu.querySelector('.programNahled_cena').innerHTML = data.cena
      this.obalNahledu.querySelector('.programNahled_cas').innerHTML = data.cas

      this.obalNahledu.classList.add('programNahled_obalNahledu-maData')

      this.obalNahledu.querySelector('.programNahled_text').scroll(0, 0)
    }
  }

  class Api {
    constructor() {
      this.cache = {}
    }

    nacti(id) {
      var api = this
      if (id in this.cache) {
        return new Promise(function(resolve) {
          resolve(api.cache[id])
        })
      } else {
        return ajax('program-nahled-api?idAktivity=' + id)
      }
    }
  }

  function ajax(url) {
    return new Promise(function(resolve) {
      var xhr = new XMLHttpRequest()
      xhr.open('GET', url)
      xhr.onload = function() {
        if (xhr.status == 200) {
          resolve(JSON.parse(xhr.response))
        }
      }
      xhr.send()
    })
  }

  var api = new Api()

  var nahled = new Nahled(
    obalNahledu,
    obalProgramu
  )

  var skryvac = obalNahledu.querySelector('.programNahled_zaviratko')

  function odkazKlik(odkaz) {
    var id = odkaz.getAttribute('data-program-nahled-id')
    nactiId(id)
    return false
  }

  function zapamatovatRozbaleni() {
    window.localStorage.setItem('programNahled_rozbaleny', nahled.viditelny ? '1' : '')
  }

  function nactiId(id) {
    if (nahled && id == nahled.id && nahled.viditelny) {
      nahled.skryj()
      return
    }

    window.localStorage.setItem('programNahled_posledniId', id)
    api.nacti(id).then(function(data) {
      nahled.data = data
      nahled.id = id
    })
  }

  skryvac.onclick = function() {
    nahled.skryj()
  }

  odkazy.forEach(e => e.onclick = (ev => odkazKlik(ev.target)))

  odkazyZapamatovat.forEach(e => e.addEventListener('click', zapamatovatRozbaleni))
}
