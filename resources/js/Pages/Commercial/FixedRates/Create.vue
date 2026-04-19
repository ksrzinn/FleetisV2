<template>
  <form @submit.prevent="submit">
    <h1 class="text-xl font-semibold mb-4">New Fixed Rate</h1>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium">Name *</label>
        <input v-model="form.name" type="text" class="border rounded px-3 py-2 w-full" />
        <p v-if="form.errors.name" class="text-red-500 text-sm">{{ form.errors.name }}</p>
      </div>
      <div>
        <label class="block text-sm font-medium">Price *</label>
        <input v-model="form.price" type="number" step="0.01" min="0" class="border rounded px-3 py-2 w-full" />
        <p v-if="form.errors.price" class="text-red-500 text-sm">{{ form.errors.price }}</p>
      </div>
      <div>
        <label class="block text-sm font-medium">Avg KM</label>
        <input v-model="form.avg_km" type="number" step="0.01" min="0" class="border rounded px-3 py-2 w-full" />
      </div>
      <div>
        <label class="block text-sm font-medium">Tolls</label>
        <input v-model="form.tolls" type="number" step="0.01" min="0" class="border rounded px-3 py-2 w-full" />
      </div>
      <div>
        <label class="block text-sm font-medium">Fuel Cost</label>
        <input v-model="form.fuel_cost" type="number" step="0.01" min="0" class="border rounded px-3 py-2 w-full" />
      </div>
    </div>

    <div class="mt-6 flex gap-2">
      <button type="submit" :disabled="form.processing" class="px-4 py-2 bg-blue-600 text-white rounded">Save</button>
    </div>
  </form>
</template>

<script>
import { useForm } from '@inertiajs/vue3'

export default {
  props: { freightTable: Object },
  setup() {
    const form = useForm({ name: '', price: '', avg_km: null, tolls: null, fuel_cost: null })
    return { form }
  },
  methods: {
    submit() {
      this.form.post(route('freight-tables.fixed-rates.store', this.freightTable.id))
    },
  },
}
</script>
