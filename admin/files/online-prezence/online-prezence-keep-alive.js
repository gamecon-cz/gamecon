{
// KEEP ALIVE (admin běžně odhlašuje po třicetiminutové neaktivitě)
  const interval = setInterval(async function () {
    try {
      await $.get(dejUrlKeepAlive());
    } catch(e) {
      // kontrola odhlášení uživatele na pozadí
      if (e.status === 403) {
        clearInterval(interval)
        alert("Na pozadí proběhlo odhlášení. Pro pokračování se prosím znovu přihlaste.")
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
