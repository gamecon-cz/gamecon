$('form#uvodForm').on('submit', function () {
  if ($(this).find('[name="rychloreg[email1_uzivatele]"]').val() === ''
    || $(this).find('[name="rychloreg[pohlavi]"]:checked').length === 0
  ) {
    alert('Všechny položky jsou povinné')
    return false
  }
})

const $boxy = $('.aBox:not(.solo)')
let previousColCount = 0

function sloupce() {
  const $sloupce = $('.sloupce')
  const fullw = $sloupce.width()
  const $sloupy = $('.sloupce > .sloup')
  const colw = $sloupy.width()
  const currentColCount = Math.floor(fullw / colw)
  if (currentColCount === previousColCount) {
    return // sloupce se preskladaji jen pokud to ma smysl (pokud se jich nove vejde vic nebo min)
  }
  previousColCount = currentColCount
  // vysypat boxy mimo, pokud tam jsou
  $boxy.insertAfter($sloupce)
  // vytvořit adekvátní počet sloupců
  let i = $sloupy.length
  while (i * colw < fullw - colw) {
    $sloupce.append($('.sloupce > .sloup').last().clone())
    i++
  }
  while (i * colw > fullw && i > 1) {
    $('.sloupce > .sloup').last().remove()
    i--
  }
  // nasypat boxy dovnitř podle pořadí, první výš
  $boxy.each(function () {
    let $min
    $sloupce.find('.sloup').each(function () {
      if (!$min || $(this).height() < $min.height()) {
        $min = $(this)
      }
    })
    $min.append($(this))
  })
}

sloupce()

$(window).on('resize', function () {
  sloupce()
})
