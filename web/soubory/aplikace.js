$(function () {
  $('a[href^="http"]').attr('target', '_blank')
})

function zobrazSkryj (event, obsah) {
  if (typeof obsah === 'undefined') {
    $('#' + event.target.id + '-obsah').slideToggle()
  } else {
    $('#' + obsah).slideToggle()
  }
  event.preventDefault()
}
