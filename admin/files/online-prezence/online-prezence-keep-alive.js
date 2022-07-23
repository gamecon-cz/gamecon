{
// KEEP ALIVE (admin běžně odhlašuje po třicetiminutové neaktivitě)
  setInterval(function () {
    $.get(dejUrlKeepAlive())
  }, 5 * 60 * 1000)

  /**
   * @return {string}
   */
  function dejUrlKeepAlive() {
    const onlinePrezence = document.getElementById('online-prezence')
    return onlinePrezence.dataset.urlAkceKeepAlive // bez domény - jQuery to nevadí
  }
}
