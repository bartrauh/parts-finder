<script setup lang="ts">
import type { Part } from '~/types/part'

const props = defineProps<{ part: Part }>()

const { addLine, open: quoteOpen } = useQuote()
const toast = useToast()

const to = computed(() => `/parts/${props.part.id}`)
const hasColours = computed(() => props.part.colours.length > 0)

const adding = ref(false)

// Parts with colours need a choice first, so those go to the detail page instead
async function quickAdd() {
  adding.value = true
  try {
    await addLine(props.part.id, 1, null)
    toast.add({
      title: 'Added to quote',
      description: `1 × ${props.part.name}`,
      color: 'success',
      icon: 'i-lucide-check',
      actions: [{ label: 'View quote', onClick: () => { quoteOpen.value = true } }]
    })
  } catch (e) {
    toast.add({ title: 'Could not add to quote', description: apiErrorMessage(e), color: 'error', icon: 'i-lucide-circle-alert' })
  } finally {
    adding.value = false
  }
}
</script>

<template>
  <article class="group flex flex-col overflow-hidden rounded-lg border border-default bg-default transition hover:border-accented hover:shadow-md">
    <NuxtLink
      :to="to"
      class="relative flex aspect-4/3 items-center justify-center bg-elevated"
      tabindex="-1"
      aria-hidden="true"
    >
      <UIcon
        name="i-lucide-package"
        class="size-16 text-dimmed transition group-hover:scale-105"
      />
      <UBadge
        :label="part.condition === 'new' ? 'New' : 'Used'"
        :color="part.condition === 'new' ? 'primary' : 'neutral'"
        variant="solid"
        size="sm"
        class="absolute top-2 left-2"
      />
      <div
        v-if="hasColours"
        class="absolute top-2 right-2 flex -space-x-1"
        :title="`Available in ${part.colours.join(', ')}`"
      >
        <span
          v-for="colour in part.colours"
          :key="colour"
          class="size-4 rounded-full border-2 border-default"
          :style="{ backgroundColor: colour }"
        />
      </div>
    </NuxtLink>

    <div class="flex flex-1 flex-col gap-1 p-4">
      <p class="text-xs font-medium tracking-wide text-muted uppercase">
        {{ part.brand }} · {{ part.category }}
      </p>
      <NuxtLink
        :to="to"
        class="line-clamp-2 font-semibold text-highlighted hover:underline"
      >
        {{ part.name }}
      </NuxtLink>
      <p class="font-mono text-xs text-muted">
        OEM {{ part.oem }}
      </p>

      <p
        class="mt-2 flex items-center gap-1.5 text-sm"
        :class="part.in_stock ? 'text-success' : 'text-warning'"
      >
        <span
          class="size-2 rounded-full"
          :class="part.in_stock ? 'bg-success' : 'bg-warning'"
        />
        {{ part.in_stock ? 'In stock' : 'On backorder' }}
      </p>

      <div class="mt-auto flex items-end justify-between gap-2 pt-4">
        <div>
          <p class="text-xl font-bold text-highlighted">
            {{ formatEuro(part.price) }}
          </p>
          <p class="text-xs text-muted">
            excl. VAT
          </p>
        </div>
        <UButton
          v-if="hasColours"
          :to="to"
          label="Choose colour"
          color="neutral"
          variant="outline"
          size="sm"
        />
        <UButton
          v-else
          icon="i-lucide-file-plus"
          label="Add"
          size="sm"
          :loading="adding"
          :aria-label="`Add ${part.name} to quote`"
          @click="quickAdd"
        />
      </div>
    </div>
  </article>
</template>
