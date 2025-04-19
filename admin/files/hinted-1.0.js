/**
 * Využívá existující hint stylování i :hover pro zobrazení a pouze nastavuje pozici tak aby byl hint vidět.
 */
{
  const addListenersForHinted = () => {
    document.querySelectorAll('.hinted').forEach((hinted) => {

      const hint = hinted.querySelector(".hint")
      if (!hint) {
        console.warn("hinted element nemá hint!", hinted)
        return
      }

      hint.style.position = "fixed"
      hint.style.zIndex = 1000
      // výchozí zobrazení - vykreslí se mimo obrazovku ať známe šířku než se pokusíme hint umístít
      hint.style.left = -100000 + "px"
      hint.style.top = -100000 + "px"

      const moveHintToPosition = () => {
        const { bottom, left } = hinted.getBoundingClientRect()
        const { width: šířkaHintu } = hint.getBoundingClientRect()
        const documentWidth = document.documentElement.clientWidth

        hint.style.left = left + "px"
        hint.style.top = bottom + "px"

        // pokud zasahuje mimo obrazovku tak se srovná s pravou starnou obrazovky
        if (left + šířkaHintu > documentWidth) {
          hint.style.left = (documentWidth - šířkaHintu) + "px"
        }
      }

      // Před zobrazením nemá hint dopočítanou šířku proto počkáme chvíli než se vykreslí
      hinted.addEventListener("mouseover", () => setTimeout(moveHintToPosition, 10))
    })
  }

  document.addEventListener('DOMContentLoaded', addListenersForHinted)
}
