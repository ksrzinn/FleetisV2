<template>
  <div>
    <div class="flex justify-between items-center mb-4">
      <h1 class="text-xl font-semibold">Clients</h1>
      <Link v-if="$page.props.auth.user.can?.['clients.manage']"
            :href="route('clients.create')"
            class="px-4 py-2 bg-blue-600 text-white rounded">New Client</Link>
    </div>

    <div class="mb-4 flex gap-2">
      <input v-model="filters.search" type="text" placeholder="Search name or document..."
             class="border rounded px-3 py-2 w-64" @input="search" />
      <select v-model="filters.active" class="border rounded px-3 py-2" @change="search">
        <option value="">All</option>
        <option value="1">Active</option>
        <option value="0">Inactive</option>
      </select>
    </div>

    <table class="w-full text-sm">
      <thead class="bg-gray-50">
        <tr>
          <th class="text-left p-2">Name</th>
          <th class="text-left p-2">Document</th>
          <th class="text-left p-2">Email</th>
          <th class="text-left p-2">Active</th>
          <th class="p-2"></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="client in clients.data" :key="client.id" class="border-t">
          <td class="p-2">{{ client.name }}</td>
          <td class="p-2">{{ client.document }}</td>
          <td class="p-2">{{ client.email ?? '—' }}</td>
          <td class="p-2">{{ client.active ? 'Yes' : 'No' }}</td>
          <td class="p-2 flex gap-2">
            <Link :href="route('clients.edit', client.id)" class="text-blue-600">Edit</Link>
            <button v-if="$page.props.auth.user.can?.['clients.delete']"
                    @click="destroy(client)" class="text-red-600">Delete</button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script>
import { Link, router } from '@inertiajs/vue3'

export default {
  components: { Link },
  props: {
    clients: Object,
    filters: Object,
  },
  data() {
    return {
      filters: { search: this.filters?.search ?? '', active: this.filters?.active ?? '' },
    }
  },
  methods: {
    search() {
      router.get(route('clients.index'), this.filters, { preserveState: true, replace: true })
    },
    destroy(client) {
      if (confirm(`Delete ${client.name}?`)) {
        router.delete(route('clients.destroy', client.id))
      }
    },
  },
}
</script>
