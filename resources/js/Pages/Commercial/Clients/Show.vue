<template>
  <div>
    <div class="flex justify-between items-center mb-4">
      <h1 class="text-xl font-semibold">{{ client.name }}</h1>
      <div class="flex gap-2">
        <Link v-if="$page.props.auth.user.can?.['clients.manage']"
              :href="route('clients.edit', client.id)"
              class="px-4 py-2 bg-blue-600 text-white rounded">Edit</Link>
        <Link :href="route('clients.index')" class="px-4 py-2 border rounded">Back</Link>
      </div>
    </div>

    <div class="grid grid-cols-2 gap-4 mb-6 text-sm">
      <div><span class="font-medium">Document:</span> {{ client.document }}</div>
      <div><span class="font-medium">Type:</span> {{ client.document_type }}</div>
      <div><span class="font-medium">Email:</span> {{ client.email ?? '—' }}</div>
      <div><span class="font-medium">Phone:</span> {{ client.phone ?? '—' }}</div>
      <div><span class="font-medium">Active:</span> {{ client.active ? 'Yes' : 'No' }}</div>
    </div>

    <div class="mb-6">
      <div class="flex justify-between items-center mb-2">
        <h2 class="text-lg font-semibold">Freight Tables</h2>
        <Link v-if="$page.props.auth.user.can?.['freight_tables.manage']"
              :href="route('clients.freight-tables.create', client.id)"
              class="px-3 py-1 bg-blue-600 text-white rounded text-sm">New Table</Link>
      </div>
      <table class="w-full text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="text-left p-2">Name</th>
            <th class="text-left p-2">Min Weight (kg)</th>
            <th class="text-left p-2">Max Weight (kg)</th>
            <th class="p-2"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="table in client.freight_tables" :key="table.id" class="border-t">
            <td class="p-2">{{ table.name }}</td>
            <td class="p-2">{{ table.min_weight_kg ?? '—' }}</td>
            <td class="p-2">{{ table.max_weight_kg ?? '—' }}</td>
            <td class="p-2 flex gap-2">
              <Link :href="route('freight-tables.show', table.id)" class="text-blue-600">View</Link>
              <Link v-if="$page.props.auth.user.can?.['freight_tables.manage']"
                    :href="route('freight-tables.edit', table.id)" class="text-blue-600">Edit</Link>
            </td>
          </tr>
          <tr v-if="!client.freight_tables?.length">
            <td colspan="4" class="p-2 text-gray-500">No freight tables.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div>
      <div class="flex justify-between items-center mb-2">
        <h2 class="text-lg font-semibold">Per-KM Rates</h2>
        <Link v-if="$page.props.auth.user.can?.['freight_tables.manage']"
              :href="route('clients.per-km-rates.create', client.id)"
              class="px-3 py-1 bg-blue-600 text-white rounded text-sm">New Rate</Link>
      </div>
      <table class="w-full text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="text-left p-2">Origin State</th>
            <th class="text-left p-2">Destination State</th>
            <th class="text-left p-2">Rate per KM</th>
            <th class="p-2"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="rate in client.per_km_rates" :key="rate.id" class="border-t">
            <td class="p-2">{{ rate.origin_state }}</td>
            <td class="p-2">{{ rate.destination_state }}</td>
            <td class="p-2">{{ rate.rate_per_km }}</td>
            <td class="p-2 flex gap-2">
              <Link v-if="$page.props.auth.user.can?.['freight_tables.manage']"
                    :href="route('per-km-rates.edit', rate.id)" class="text-blue-600">Edit</Link>
              <button v-if="$page.props.auth.user.can?.['freight_tables.manage']"
                      @click="destroyRate(rate)" class="text-red-600">Delete</button>
            </td>
          </tr>
          <tr v-if="!client.per_km_rates?.length">
            <td colspan="4" class="p-2 text-gray-500">No per-KM rates.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script>
import { Link, router } from '@inertiajs/vue3'

export default {
  components: { Link },
  props: {
    client: Object,
  },
  methods: {
    destroyRate(rate) {
      if (confirm('Delete this rate?')) {
        router.delete(route('per-km-rates.destroy', rate.id))
      }
    },
  },
}
</script>
