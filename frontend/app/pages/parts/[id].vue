<script setup lang="ts">
import type { Part } from '~/types/part'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const { data: part, status, error, refresh } = await useFetch<Part>(`/parts/${route.params.id}`, {
  $fetch: useApi()
})

useSeoMeta({ title: () => part.value?.name ?? 'Part' })

const price = computed(() => part.value
  ? new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' }).format(Number(part.value.price))
  : '')

const quantity = ref(1)
const colour = ref<string | undefined>(undefined)
watch(part, (value) => {
  colour.value = value?.colours[0]
}, { immediate: true })

const colourItems = computed(() => (part.value?.colours ?? []).map(value => ({
  label: value.charAt(0).toUpperCase() + value.slice(1),
  value
})))

// Back to the overview with its filters intact when we came from there
function goBack() {
  if (import.meta.client && window.history.state?.back) {
    router.back()
  } else {
    router.push('/')
  }
}

const { addLine, open: quoteOpen } = useQuote()
const adding = ref(false)

async function addToQuote() {
  if (!part.value) {
    return
  }
  adding.value = true
  try {
    await addLine(part.value.id, quantity.value, colour.value ?? null)
    toast.add({
      title: 'Added to quote',
      description: `${quantity.value} × ${part.value.name}`,
      color: 'success',
      icon: 'i-lucide-check',
      actions: [{ label: 'View quote', onClick: () => { quoteOpen.value = true } }]
    })
  } catch (e) {
    toast.add({
      title: 'Could not add to quote',
      description: apiErrorMessage(e),
      color: 'error',
      icon: 'i-lucide-circle-alert'
    })
  } finally {
    adding.value = false
  }
}
</script>

<template>
  <UContainer class="py-8 space-y-6">
    <UButton
      variant="link"
      color="neutral"
      icon="i-lucide-arrow-left"
      label="Back to parts"
      class="px-0"
      @click="goBack"
    />

    <UAlert
      v-if="error"
      color="error"
      variant="subtle"
      icon="i-lucide-circle-alert"
      :title="error.statusCode === 404 ? 'Part not found' : 'Could not load this part'"
      :description="error.statusCode === 404 ? 'This part does not exist or has been removed.' : error.message"
      :actions="error.statusCode === 404 ? [] : [{ label: 'Retry', onClick: () => refresh() }]"
    />

    <div
      v-else-if="status === 'pending' && !part"
      class="grid gap-8 md:grid-cols-2"
    >
      <USkeleton class="aspect-square w-full" />
      <div class="space-y-4">
        <USkeleton class="h-8 w-3/4" />
        <USkeleton class="h-5 w-1/2" />
        <USkeleton class="h-10 w-1/3" />
      </div>
    </div>

    <div
      v-else-if="part"
      class="grid gap-8 md:grid-cols-2"
    >
      <div class="flex aspect-square items-center justify-center rounded-lg border border-default bg-elevated">
        <UIcon
          name="i-lucide-package"
          class="size-24 text-dimmed"
        />
      </div>

      <div class="space-y-6">
        <div class="space-y-2">
          <div class="flex flex-wrap gap-2">
            <UBadge
              :label="part.brand"
              color="neutral"
              variant="subtle"
            />
            <UBadge
              :label="part.condition === 'new' ? 'New' : 'Used'"
              :color="part.condition === 'new' ? 'primary' : 'neutral'"
              variant="subtle"
            />
            <UBadge
              v-if="part.colours.length"
              label="Available in colour"
              color="info"
              variant="subtle"
              icon="i-lucide-palette"
            />
          </div>
          <h1 class="text-2xl font-semibold text-highlighted">
            {{ part.name }}
          </h1>
        </div>

        <div>
          <p class="text-3xl font-semibold text-highlighted">
            {{ price }}
          </p>
          <p class="text-sm text-muted">
            excl. VAT
          </p>
        </div>

        <dl class="grid grid-cols-[auto_1fr] gap-x-6 gap-y-2 text-sm">
          <dt class="text-muted">
            OEM number
          </dt>
          <dd class="font-mono">
            {{ part.oem }}
          </dd>
          <dt class="text-muted">
            Category
          </dt>
          <dd>{{ part.category }}</dd>
          <dt class="text-muted">
            Stock
          </dt>
          <dd>
            <span
              v-if="part.in_stock"
              class="text-success"
            >In stock ({{ part.quantity }})</span>
            <span
              v-else
              class="text-warning"
            >Out of stock, available on backorder</span>
          </dd>
        </dl>

        <div class="space-y-4 rounded-lg border border-default p-4">
          <UFormField
            v-if="part.colours.length"
            label="Colour"
          >
            <USelect
              v-model="colour"
              :items="colourItems"
              class="w-full sm:w-56"
            >
              <template #leading>
                <span
                  class="size-3 rounded-full border border-default"
                  :style="{ backgroundColor: colour }"
                />
              </template>
            </USelect>
          </UFormField>

          <UFormField
            label="Quantity"
            :help="part.in_stock ? '' : 'Will be placed on backorder'"
          >
            <UInputNumber
              v-model="quantity"
              :min="1"
              :max="part.in_stock ? part.quantity : undefined"
              class="w-full sm:w-40"
            />
          </UFormField>

          <UButton
            label="Add to quote"
            icon="i-lucide-file-plus"
            size="lg"
            block
            :loading="adding"
            @click="addToQuote"
          />
        </div>
      </div>
    </div>
  </UContainer>
</template>
