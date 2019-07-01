/**
 * @param obalNahledu jquery element set element, ve kterém je náhled
 * @param obalProgramu jquery element set element, ve kterém je program (kvůli
 *  úpravě šířky při zobrazení náhledu)
 * @param odkazy jquery element set odkazy na aktivity. Musí mít nastavený data
 *  atribut program-nahled-id. Nejde o všechny odkazy, ale jen odkazy na
 *  aktivity.
 * @param prepinac jquery element set prvek, kterým se zapíná/vypíná zobrazení
 *  náhledu.
 */
function programNahled(obalNahledu, obalProgramu, odkazy, prepinac) {

  class Nahled {
    constructor(obalNahledu, obalProgramu, prepinac, storage) {
      this.obalNahledu = obalNahledu
      this.obalProgramu = obalProgramu
      this.storage = storage
      this.prepinac = prepinac

      if (
        this.viditelny || // ručně zapnuto
        this.viditelny === null && $(document).width() > 1000 // nenastaveno
      ) this.zobraz()
    }

    zobraz() {
      this.obalProgramu.addClass('programNahled_obalProgramu-zuzeny')
      this.obalNahledu.addClass('programNahled_obalNahledu-viditelny')
      this.storage.setItem('programNahled_viditelny', '1')
      this.prepinac.html('vypnout náhledy')
    }

    skryj() {
      this.obalProgramu.removeClass('programNahled_obalProgramu-zuzeny')
      this.obalNahledu.removeClass('programNahled_obalNahledu-viditelny')
      this.storage.setItem('programNahled_viditelny', '')
      this.prepinac.html('zapnout náhledy')
    }

    get viditelny() {
      return this.storage.getItem('programNahled_viditelny')
    }

    zobrazSkryj() {
      this.viditelny ? this.skryj() : this.zobraz()
    }

    set data(data) {
      this.obalNahledu.find('.programNahled_nazev').html(data.nazev)
      this.obalNahledu.find('.programNahled_stitky').html(data.stitky.map(function(stitek) {
        return '<div class="programNahled_stitek">'+stitek+'</div>'
      }).join(''))
      this.obalNahledu.find('.programNahled_vypraveci').html(data.vypraveci.join(', '))
      this.obalNahledu.find('.programNahled_kratkyPopis').html(data.kratkyPopis)
      this.obalNahledu.find('.programNahled_popis').html(data.popis)
      this.obalNahledu.find('.programNahled_obrazek').attr('src', data.obrazek)

      this.obalNahledu.addClass('programNahled_obalNahledu-maData')

      this.obalNahledu.find('.programNahled_text').scrollTop(0);
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
        return $.ajax('program-nahled-api', {data: {idAktivity: id}}).then(function(data) {
          api.cache[id] = data
          return data
        })
      }
    }
  }

  var nahled = new Nahled(
    obalNahledu,
    obalProgramu,
    prepinac,
    window.localStorage
  )

  var api = new Api()

  var skryvac = obalNahledu.find('.programNahled_zaviratko')

  function odkazKlik() {
    if (nahled.viditelny) {
      var id = $(this).data('program-nahled-id')
      api.nacti(id).then(function(data) {
        nahled.data = data
      })
      return false
    } else {
      return true // výchozí chování odkazu, když je pruh skrytý
    }
  }

  skryvac.click(function() {
    nahled.skryj()
  })

  // inicializace a nastavení přepínače
  if (!nahled.viditelny) prepinac.html('zapnout náhledy')
  prepinac.click(function() {
    nahled.zobrazSkryj()
    return false
  })

  odkazy.click(odkazKlik)
}
