<template>
  <BasePage>
    <BasePageHeader :title="$t('logs.email_logs')">
      <template #actions>
        <BaseButton
          v-if="showFilters"
          variant="white"
          @click="toggleFilters"
        >
          {{ $t('general.hide_filters') }}
        </BaseButton>
        <BaseButton
          v-else
          variant="white"
          @click="toggleFilters"
        >
          {{ $t('general.show_filters') }}
        </BaseButton>
      </template>
    </BasePageHeader>

    <!-- Filters -->
    <div v-if="showFilters" class="mb-6">
      <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          <div>
            <BaseInput
              v-model="filters.search"
              :label="$t('general.search')"
              type="text"
              placeholder="Search by subject, to, or from"
            />
          </div>
          <div>
            <BaseSelect
              v-model="filters.mailable_type"
              :label="$t('logs.document_type')"
              :options="documentTypeOptions"
              placeholder="Select document type"
            />
          </div>
          <div>
            <BaseInput
              v-model="filters.from_date"
              :label="$t('general.from_date')"
              type="date"
            />
          </div>
          <div>
            <BaseInput
              v-model="filters.to_date"
              :label="$t('general.to_date')"
              type="date"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
      <BaseTable
        ref="tableComponent"
        :key="tableKey"
        :data="emailLogStore.getEmailLogs"
        :columns="emailLogColumns"
        :loading="emailLogStore.getIsFetching"
        :pagination="true"
        :search="false"
        :show-empty-screen="showEmptyScreen"
        @page-changed="onPageChanged"
      >
        <template #cell-document="{ row }">
          <div class="flex flex-col">
            <span class="font-medium text-gray-900">
              {{ row.data.mailable.type }}
            </span>
            <span class="text-sm text-gray-500">
              {{ row.data.mailable.number }}
            </span>
            <span v-if="row.data.mailable.customer" class="text-sm text-gray-500">
              {{ row.data.mailable.customer.name }}
            </span>
          </div>
        </template>

        <template #cell-email="{ row }">
          <div class="flex flex-col">
            <span class="font-medium text-gray-900">
              {{ row.data.to }}
            </span>
            <span class="text-sm text-gray-500">
              {{ row.data.from }}
            </span>
          </div>
        </template>

        <template #cell-subject="{ row }">
          <div class="max-w-xs truncate">
            {{ row.data.subject }}
          </div>
        </template>

        <template #cell-created_at="{ row }">
          {{ formatDate(row.data.created_at) }}
        </template>
      </BaseTable>
    </div>
  </BasePage>
</template>

<script setup>
import { ref, computed, reactive, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useEmailLogStore } from '@/scripts/admin/stores/email-log'
import { useUserStore } from '@/scripts/admin/stores/user'
import { debouncedWatch } from '@vueuse/core'
import abilities from '@/scripts/admin/stub/abilities'

const emailLogStore = useEmailLogStore()
const userStore = useUserStore()
const { t } = useI18n()

const tableComponent = ref(null)
const tableKey = ref(0)
const showFilters = ref(false)
const isRequestOngoing = ref(true)

const filters = reactive({
  search: '',
  mailable_type: '',
  from_date: '',
  to_date: '',
})

const documentTypeOptions = [
  { label: 'Invoice', value: 'App\\Models\\Invoice' },
  { label: 'Estimate', value: 'App\\Models\\Estimate' },
  { label: 'Payment', value: 'App\\Models\\Payment' },
]

const showEmptyScreen = computed(
  () => !emailLogStore.getTotalEmailLogs && !isRequestOngoing.value
)

const emailLogColumns = computed(() => {
  return [
    {
      key: 'document',
      label: t('logs.document'),
      thClass: 'extra',
      tdClass: 'font-medium',
    },
    {
      key: 'email',
      label: t('general.email'),
      thClass: 'extra',
      tdClass: 'font-medium',
    },
    {
      key: 'subject',
      label: t('general.subject'),
      thClass: 'extra',
      tdClass: 'font-medium',
    },
    {
      key: 'created_at',
      label: t('general.created_at'),
      thClass: 'extra',
      tdClass: 'font-medium',
    },
  ]
})

debouncedWatch(
  filters,
  () => {
    setFilters()
  },
  { debounce: 500 }
)

onMounted(() => {
  loadEmailLogs()
})

function canViewEmailLogs() {
  return userStore.hasAbilities([abilities.VIEW_EMAIL_LOGS])
}

function toggleFilters() {
  showFilters.value = !showFilters.value
}

function setFilters() {
  const params = {}
  Object.keys(filters).forEach((key) => {
    if (filters[key]) {
      params[key] = filters[key]
    }
  })
  loadEmailLogs(params)
}

async function loadEmailLogs(params = {}) {
  if (!canViewEmailLogs()) {
    return
  }

  isRequestOngoing.value = true
  try {
    await emailLogStore.fetchEmailLogs(params)
  } catch (error) {
    console.error('Error loading email logs:', error)
  } finally {
    isRequestOngoing.value = false
  }
}

function onPageChanged(page) {
  const params = { page }
  Object.keys(filters).forEach((key) => {
    if (filters[key]) {
      params[key] = filters[key]
    }
  })
  loadEmailLogs(params)
}

function formatDate(date) {
  return new Date(date).toLocaleDateString()
}

function refreshTable() {
  tableComponent.value && tableComponent.value.refresh()
}
</script> 