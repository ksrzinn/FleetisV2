<template>
  <form @submit.prevent="submit">
    <h1 class="text-xl font-semibold mb-4">Edit Client</h1>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium">Name *</label>
        <input v-model="form.name" type="text" class="border rounded px-3 py-2 w-full" />
        <p v-if="form.errors.name" class="text-red-500 text-sm">{{ form.errors.name }}</p>
      </div>
      <div>
        <label class="block text-sm font-medium">CPF / CNPJ *</label>
        <input v-model="form.document" type="text" class="border rounded px-3 py-2 w-full" />
        <p v-if="form.errors.document" class="text-red-500 text-sm">{{ form.errors.document }}</p>
      </div>
      <div>
        <label class="block text-sm font-medium">Email</label>
        <input v-model="form.email" type="email" class="border rounded px-3 py-2 w-full" />
      </div>
      <div>
        <label class="block text-sm font-medium">Phone</label>
        <input v-model="form.phone" type="text" class="border rounded px-3 py-2 w-full" />
      </div>
      <div>
        <label class="block text-sm font-medium">Street</label>
        <input v-model="form.address_street" type="text" class="border rounded px-3 py-2 w-full" />
      </div>
      <div>
        <label class="block text-sm font-medium">City</label>
        <input v-model="form.address_city" type="text" class="border rounded px-3 py-2 w-full" />
      </div>
      <div>
        <label class="block text-sm font-medium">State (UF)</label>
        <input v-model="form.address_state" type="text" maxlength="2" class="border rounded px-3 py-2 w-full" />
      </div>
      <div>
        <label class="block text-sm font-medium">ZIP</label>
        <input v-model="form.address_zip" type="text" maxlength="8" class="border rounded px-3 py-2 w-full" />
      </div>
    </div>

    <div class="mt-4 flex items-center gap-2">
      <input v-model="form.active" type="checkbox" id="active" />
      <label for="active" class="text-sm">Active</label>
    </div>

    <div class="mt-6 flex gap-2">
      <button type="submit" :disabled="form.processing" class="px-4 py-2 bg-blue-600 text-white rounded">Save</button>
      <Link :href="route('clients.index')" class="px-4 py-2 border rounded">Cancel</Link>
    </div>
  </form>
</template>

<script>
import { Link, useForm } from '@inertiajs/vue3'

export default {
  components: { Link },
  props: { client: Object },
  setup(props) {
    const form = useForm({
      name: props.client.name,
      document: props.client.document,
      email: props.client.email ?? '',
      phone: props.client.phone ?? '',
      address_street: props.client.address_street ?? '',
      address_city: props.client.address_city ?? '',
      address_state: props.client.address_state ?? '',
      address_zip: props.client.address_zip ?? '',
      active: props.client.active,
    })
    return { form }
  },
  methods: {
    submit() {
      this.form.put(route('clients.update', this.client.id))
    },
  },
}
</script>
