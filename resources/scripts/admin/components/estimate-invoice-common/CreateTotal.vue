<template>
  <div
    class="
      px-5
      py-4
      mt-6
      bg-white
      border border-gray-200 border-solid
      rounded
      md:min-w-[390px]
      min-w-[300px]
      lg:mt-7
    "
  >
    <div class="flex items-center justify-between w-full">
      <BaseContentPlaceholders v-if="isLoading">
        <BaseContentPlaceholdersText :lines="1" class="w-16 h-5" />
      </BaseContentPlaceholders>
      <label
        v-else
        class="text-sm font-semibold leading-5 text-gray-400 uppercase"
      >
        {{ $t('estimates.sub_total') }}
      </label>

      <BaseContentPlaceholders v-if="isLoading">
        <BaseContentPlaceholdersText :lines="1" class="w-16 h-5" />
      </BaseContentPlaceholders>

      <label
        v-else
        class="flex items-center justify-center m-0 text-lg text-black uppercase "
      >
        <BaseFormatMoney
          :amount="store.getSubTotal"
          :currency="defaultCurrency"
        />
      </label>
    </div>

    <div
      v-for="tax in itemWiseTaxes"
      :key="tax.tax_type_id"
      class="flex items-center justify-between w-full"
    >
      <BaseContentPlaceholders v-if="isLoading">
        <BaseContentPlaceholdersText :lines="1" class="w-16 h-5" />
      </BaseContentPlaceholders>
      <label
        v-else-if="store[storeProp].tax_per_item === 'YES'"
        class="m-0 text-sm font-semibold leading-5 text-gray-500 uppercase"
      >
        <template v-if="tax.calculation_type === 'percentage'">
          {{ tax.name }} - {{ tax.percent }}%
        </template>
        <template v-else>
          {{ tax.name }} - <BaseFormatMoney :amount="tax.fixed_amount" :currency="defaultCurrency" />
        </template>
      </label>

      <BaseContentPlaceholders v-if="isLoading">
        <BaseContentPlaceholdersText :lines="1" class="w-16 h-5" />
      </BaseContentPlaceholders>

      <label
        v-else-if="store[storeProp].tax_per_item === 'YES'"
        class="flex items-center justify-center m-0 text-lg text-black uppercase "
      >
        <BaseFormatMoney :amount="tax.amount" :currency="defaultCurrency" />
      </label>
    </div>

    <div
      v-if="
        store[storeProp].discount_per_item === 'NO' ||
        store[storeProp].discount_per_item === null
      "
      class="flex items-center justify-between w-full mt-2"
    >
      <BaseContentPlaceholders v-if="isLoading">
        <BaseContentPlaceholdersText :lines="1" class="w-16 h-5" />
      </BaseContentPlaceholders>
      <label
        v-else
        class="text-sm font-semibold leading-5 text-gray-400 uppercase"
      >
        {{ $t('estimates.discount') }}
      </label>
      <BaseContentPlaceholders v-if="isLoading">
        <BaseContentPlaceholdersText
          :lines="1"
          class="w-24 h-8 border rounded-md"
        />
      </BaseContentPlaceholders>
      <div v-else class="flex" style="width: 140px" role="group">
        <BaseInput
          v-model="totalDiscount"
          class="
            border-r-0
            focus:border-r-2
            rounded-tr-sm rounded-br-sm
            h-[38px]
          "
        />
        <BaseDropdown position="bottom-end">
          <template #activator>
            <BaseButton
              class="p-2 rounded-none rounded-tr-md rounded-br-md"
              type="button"
              variant="white"
            >
              <span class="flex items-center">
                {{
                  store[storeProp].discount_type == 'fixed'
                    ? defaultCurrency.symbol
                    : '%'
                }}

                <BaseIcon
                  name="ChevronDownIcon"
                  class="w-4 h-4 ml-1 text-gray-500"
                />
              </span>
            </BaseButton>
          </template>

          <BaseDropdownItem @click="selectFixed">
            {{ $t('general.fixed') }}
          </BaseDropdownItem>

          <BaseDropdownItem @click="selectPercentage">
            {{ $t('general.percentage') }}
          </BaseDropdownItem>
        </BaseDropdown>
      </div>
    </div>

    <div
      v-if="
        store[storeProp].tax_per_item === 'NO' ||
        store[storeProp].tax_per_item === null
      "
    >
      <Tax
        v-for="(tax, index) in taxes"
        :key="tax.id"
        :index="index"
        :tax="tax"
        :taxes="taxes"
        :currency="currency"
        :store="store"
        :store-prop="storeProp"
        @remove="removeTax"
        @update="updateTax"
      />
    </div>

    <div
      v-if="
        store[storeProp].tax_per_item === 'NO' ||
        store[storeProp].tax_per_item === null
      "
      ref="taxModal"
      class="float-right pt-2 pb-4"
    >
      <SelectTaxPopup
        :store-prop="storeProp"
        :store="store"
        :type="taxPopupType"
        @select:taxType="onSelectTax"
      />
    </div>

    <div
      class="flex items-center justify-between w-full pt-2 mt-5 border-t border-gray-200 border-solid "
    >
      <BaseContentPlaceholders v-if="isLoading">
        <BaseContentPlaceholdersText :lines="1" class="w-16 h-5" />
      </BaseContentPlaceholders>
      <label
        v-else
        class="m-0 text-sm font-semibold leading-5 text-gray-400 uppercase"
        >{{ $t('estimates.total') }} {{ $t('estimates.amount') }}:</label
      >

      <BaseContentPlaceholders v-if="isLoading">
        <BaseContentPlaceholdersText :lines="1" class="w-16 h-5" />
      </BaseContentPlaceholders>
      <label
        v-else
        class="flex items-center justify-center text-lg uppercase  text-primary-400"
      >
        <BaseFormatMoney :amount="store.getTotal" :currency="defaultCurrency" />
      </label>
    </div>
  </div>
