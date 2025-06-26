<template>
  <BasePage>
    <BasePageHeader :title="$t('logs.audit_logs')">
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
              v-model="filters.invoice_id"
              :label="$t('invoices.invoice_number')"
              type="text"
              placeholder="Search by invoice number"
            />
          </div>
          <div>
            <BaseInput
              v-model="filters.user_search"
              :label="$t('general.user')"
              type="text"
              placeholder="Search by username or email"
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
        :data="auditLogStore.getAuditLogs"
        :columns="auditLogColumns"
        :loading="auditLogStore.getIsFetching"
        :pagination="true"
        :search="false"
        :show-empty-screen="showEmptyScreen"
        @page-changed="onPageChanged"
      >
        <template #cell-invoice="{ row }">
          <div class="flex flex-col">
            <span class="font-medium text-gray-900">
              {{ row.data.invoice.invoice_number }}
            </span>
            <span class="text-sm text-gray-500">
              {{ row.data.invoice.customer.name }}
            </span>
          </div>
        </template>

        <template #cell-user="{ row }">
          <div class="flex flex-col">
            <span class="font-medium text-gray-900">
              {{ row.data.user.name }}
            </span>
            <span class="text-sm text-gray-500">
              {{ row.data.user.email }}
            </span>
          </div>
        </template>

        <template #cell-status_change="{ row }">
          <div class="flex items-center space-x-2">
            <span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full">
              {{ row.data.old_status }}
            </span>
            <span class="text-gray-400">→</span>
            <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">
              {{ row.data.new_status }}
            </span>
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
import { useAuditLogStore } from '@/scripts/admin/stores/audit-log'
import { useUserStore } from '@/scripts/admin/stores/user'
import { debouncedWatch } from '@vueuse/core'
import abilities from '@/scripts/admin/stub/abilities'

const auditLogStore = useAuditLogStore()
const userStore = useUserStore()
const { t } = useI18n()

const tableComponent = ref(null)
const tableKey = ref(0)
const showFilters = ref(false)
const isRequestOngoing = ref(true)

const filters = reactive({
  invoice_id: '',
  user_search: '',
  from_date: '',
  to_date: '',
})

const showEmptyScreen = computed(
  () => !auditLogStore.getTotalAuditLogs && !isRequestOngoing.value
)

const auditLogColumns = computed(() => {
  return [
    {
      key: 'invoice',
      label: t('invoices.invoice'),
      thClass: 'extra',
      tdClass: 'font-medium',
    },
    {
      key: 'user',
      label: t('general.user'),
      thClass: 'extra',
      tdClass: 'font-medium',
    },
    {
      key: 'status_change',
      label: t('logs.status_change'),
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
  loadAuditLogs()
})

function canViewAuditLogs() {
  return userStore.hasAbilities([abilities.VIEW_AUDIT_LOGS])
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
  loadAuditLogs(params)
}

async function loadAuditLogs(params = {}) {
  if (!canViewAuditLogs()) {
    return
  }

  isRequestOngoing.value = true
  try {
    await auditLogStore.fetchAuditLogs(params)
  } catch (error) {
    console.error('Error loading audit logs:', error)
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
  loadAuditLogs(params)
}

function formatDate(date) {
  return new Date(date).toLocaleDateString()
}

function refreshTable() {
  tableComponent.value && tableComponent.value.refresh()
}
</script> 