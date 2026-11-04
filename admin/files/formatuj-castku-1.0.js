/**
 * Stejný zápis částky jako Finance::formatujCastku(): celé koruny bez desetin, jinak dvě desetinná místa,
 * čárka a pevná mezera mezi tisíci. Částka má být už zaokrouhlená na haléře (jako Finance::zaokouhli()),
 * Math.round tady jen smaže chybu plovoucí čárky.
 *
 * @param {number} castka
 * @returns {string}
 */
window.formatujCastku = function (castka) {
  const zaokrouhlena = Math.round(castka * 100) / 100

  return (zaokrouhlena === 0 ? 0 : zaokrouhlena).toLocaleString('cs-CZ', {
    minimumFractionDigits: Number.isInteger(zaokrouhlena) ? 0 : 2,
    maximumFractionDigits: 2,
  })
}
