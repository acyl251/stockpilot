const fmt = new Intl.NumberFormat('fr-TN', {
  minimumFractionDigits: 3,
  maximumFractionDigits: 3,
})

export function formatPrice(v: number | string | null | undefined): string {
  return fmt.format(Number(v ?? 0)) + ' DT'
}

export function formatPriceShort(v: number | string | null | undefined): string {
  return fmt.format(Number(v ?? 0)) + ' DT'
}

/**
 * Parse a price typed by the user, accepting both '.' and ',' as the decimal
 * separator (French-locale keyboards produce ',' — native number inputs can
 * mis-handle it depending on browser/OS locale, silently dropping the
 * separator, e.g. "4,566" -> 4566 instead of 4.566).
 */
export function parsePrice(input: string | number | null | undefined): number {
  if (typeof input === 'number') return isNaN(input) ? 0 : input
  const normalized = String(input ?? '').trim().replace(',', '.').replace(/[^0-9.]/g, '')
  const n = parseFloat(normalized)
  return isNaN(n) ? 0 : n
}
