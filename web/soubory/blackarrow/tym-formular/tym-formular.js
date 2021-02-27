function tymFormularOdeber(e) {
   const parent = e.parentNode
   const input = e.parentNode.querySelector('input')
   input.value = -1
   parent.style.display = 'none'
   return false
}

async function tymFormularZpracuj(tlacitko) {
   const form = tlacitko.closest('form')
   if (!form.reportValidity()) return

   tlacitko.disabled = true

   const odpoved = await fetch(window.location, {
      method: 'POST',
      body: new FormData(form)
   })

   if (!odpoved.ok) {
      tlacitko.disabled = false
      alert('neznámá chyba')
      return
   }

   const json = await odpoved.json()
   const chyby = json.chyby

   if (chyby.length) {
      tlacitko.disabled = false
      alert(chyby)
      return
   }

   location.reload()
}

function tymFormularAutocomplete(inputy) {

   async function source(term, suggest) {
      const odpoved = await fetch('ajax-omnibox?term='+term)
      const data = await odpoved.json()
      suggest(data)
   }

   function renderItem(item, search) {
      return '<div class="autocomplete-suggestion" data-val="'+item.value+'">'+item.label+'</div>'
   }

   inputy.forEach((e) => {
      new autoComplete({
         selector: e,
         source: source,
         renderItem: renderItem
      })
   })

}
