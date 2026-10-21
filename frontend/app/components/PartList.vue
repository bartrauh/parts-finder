<script setup lang="ts">
import type { Part } from '~/types/part'

interface Paginated<T> {
  data: T[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}

const api = useApi()

const route = useRoute()
const router = useRouter()

const conditionItems = [
  { label: 'All conditions', value: 'all' },
  { label: 'New', value: 'new' },
  { label: 'Used', value: 'used' }
]

const sortItems = [
  { label: 'Default order', value: 'none' },
  { label: 'Price: low to high', value: 'price_asc' },
  { label: 'Price: high to low', value: 'price_desc' }
]

const toArray = (value: unknown): string[] =>
  (Array.isArray(value) ? value : [value]).filter((v): v is string => typeof v === 'string' && v !== '')

const toString = (value: unknown): string | undefined =>
  typeof value === 'string' && value !== '' ? value : undefined

// The URL is the single source of truth for search, filters, sort and page
const q = computed(() => toString(route.query.q))
const brands = computed(() => toArray(route.query.brand))
const condition = computed(() => toString(route.query.condition))
const sort = computed(() => toString(route.query.sort))
const page = computed(() => Number(route.query.page) || 1)

function updateQuery(changes: Record<string, string | string[] | number | undefined>, resetPage = true) {
  const merged: Record<string, unknown> = { ...route.query, ...changes }
  if (resetPage) {
    merged.page = undefined
  }
  const query = Object.fromEntries(
    Object.entries(merged).filter(([, value]) =>
      value !== undefined && value !== '' && !(Array.isArray(value) && value.length === 0))
  ) as Record<string, string | string[]>
  router.replace({ query })
}

const search = ref(q.value ?? '')
let searchTimeout: ReturnType<typeof setTimeout> | undefined
watch(search, (value) => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => updateQuery({ q: value.trim() || undefined }), 300)
})
// Keep the input in sync when the URL changes from elsewhere (back/forward)
watch(q, (value) => {
  if ((value ?? '') !== search.value.trim()) {
    search.value = value ?? ''
  }
})

const selectedBrands = computed({
  get: () => brands.value,
  set: (value: string[]) => updateQuery({ brand: value })
})

const selectedCondition = computed({
  get: () => condition.value ?? 'all',
  set: (value: string) => updateQuery({ condition: value === 'all' ? undefined : value })
})

const selectedSort = computed({
  get: () => sort.value ?? 'none',
  set: (value: string) => updateQuery({ sort: value === 'none' ? undefined : value })
})

const currentPage = computed({
  get: () => page.value,
  set: (value: number) => updateQuery({ page: value > 1 ? value : undefined }, false)
})

const hasActiveFilters = computed(() => !!(q.value || brands.value.length || condition.value || sort.value))

function resetFilters() {
  search.value = ''
  router.replace({ query: {} })
}

const { data: brandOptions } = await useFetch<string[]>('/brands', {
  $fetch: api,
  query: computed(() => ({ q: q.value, condition: condition.value }))
})

const { data: parts, status, error, refresh } = await useFetch<Paginated<Part>>('/parts', {
  $fetch: api,
  query: computed(() => ({
    'q': q.value,
    'brand[]': brands.value,
    'condition': condition.value,
    'sort': sort.value,
    'page': page.value > 1 ? page.value : undefined
  }))
})
</script>

<template>
  <div class="space-y-6">
    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
      <UInput
        v-model="search"
        icon="i-lucide-search"
        placeholder="Search by name or OEM number"
        class="w-full"
      />
      <USelectMenu
        v-model="selectedBrands"
        :items="brandOptions ?? []"
        multiple
        placeholder="All brands"
        class="w-full"
      />
      <USelect
        v-model="selectedCondition"
        :items="conditionItems"
        class="w-full"
      />
      <USelect
        v-model="selectedSort"
        :items="sortItems"
        class="w-full"
      />
    </div>

    <div class="flex items-center justify-between text-sm text-muted">
      <span v-if="parts">{{ parts.total }} {{ parts.total === 1 ? 'part' : 'parts' }} found</span>
      <UButton
        v-if="hasActiveFilters"
        variant="link"
        color="neutral"
        icon="i-lucide-x"
        label="Clear filters"
        @click="resetFilters"
      />
    </div>

    <UAlert
      v-if="error"
      color="error"
      variant="subtle"
      icon="i-lucide-circle-alert"
      title="Could not load parts"
      :description="error.message"
      :actions="[{ label: 'Retry', onClick: () => refresh() }]"
    />

    <div
      v-else-if="status === 'pending' && !parts"
      class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
    >
      <USkeleton
        v-for="n in 8"
        :key="n"
        class="h-96"
      />
    </div>

    <UEmpty
      v-else-if="!parts?.data.length"
      icon="i-lucide-package-search"
      title="No parts found"
      description="Try a different search term or adjust the filters."
    />

    <div
      v-else
      class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
      :class="{ 'opacity-60': status === 'pending' }"
    >
      <PartCard
        v-for="part in parts.data"
        :key="part.id"
        :part="part"
      />
    </div>

    <div
      v-if="parts && parts.last_page > 1"
      class="flex justify-center"
    >
      <UPagination
        v-model:page="currentPage"
        :total="parts.total"
        :items-per-page="parts.per_page"
      />
    </div>
  </div>
</template>
