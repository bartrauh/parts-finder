<script setup lang="ts">
const { quote, open, restore, updateLine, removeLine, submit, startNew } = useQuote()
const toast = useToast()

onMounted(restore)

const isDraft = computed(() => quote.value?.status === 'draft')
const lineCount = computed(() => quote.value?.lines.length ?? 0)

const euro = new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' })
const money = (amount: string) => euro.format(Number(amount))

// Quantity steps are batched per line, so clicking + five times sends one PATCH
const QUANTITY_DEBOUNCE = 400
const pendingQuantities = reactive<Record<number, number>>({})
const timers = new Map<number, ReturnType<typeof setTimeout>>()
const busyLines = reactive(new Set<number>())

function changeQuantity(lineId: number, quantity: number | null) {
  if (!quantity || quantity < 1) {
    return
  }
  pendingQuantities[lineId] = quantity
  clearTimeout(timers.get(lineId))
  timers.set(lineId, setTimeout(() => saveQuantity(lineId), QUANTITY_DEBOUNCE))
}

async function saveQuantity(lineId: number) {
  timers.delete(lineId)
  busyLines.add(lineId)
  try {
    await updateLine(lineId, { quantity: pendingQuantities[lineId] })
  } catch (e) {
    toast.add({ title: 'Could not change quantity', description: apiErrorMessage(e), color: 'error', icon: 'i-lucide-circle-alert' })
  } finally {
    // Show whatever the API holds now, the new quantity or the old one on failure
    delete pendingQuantities[lineId] // eslint-disable-line @typescript-eslint/no-dynamic-delete
    busyLines.delete(lineId)
  }
}

async function remove(lineId: number) {
  busyLines.add(lineId)
  try {
    await removeLine(lineId)
  } catch (e) {
    toast.add({ title: 'Could not remove line', description: apiErrorMessage(e), color: 'error', icon: 'i-lucide-circle-alert' })
  } finally {
    busyLines.delete(lineId)
  }
}

const submitting = ref(false)
const submitError = ref<string | null>(null)

async function submitQuote() {
  submitting.value = true
  submitError.value = null
  try {
    await submit()
    toast.add({ title: 'Quote submitted', color: 'success', icon: 'i-lucide-check' })
  } catch (e) {
    submitError.value = apiErrorMessage(e)
  } finally {
    submitting.value = false
  }
}

function newQuote() {
  submitError.value = null
  startNew()
}
</script>

<template>
  <USlideover
    v-model:open="open"
    :title="quote ? `Quote #${quote.id}` : 'Quote'"
    :description="isDraft || !quote ? '' : 'Submitted'"
  >
    <UChip
      :text="lineCount"
      :show="lineCount > 0"
      size="3xl"
    >
      <UButton
        icon="i-lucide-file-text"
        color="neutral"
        variant="ghost"
        aria-label="Open quote"
      />
    </UChip>

    <template #body>
      <UEmpty
        v-if="!quote?.lines.length"
        icon="i-lucide-file-text"
        title="Your quote is empty"
        description="Add parts from their detail page."
      />

      <div
        v-else
        class="space-y-4"
      >
        <UAlert
          v-if="quote.status === 'submitted'"
          color="success"
          variant="subtle"
          icon="i-lucide-lock"
          title="This quote has been submitted"
          :description="quote.submittedAt ? `On ${new Date(quote.submittedAt).toLocaleString('nl-NL')}` : undefined"
        />

        <UAlert
          v-for="warning in quote.warnings"
          :key="warning.lineId"
          color="warning"
          variant="subtle"
          icon="i-lucide-triangle-alert"
          :description="warning.message"
        />

        <UAlert
          v-if="submitError"
          color="error"
          variant="subtle"
          icon="i-lucide-circle-alert"
          title="Could not submit the quote"
          :description="submitError"
        />

        <ul class="divide-y divide-default">
          <li
            v-for="line in quote.lines"
            :key="line.id"
            class="space-y-2 py-3"
            :class="{ 'opacity-60': busyLines.has(line.id) }"
            data-testid="quote-line"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <NuxtLink
                  :to="`/parts/${line.partId}`"
                  class="font-medium text-highlighted hover:underline"
                >
                  {{ line.part.name }}
                </NuxtLink>
                <p class="text-xs text-muted">
                  {{ line.part.brand }} · {{ line.part.oem }}<template v-if="line.colour">
                    · {{ line.colour }}
                  </template>
                </p>
                <UBadge
                  v-if="line.backorder"
                  label="Backorder"
                  color="warning"
                  variant="subtle"
                  size="sm"
                  class="mt-1"
                />
              </div>
              <UButton
                v-if="isDraft"
                icon="i-lucide-trash-2"
                color="neutral"
                variant="ghost"
                size="sm"
                :aria-label="`Remove ${line.part.name}`"
                :disabled="busyLines.has(line.id)"
                data-testid="remove-line"
                @click="remove(line.id)"
              />
            </div>

            <div class="flex items-center justify-between gap-3">
              <UInputNumber
                v-if="isDraft"
                :model-value="pendingQuantities[line.id] ?? line.quantity"
                :min="1"
                :max="line.backorder ? undefined : line.part.stockQuantity"
                size="sm"
                class="w-28"
                data-testid="line-quantity"
                @update:model-value="changeQuantity(line.id, $event)"
              />
              <span
                v-else
                class="text-sm"
              >{{ line.quantity }} ×</span>
              <div class="text-right text-sm">
                <p class="text-muted">
                  {{ money(line.unitPrice) }} each
                </p>
                <p
                  class="font-medium text-highlighted"
                  data-testid="line-total"
                >
                  {{ money(line.lineTotal) }}
                </p>
              </div>
            </div>
          </li>
        </ul>
      </div>
    </template>

    <template #footer>
      <div class="w-full space-y-4">
        <dl
          v-if="quote?.lines.length"
          class="grid grid-cols-[1fr_auto] gap-y-1 text-sm"
          data-testid="totals"
        >
          <dt class="text-muted">
            Subtotal
          </dt>
          <dd>{{ money(quote.totals.subtotal) }}</dd>
          <template v-if="quote.totals.discountPercentage">
            <dt class="text-muted">
              Workshop discount ({{ quote.totals.discountPercentage }}%)
            </dt>
            <dd>− {{ money(quote.totals.discount) }}</dd>
          </template>
          <dt class="text-muted">
            VAT (21%)
          </dt>
          <dd>{{ money(quote.totals.vat) }}</dd>
          <dt class="font-semibold text-highlighted">
            Total
          </dt>
          <dd
            class="font-semibold text-highlighted"
            data-testid="total"
          >
            {{ money(quote.totals.total) }}
          </dd>
        </dl>

        <UButton
          v-if="quote?.status === 'submitted'"
          label="Start a new quote"
          icon="i-lucide-file-plus"
          block
          @click="newQuote"
        />
        <UButton
          v-else-if="quote?.lines.length"
          label="Submit quote"
          icon="i-lucide-send"
          block
          :loading="submitting"
          :disabled="busyLines.size > 0"
          @click="submitQuote"
        />
      </div>
    </template>
  </USlideover>
</template>
