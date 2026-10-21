export interface QuoteLine {
  id: number
  partId: number
  part: {
    id: number
    name: string
    oem: string
    brand: string
    colours: string[]
    stockQuantity: number
  }
  quantity: number
  colour: string | null
  backorder: boolean
  unitPrice: string
  lineTotal: string
}

export interface Quote {
  id: number
  status: 'draft' | 'submitted'
  submittedAt: string | null
  lines: QuoteLine[]
  totals: {
    subtotal: string
    discount: string
    vat: string
    total: string
    discountPercentage: number
  }
  warnings: { lineId: number, message: string }[]
}

const QUOTE_KEY = 'quoteId'

function readQuoteId(): string | null {
  try {
    return localStorage.getItem(QUOTE_KEY)
  } catch {
    return null
  }
}

function storeQuoteId(id: number | null) {
  try {
    if (id === null) {
      localStorage.removeItem(QUOTE_KEY)
    } else {
      localStorage.setItem(QUOTE_KEY, String(id))
    }
  } catch {
    // Without storage the quote just won't survive a refresh
  }
}

/**
 * The workshop's open quote, shared by the drawer and the detail page.
 * Every mutation replaces the quote with the API's response; totals are never computed here.
 */
export function useQuote() {
  const api = useApi()
  const quote = useState<Quote | null>('quote', () => null)
  const open = useState('quoteOpen', () => false)

  function set(value: Quote | null) {
    quote.value = value
    storeQuoteId(value?.id ?? null)
  }

  /**
   * Restore the quote remembered in localStorage, so a refresh or new tab reopens it.
   */
  async function restore() {
    const id = readQuoteId()
    if (!id || quote.value) {
      return
    }
    try {
      set(await api<Quote>(`/quote/${id}`))
    } catch {
      // Gone or not ours anymore: start fresh on the next add
      set(null)
    }
  }

  async function ensureDraft(): Promise<Quote> {
    if (quote.value?.status === 'draft') {
      return quote.value
    }
    const created = await api<Quote>('/quote', { method: 'POST' })
    set(created)
    return created
  }

  async function addLine(partId: number, quantity: number, colour: string | null) {
    const { id } = await ensureDraft()
    set(await api<Quote>(`/quote/${id}/quoteline`, {
      method: 'POST',
      body: { partId, quantity, colour }
    }))
  }

  async function updateLine(lineId: number, changes: { quantity?: number, colour?: string | null }) {
    set(await api<Quote>(`/quote/${quote.value!.id}/quoteline/${lineId}`, {
      method: 'PATCH',
      body: changes
    }))
  }

  async function removeLine(lineId: number) {
    set(await api<Quote>(`/quote/${quote.value!.id}/quoteline/${lineId}`, { method: 'DELETE' }))
  }

  async function submit() {
    set(await api<Quote>(`/quote/${quote.value!.id}/submit`, { method: 'POST' }))
  }

  /**
   * Leave a submitted quote behind; the next add creates a fresh draft.
   */
  function startNew() {
    set(null)
  }

  return { quote, open, restore, addLine, updateLine, removeLine, submit, startNew }
}