</template>

<script setup>
import { computed, inject, ref, watch } from 'vue'
import Guid from 'guid'
import Tax from './CreateTotalTaxes.vue'
import TaxStub from '@/scripts/admin/stub/abilities'
import SelectTaxPopup from './SelectTaxPopup.vue'
import { useCompanyStore } from '@/scripts/admin/stores/company'

const taxModal = ref(null)

const props = defineProps({
  store: {
    type: Object,
    default: null,
  },
  storeProp: {
    type: String,
    default: '',
  },
  taxPopupType: {
    type: String,
    default: '',
  },
  currency: {
    type: [Object, String],
    default: '',
  },
  isLoading: {
    type: Boolean,
    default: false,
  },
})

const utils = inject('$utils')

const companyStore = useCompanyStore()

watch(
  () => props.store[props.storeProp].items,
  () => {
    recalculateTaxes()
  },
  { deep: true }
)

watch(
  () => props.store[props.storeProp].tax_per_item,
  () => {
    recalculateTaxes()
  }
)

watch(
  () => props.store[props.storeProp].discount,
  () => {
    setDiscount()
    recalculateTaxes()
  }
)

// Watch for changes in items array to recalculate taxes when data is loaded (for edit mode)
watch(
  () => props.store[props.storeProp].items.length,
  () => {
    recalculateTaxes()
  }
)

const totalDiscount = computed({
  get: () => {
    return props.store[props.storeProp].discount
  },
  set: (newValue) => {
    props.store[props.storeProp].discount = newValue
    setDiscount()
  },
})

const taxes = computed({
  get: () => props.store[props.storeProp].taxes,
  set: (value) => {
    props.store.$patch((state) => {
      state[props.storeProp].taxes = value
    })
  },
})

const itemWiseTaxes = computed(() => {
  let taxes = []
  props.store[props.storeProp].items.forEach((item) => {
    // Only include taxes from items with item_tax_type = 'S' (Standard-rated)
    if (item.taxes && item.item_tax_type === 'S') {
      item.taxes.forEach((tax) => {
        let found = taxes.find((_tax) => {
          return _tax.tax_type_id === tax.tax_type_id
        })
        if (found) {
          found.amount += tax.amount
        } else if (tax.tax_type_id) {
          taxes.push({
            tax_type_id: tax.tax_type_id,
            amount: Math.round(tax.amount),
            percent: tax.percent,
            name: tax.name,
            calculation_type: tax.calculation_type,
            fixed_amount: tax.fixed_amount
          })
        }
      })
    }
  })
  return taxes
})

