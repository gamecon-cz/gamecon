document.addEventListener('DOMContentLoaded', function () {
  'use strict'

  // Fetch all the forms we want to apply custom Bootstrap validation styles to
  const forms = document.querySelectorAll('.needs-validation')

  // Loop over them and prevent submission
  Array.prototype.slice.call(forms)
    .forEach(function (form) {
      form.addEventListener('submit', function (event) {
        // tlačítko s formnovalidate (Přihlásit QR kódem) jméno ani heslo nepotřebuje
        if (event.submitter && event.submitter.formNoValidate) {
          return
        }
        if (!form.checkValidity()) {
          event.preventDefault()
          event.stopPropagation()
        }

        form.classList.add('was-validated')
      }, false)
    })
})
