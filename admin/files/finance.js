document.addEventListener('DOMContentLoaded', function () {
    const mainContentElement = document.getElementById('mainContent')

    if (window.location.hash) {
        const urlHash = window.location.hash
        const idFromHash = urlHash.trim().replace('#', '')
        const elementProZvyrazneni = document.getElementById(idFromHash)
        if (elementProZvyrazneni) {
            elementProZvyrazneni.classList.add('zvyrazni')
        }
    }

    Array.from(mainContentElement.getElementsByClassName('zvyrazni')).forEach(function (zvyrazniElement) {
        zablikej(zvyrazniElement)
    })
})
