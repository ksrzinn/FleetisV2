<template>
  <form @submit.prevent="submit">
    <h1 class="text-xl font-semibold mb-4">New Freight Table</h1>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium">Name *</label>
        <input v-model="form.name" type="text" class="border rounded px-3 py-2 w-full" />
        <p v-if="form.errors.name" class="text-red-500 text-sm">{{ form.errors.name }}</p>
      </div>
      <div>
        <label class="block text-sm font-medium">Pricing Model *</label>
        <select v-model="form.pricing_model" class="border rounded px-3 py-2 w-full">
          <option value="fixed">Fixed</option>
          <option value="per_km">Per KM</option>
        </select>
        <p v-if="form.errors.pricing_model" class="text-red-500 text-sm">{{ form.errors.pricing_model }}</p>
      </div>
    </div>

    <div class="mt-4 flex items-center gap-2">
      <input v-model="form.active" type="checkbox" id="active" />
      <label for="active" class="text-sm">Active</label>
    </div>

    <div class="mt-6 flex gap-2">
      <button type="submit" :disabled="form.processing" class="px-4 py-2 bg-blue-600 text-white rounded">Save</button>
    </div>
  </form>
</template>

<script>
import { useForm } from '@inertiajs/vue3'

export default {
  props: { client: Object },
  setup() {
    const form = useForm({ name: '', pricing_model: 'fixed', active: true })
    return { form }
  },
  methods: {
    submit() {
      this.form.post(route('clients.freight-tables.store', this.client.id))
    },
  },
}
</script>