const defaultCurrency = computed(() => {
  if (props.currency) {
    return props.currency
  } else {
    return companyStore.selectedCompanyCurrency
  }
})

const hasOutOfScopeItems = computed(() => {
  return props.store[props.storeProp].items.some(item => item.item_tax_type === 'O')
})

const taxableAmount = computed(() => {
  return props.store[props.storeProp].items
    .filter(item => item.item_tax_type === 'S')
    .reduce((sum, item) => sum + (item.total - item.discount_val), 0)
})

const nonTaxableAmount = computed(() => {
  return props.store[props.storeProp].items
    .filter(item => item.item_tax_type === 'O')
    .reduce((sum, item) => sum + (item.total - item.discount_val), 0)
})

function setDiscount() {
  const newValue = props.store[props.storeProp].discount

  if (props.store[props.storeProp].discount_type === 'percentage') {
    props.store[props.storeProp].discount_val
      = Math.round((props.store.getSubTotal * newValue) / 100)

    return
  }

  props.store[props.storeProp].discount_val = Math.round(newValue * 100)
}

function selectFixed() {
  if (props.store[props.storeProp].discount_type === 'fixed') {
    return
  }
  props.store[props.storeProp].discount_val = Math.round(
    props.store[props.storeProp].discount * 100
  )
  props.store[props.storeProp].discount_type = 'fixed'
}

function selectPercentage() {
  if (props.store[props.storeProp].discount_type === 'percentage'){
    return
  }

  const val = Math.round(props.store[props.storeProp].discount * 100) / 100

  props.store[props.storeProp].discount_val
    = Math.round((props.store.getSubTotal * val) / 100)

  props.store[props.storeProp].discount_type = 'percentage'
}

function onSelectTax(selectedTax) {
  let amount = 0
  
  // Calculate taxable amount only from items with item_tax_type = 'S'
  let taxableSubtotal = 0
  let totalSubtotal = 0
  
  props.store[props.storeProp].items.forEach((item) => {
    totalSubtotal += item.total - item.discount_val
    if (item.item_tax_type === 'S') {
      taxableSubtotal += item.total - item.discount_val
    }
  })
  
  if (selectedTax.calculation_type === 'percentage' && taxableSubtotal && selectedTax.percent) {
    amount = Math.round(
      (taxableSubtotal * selectedTax.percent) / 100
    )
  } else if (selectedTax.calculation_type === 'fixed') {
    amount = selectedTax.fixed_amount
  }

  let data = {
    ...TaxStub,
    id: Guid.raw(),
    name: selectedTax.name,
    percent: selectedTax.percent,
    tax_type_id: selectedTax.id,
    amount,
    calculation_type: selectedTax.calculation_type,
    fixed_amount: selectedTax.fixed_amount
  }
  props.store.$patch((state) => {
    state[props.storeProp].taxes.push({ ...data })
  })
}

function updateTax(data) {
  const tax = props.store[props.storeProp].taxes.find(
    (tax) => tax.id === data.id
  )
  if (tax) {
    Object.assign(tax, { ...data })
  }
}

function removeTax(id) {
  const index = props.store[props.storeProp].taxes.findIndex(
    (tax) => tax.id === id
  )

  props.store.$patch((state) => {
    state[props.storeProp].taxes.splice(index, 1)
  })
}

function recalculateTaxes() {
  // Recalculate taxes only for items with item_tax_type = 'S'
  let taxableSubtotal = 0
  props.store[props.storeProp].items.forEach((item) => {
    if (item.item_tax_type === 'S') {
      taxableSubtotal += item.total - item.discount_val
    }
  })
  
  // Update existing taxes with new amounts
  props.store[props.storeProp].taxes.forEach((tax) => {
    if (tax.calculation_type === 'percentage' && taxableSubtotal && tax.percent) {
      tax.amount = Math.round((taxableSubtotal * tax.percent) / 100)
    }
    // Fixed amount taxes don't need recalculation
  })
}
</script>

