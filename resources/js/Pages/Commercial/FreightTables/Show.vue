<template>
  <div>
    <h1 class="text-xl font-semibold mb-4">{{ freightTable.name }}</h1>
    <p class="text-sm text-gray-500 mb-4">Model: {{ freightTable.pricing_model }}</p>

    <div v-if="freightTable.pricing_model === 'fixed'">
      <h2 class="font-medium mb-2">Fixed Rates</h2>
      <table class="w-full text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="text-left p-2">Name</th>
            <th class="text-right p-2">Price</th>
            <th class="p-2"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="rate in freightTable.fixed_rates" :key="rate.id" class="border-t">
            <td class="p-2">{{ rate.name }}</td>
            <td class="p-2 text-right">{{ rate.price }}</td>
            <td class="p-2">
              <Link :href="route('fixed-rates.edit', rate.id)" class="text-blue-600">Edit</Link>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script>
import { Link } from '@inertiajs/vue3'

export default {
  components: { Link },
  props: { freightTable: Object },
}
</script>
