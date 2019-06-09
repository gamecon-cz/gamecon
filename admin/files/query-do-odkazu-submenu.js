$(function () {

  const query = window.location.search;
  $('#submenu').find('a').each(function (index, a) {
    const puvodniOdkaz = a.href;
    a.href = puvodniOdkaz + query;
  })

});