<template>
  <form @submit.prevent="submit">
    <h1 class="text-xl font-semibold mb-4">Edit Freight Table</h1>

    <div>
      <label class="block text-sm font-medium">Name *</label>
      <input v-model="form.name" type="text" class="border rounded px-3 py-2 w-full" />
      <p v-if="form.errors.name" class="text-red-500 text-sm">{{ form.errors.name }}</p>
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
  props: { freightTable: Object },
  setup(props) {
    const form = useForm({ name: props.freightTable.name, active: props.freightTable.active })
    return { form }
  },
  methods: {
    submit() {
      this.form.put(route('freight-tables.update', this.freightTable.id))
    },
  },
}
</script>
