<template>
  <form @submit.prevent="submit">
    <h1 class="text-xl font-semibold mb-4">Edit Per-KM Rate ({{ rate.state }})</h1>

    <div>
      <label class="block text-sm font-medium">Rate per KM (R$) *</label>
      <input v-model="form.rate_per_km" type="number" step="0.0001" min="0" class="border rounded px-3 py-2 w-full" />
      <p v-if="form.errors.rate_per_km" class="text-red-500 text-sm">{{ form.errors.rate_per_km }}</p>
    </div>

    <div class="mt-6 flex gap-2">
      <button type="submit" :disabled="form.processing" class="px-4 py-2 bg-blue-600 text-white rounded">Save</button>
    </div>
  </form>
</template>

<script>
import { useForm } from '@inertiajs/vue3'

export default {
  props: { rate: Object },
  setup(props) {
    const form = useForm({ rate_per_km: props.rate.rate_per_km })
    return { form }
  },
  methods: {
    submit() {
      this.form.put(route('per-km-rates.update', this.rate.id))
    },
  },
}
</script>
