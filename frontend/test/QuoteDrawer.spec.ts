import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import { flushPromises } from '@vue/test-utils'
import QuoteDrawer from '~/components/QuoteDrawer.vue'
import type { Quote } from '~/composables/useQuote'

const { api } = vi.hoisted(() => ({ api: vi.fn() }))

mockNuxtImport('useApi', () => () => api)

const euro = (amount: string) => new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' }).format(Number(amount))

function quoteResponse(overrides: Partial<Quote> = {}): Quote {
  return {
    id: 7,
    status: 'draft',
    submittedAt: null,
    lines: [
      {
        id: 11,
        partId: 2119711,
        part: { id: 2119711, name: 'DAF XF 106 Sideskirt set', oem: '2119711', brand: 'DAF', colours: ['white', 'red'], stockQuantity: 3 },
        quantity: 2,
        colour: 'red',
        backorder: false,
        unitPrice: '795.00',
        lineTotal: '1590.00'
      },
      {
        id: 12,
        partId: 100003,
        part: { id: 100003, name: 'DAF XF 106 Sun visor', oem: '1880397', brand: 'DAF', colours: [], stockQuantity: 0 },
        quantity: 4,
        colour: null,
        backorder: true,
        unitPrice: '365.00',
        lineTotal: '1460.00'
      }
    ],
    // Deliberately not what the lines add up to: the drawer must show the API's numbers
    totals: { subtotal: '3050.00', discount: '305.00', vat: '576.45', total: '3321.45', discountPercentage: 10 },
    warnings: [{ lineId: 12, message: 'DAF XF 106 Sun visor is out of stock and will be placed on backorder.' }],
    ...overrides
  }
}

let mounted: Awaited<ReturnType<typeof mountSuspended>> | undefined

async function mountDrawer(quote: Quote | null = quoteResponse()) {
  useState<Quote | null>('quote').value = quote
  useState('quoteOpen').value = true
  mounted = await mountSuspended(QuoteDrawer)
  await flushPromises()
  return mounted
}

// The slideover is teleported, so its content lives on document.body
const body = () => document.body
const lines = () => body().querySelectorAll('[data-testid="quote-line"]')

describe('QuoteDrawer', () => {
  beforeEach(() => {
    api.mockReset()
    localStorage.clear()
  })

  afterEach(() => {
    // Unmount so the teleported slideover doesn't linger into the next test
    mounted?.unmount()
    vi.useRealTimers()
    useState('quoteOpen').value = false
  })

  it('renders the lines, backorder flag, warnings and totals from the API response', async () => {
    await mountDrawer()

    expect(lines()).toHaveLength(2)
    expect(lines()[0]!.textContent).toContain('DAF XF 106 Sideskirt set')
    expect(lines()[0]!.textContent).toContain(euro('1590.00'))
    expect(lines()[0]!.textContent).not.toContain('Backorder')
    expect(lines()[1]!.textContent).toContain('Backorder')
    expect(body().textContent).toContain('DAF XF 106 Sun visor is out of stock')

    const totals = body().querySelector('[data-testid="totals"]')!.textContent
    expect(totals).toContain(euro('3050.00'))
    expect(totals).toContain('Workshop discount (10%)')
    expect(totals).toContain(euro('305.00'))
    expect(totals).toContain(euro('576.45'))
    expect(body().querySelector('[data-testid="total"]')!.textContent).toContain(euro('3321.45'))
  })

  it('removes a line through the DELETE endpoint and renders the returned quote', async () => {
    const afterRemove = quoteResponse({ lines: [quoteResponse().lines[1]!] })
    api.mockResolvedValueOnce(afterRemove)
    await mountDrawer()

    body().querySelector<HTMLButtonElement>('[data-testid="remove-line"]')!.click()
    await flushPromises()

    expect(api).toHaveBeenCalledWith('/quote/7/quoteline/11', { method: 'DELETE' })
    expect(lines()).toHaveLength(1)
  })

  it('sends one PATCH with the final quantity after several steps', async () => {
    api.mockResolvedValueOnce(quoteResponse())
    const wrapper = await mountDrawer()
    vi.useFakeTimers()

    const input = wrapper.findAllComponents({ name: 'UInputNumber' })[0]!
    input.vm.$emit('update:modelValue', 3)
    input.vm.$emit('update:modelValue', 1)
    expect(api).not.toHaveBeenCalled()

    await vi.advanceTimersByTimeAsync(500)

    expect(api).toHaveBeenCalledTimes(1)
    expect(api).toHaveBeenCalledWith('/quote/7/quoteline/11', { method: 'PATCH', body: { quantity: 1 } })
  })

  it('submits the quote and then shows it read-only', async () => {
    api.mockResolvedValueOnce(quoteResponse({ status: 'submitted', submittedAt: '2026-10-22T10:00:00+00:00' }))
    await mountDrawer()

    const submit = [...body().querySelectorAll('button')].find(b => b.textContent?.includes('Submit quote'))!
    submit.click()
    await flushPromises()

    expect(api).toHaveBeenCalledWith('/quote/7/submit', { method: 'POST' })
    expect(body().querySelector('[data-testid="remove-line"]')).toBeNull()
    expect(body().querySelector('[data-testid="line-quantity"]')).toBeNull()
    expect(body().textContent).toContain('Start a new quote')
  })

  it('restores the remembered quote from localStorage', async () => {
    localStorage.setItem('quoteId', '7')
    api.mockResolvedValueOnce(quoteResponse())

    await mountDrawer(null)

    expect(api).toHaveBeenCalledWith('/quote/7')
    expect(lines()).toHaveLength(2)
  })
})
