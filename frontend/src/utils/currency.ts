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
