const euro = new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' })

/**
 * Format an amount as the API returns it ("489.00") for display.
 */
export function formatEuro(amount: string | number): string {
  return euro.format(Number(amount))
}
