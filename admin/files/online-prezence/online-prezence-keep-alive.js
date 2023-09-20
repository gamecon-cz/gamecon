{
// KEEP ALIVE (admin běžně odhlašuje po třicetiminutové neaktivitě)
  setInterval(async function () {
    try {
      await $.get(dejUrlKeepAlive());
    } catch(e) {
      // kontrola odhlášení uživatele na pozadí
      if (e.status === 403) {
        window.location.reload(true);
      }
    }
  }, 15 * 1000)

  /**
   * @return {string}
   */
  function dejUrlKeepAlive() {
    const onlinePrezence = document.getElementById('online-prezence')
    return onlinePrezence.dataset.urlAkceKeepAlive // bez domény - jQuery to nevadí
  }
}
