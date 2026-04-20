<script>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'

export default {
    components: { AuthenticatedLayout, Head, Link },

    props: { freightTable: Object },

    methods: {
        destroyRate(rate) {
            if (confirm('Remover esta taxa?')) {
                router.delete(route('fixed-rates.destroy', rate.id))
            }
        },
    },
}
</script>

<template>
    <Head :title="freightTable.name" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <Link :href="route('clients.show', freightTable.client_id)" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                        </svg>
                    </Link>
                    <div>
                        <h1 class="text-xl font-semibold text-gray-900">{{ freightTable.name }}</h1>
                        <p class="text-sm text-gray-500">{{ freightTable.pricing_model === 'per_km' ? 'Por KM' : 'Fixo' }}</p>
                    </div>
                </div>
                <Link
                    :href="route('freight-tables.edit', freightTable.id)"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    Editar Tabela
                </Link>
            </div>
        </template>

        <!-- Fixed rates -->
        <div v-if="freightTable.pricing_model === 'fixed'" class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-700">Taxas Fixas</h2>
                <Link
                    :href="route('freight-tables.fixed-rates.create', freightTable.id)"
                    class="inline-flex items-center gap-1 rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-500 transition-colors"
                >
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    Nova Taxa
                </Link>
            </div>

            <div v-if="!freightTable.fixed_rates?.length" class="px-6 py-10 text-center text-sm text-gray-500">
                Nenhuma taxa cadastrada.
            </div>

            <div v-for="rate in freightTable.fixed_rates" :key="rate.id" class="border-b border-gray-100 last:border-0">
                <div class="flex items-center justify-between px-6 py-3 bg-gray-50">
                    <div class="flex items-center gap-4">
                        <span class="text-sm font-medium text-gray-900">{{ rate.name }}</span>
                        <span v-if="rate.avg_km" class="text-xs text-gray-500">{{ rate.avg_km }} km</span>
                    </div>
                    <div class="flex items-center gap-4">
                        <Link :href="route('fixed-rates.edit', rate.id)" class="text-xs font-medium text-indigo-600 hover:text-indigo-800 transition-colors">Editar</Link>
                        <button class="text-xs font-medium text-red-500 hover:text-red-700 transition-colors" @click="destroyRate(rate)">Remover</button>
                    </div>
                </div>
                <table v-if="rate.prices?.length" class="min-w-full divide-y divide-gray-100">
                    <thead>
                        <tr class="bg-white">
                            <th class="px-6 py-2 text-left text-xs font-medium text-gray-400">Tipo de Veículo</th>
                            <th class="px-6 py-2 text-right text-xs font-medium text-gray-400">Preço (R$)</th>
                            <th class="px-6 py-2 text-right text-xs font-medium text-gray-400">Pedágio (R$)</th>
                            <th class="px-6 py-2 text-right text-xs font-medium text-gray-400">Combustível (R$)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50 bg-white">
                        <tr v-for="price in rate.prices" :key="price.id">
                            <td class="whitespace-nowrap px-6 py-2 text-sm text-gray-700">{{ price.vehicle_type?.label }}</td>
                            <td class="whitespace-nowrap px-6 py-2 text-right text-sm text-gray-700">{{ price.price }}</td>
                            <td class="whitespace-nowrap px-6 py-2 text-right text-sm text-gray-500">{{ price.tolls ?? '—' }}</td>
                            <td class="whitespace-nowrap px-6 py-2 text-right text-sm text-gray-500">{{ price.fuel_cost ?? '—' }}</td>
                        </tr>
                    </tbody>
                </table>
                <p v-else class="px-6 py-3 text-xs text-gray-400 italic">Nenhum preço por tipo de veículo cadastrado.</p>
            </div>
        </div>

        <div v-else class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200 p-6">
            <p class="text-sm text-gray-500">Esta tabela utiliza precificação por KM. Configure as taxas na página do cliente.</p>
        </div>
    </AuthenticatedLayout>
</template>
