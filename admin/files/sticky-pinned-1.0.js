document.addEventListener('DOMContentLoaded', function () {
  // https://css-tricks.com/how-to-detect-when-a-sticky-element-gets-pinned/
  // označí třídou ten element, který se dostal mimo viewport
  const observer = new IntersectionObserver(
    function (entries) {
      entries.forEach(function (entry) {
        entry.target.classList.toggle("pinned", entry.intersectionRatio < 1)
      })
    },
    {threshold: [1]},
  )

  document.querySelectorAll(".sticky-pinned").forEach(function (element) {
    observer.observe(element)
  })
})
