{
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.reload-fio').forEach(function (element) {
      const button = element.querySelector('button')
      if ((button instanceof HTMLInputElement || button instanceof HTMLButtonElement) && ['submit', 'button'].includes(button.type)) {
        button.addEventListener('click', function () {
          const originalElementCursor = element.style.cursor
          element.style.cursor = 'wait'

          const chybaFio = element.querySelector('.chybaFio')
          if (chybaFio) {
            chybaFio.style.display = 'none'
          }

          setTimeout(function () {
            button.disabled = true
          }, 1) // malý trik aby zablokování tlačítka proběhlo až v dalším "tiku" a tím se nezablokoval samotný submit

          // Přesná hodnota je v data-stav, zobrazený text je zaokrouhlený a s čárkou.
          const stavElement = element.querySelector('.stav-uctu-castka')
          const query = new URLSearchParams({puvodniStav: stavElement.dataset.stav})
          const baseUrl = document.baseURI;
          fetch(
            `${baseUrl}/api/reload-fio?` + query.toString(),
            {
              method: 'GET',
              headers: {
                'Accept': 'application/json'
              }
            }
          ).then(function (response) {
            if (!response.ok) {
              throw new Error(`HTTP error! Status: ${response.status}; Body: ${response.body}`);
            }

            return response.json();
          }).then(function (data) {
            if (data.zmenilSeZustatek) {
              stavElement.dataset.stav = data.novyStav
              stavElement.textContent = formatujCastku(data.novyStav)
              zablikej(element)
            } else {
              blikni(element, '#595959')
            }
          }).catch(function (error) {
            const chybaFio = element.querySelector('.chybaFio')
            if (chybaFio) {
              chybaFio.style.display = 'initial'
              zablikej(element, '#F3A680FF')
            }
            console.error('Chyba při získávání dat z FIO API:', error);
          }).finally(function () {
            unblockAndRemoveLoadingFioReload(button, element, originalElementCursor)
          })
        })
      }

      /**
       * @param {HTMLInputElement|HTMLButtonElement} button
       * @param {HTMLElement} element
       * @param {string} originalElementCursor
       */
      function unblockAndRemoveLoadingFioReload(button, element, originalElementCursor = 'inherit') {
        button.disabled = false
        element.style.cursor = originalElementCursor
        return true
      }
    })
  })
}
