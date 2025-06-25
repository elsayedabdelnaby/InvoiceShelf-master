<template>
  <div class="flex items-center justify-between w-full mt-2 text-sm">
    <label v-if="tax.calculation_type === 'percentage'" class="font-semibold leading-5 text-gray-500 uppercase">
      {{ tax.name }} ({{ tax.percent }} %)
    </label>
    <label v-else class="font-semibold leading-5 text-gray-500 uppercase">
      {{ tax.name }} (<BaseFormatMoney :amount="tax.fixed_amount" :currency="currency" />)
    </label>
    <label class="flex items-center justify-center text-lg text-black">
      <BaseFormatMoney :amount="tax.amount" :currency="currency" />

      <BaseIcon
        name="TrashIcon"
        class="h-5 ml-2 cursor-pointer"
        @click="$emit('remove', tax.id)"
      />
    </label>
  </div>
</template>

<script setup>
import { computed, watch, inject, watchEffect } from 'vue'

const props = defineProps({
  index: {
    type: Number,
    required: true,
  },
  tax: {
    type: Object,
    required: true,
  },
  taxes: {
    type: Array,
    required: true,
  },
  currency: {
    type: [Object, String],
    required: true,
  },
  store: {
    type: Object,
    default: null,
  },
  storeProp: {
    type: String,
    default: '',
  },
  data: {
    type: String,
    default: '',
  },
})

const emit = defineEmits(['update', 'remove'])

const utils = inject('$utils')

const taxAmount = computed(() => {
  if (props.tax.calculation_type === 'fixed') {
    return props.tax.fixed_amount;
  }
  
  // Calculate taxable amount only from items with item_tax_type = 'S'
  let taxableSubtotal = 0
  props.store[props.storeProp].items.forEach((item) => {
    if (item.item_tax_type === 'S') {
      taxableSubtotal += item.total - item.discount_val
    }
  })
  
  if (props.tax.compound_tax && taxableSubtotal) {
    return Math.round(
      ((taxableSubtotal + props.store.getTotalSimpleTax) *
        props.tax.percent) /
        100
    )
  }
  if (taxableSubtotal && props.tax.percent) {
    return Math.round(
      (taxableSubtotal * props.tax.percent) / 100
    )
  }
  return 0
})

watchEffect(() => {
  if (props.store.getSubtotalWithDiscount) {
    updateTax()
  }
  if (props.store.getTotalSimpleTax) {
    updateTax()
  }
  // Watch for changes in items to recalculate tax when item_tax_type changes
  if (props.store[props.storeProp]?.items) {
    updateTax()
  }
})

function updateTax() {
  emit('update', {
    ...props.tax,
    amount: taxAmount.value,
  })
}
</script>
