document.addEventListener('DOMContentLoaded', function () {
  // https://simon-reynolds.github.io/jquery.dirty/

  document.querySelectorAll("form.prevent-leaving-without-save").forEach(function (element) {
    $(element).dirty({
      preventLeaving: true,
      leavingMessage: 'Změny nejsou uloženy', // funguje pouze na starých prohlížečích - na novějších seobjeví generická hláška
    })
  })
})
