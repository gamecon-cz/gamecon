(() => {
  const radios = Array.from(document.querySelectorAll('.shopUbytovani_radio'));
  const povinneEls = Array.from(document.getElementsByClassName('shopUbytovani_povinne'));
  const names = [...new Set(radios.map(r => r.name))];

  // Load or initialize flag
  let presKapacituBtn = JSON.parse(sessionStorage.getItem('presKapacituBtn') || 'false');

  // Helper: toggle "povinne" elements
  const togglePovinne = show => {
    povinneEls.forEach(el => {
      el.style.display = show ? '' : 'none';
      el.querySelectorAll('input, select').forEach(input => {
        input.required = show && !presKapacituBtn;
      });
    });
  };

  // Helper: enable all disabled radios when override is active
  const applyOverride = () => {
    radios.filter(r => r.disabled).forEach(r => r.disabled = false);
  };

  // Remember user choice for each day group
  const rememberChoice = radio => {
    radios
      .filter(r => r.name === radio.name)
      .forEach(r => r.dataset.kapacitaZvolenaUzivatelem = radio.dataset.kapacita);
  };

  // Select matching or "Žádné" when capacity is full
  const syncCapacity = radio => {
    const cap = +radio.dataset.kapacita;
    if (cap === 0) return;
    names.forEach(name => {
      if (name === radio.name) return;
      const match = radios.find(r => r.name === name && r.dataset.typ === radio.dataset.typ);
      if (match && !match.disabled) {
        match.checked = true;
      } else {
        const none = radios.find(r => r.name === name && r.dataset.typ === 'Žádné');
        none.checked = true;
      }
    });
  };

  // On change handler
  const handleChange = evt => {
    const radio = evt.target;
    rememberChoice(radio);
    const anySelected = radios.some(r => r.checked && r.dataset.typ !== 'Žádné');
    togglePovinne(anySelected);
    if (presKapacituBtn) syncCapacity(radio);
  };

  // On click handler to toggle off same-type repeat
  const handleClick = evt => {
    const radio = evt.target;
    if (!radio.checked || radio.dataset.typ === 'Žádné' || !presKapacituBtn) return;
    // If same type was already selected earlier, reset previous
    radios
      .filter(r => r !== radio && r.name === radio.name && r.dataset.typ === radio.dataset.typ)
      .forEach(prev => prev.checked = false);
  };

  // Initialize on DOM ready
  document.addEventListener('DOMContentLoaded', () => {
    // Initial "povinne" visibility
    const anySelected = radios.some(r => r.checked && r.dataset.typ !== 'Žádné');
    togglePovinne(anySelected);

    if (presKapacituBtn) {
      applyOverride();
      togglePovinne(true);
    }

    // Attach handlers & remember initial
    radios.forEach(r => {
      if (r.checked) rememberChoice(r);
      r.addEventListener('change', handleChange);
      r.addEventListener('click', handleClick);
    });
  });

  // Expose override trigger
  window.presKapacitu = () => {
    presKapacituBtn = true;
    sessionStorage.setItem('presKapacituBtn', 'true');
    applyOverride();
    togglePovinne(true);
  };

  // Expose cancel override trigger
  window.zrusitPresKapacitu = () => {
    sessionStorage.removeItem('presKapacituBtn');
    location.reload();
  };
})();
