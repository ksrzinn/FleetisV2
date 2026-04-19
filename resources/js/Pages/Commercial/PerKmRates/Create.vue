<template>
  <form @submit.prevent="submit">
    <h1 class="text-xl font-semibold mb-4">New Per-KM Rate</h1>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium">State (UF) *</label>
        <input v-model="form.state" type="text" maxlength="2" placeholder="SP" class="border rounded px-3 py-2 w-full uppercase" />
        <p v-if="form.errors.state" class="text-red-500 text-sm">{{ form.errors.state }}</p>
      </div>
      <div>
        <label class="block text-sm font-medium">Rate per KM (R$) *</label>
        <input v-model="form.rate_per_km" type="number" step="0.0001" min="0" class="border rounded px-3 py-2 w-full" />
        <p v-if="form.errors.rate_per_km" class="text-red-500 text-sm">{{ form.errors.rate_per_km }}</p>
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
  props: { client: Object },
  setup() {
    const form = useForm({ state: '', rate_per_km: '' })
    return { form }
  },
  methods: {
    submit() {
      this.form.post(route('clients.per-km-rates.store', this.client.id))
    },
  },
}
</script>
