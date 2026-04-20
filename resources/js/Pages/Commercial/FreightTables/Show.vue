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
                    v-if="$page.props.auth.user.can?.['freight_tables.manage']"
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
                    v-if="$page.props.auth.user.can?.['freight_tables.manage']"
                    :href="route('freight-tables.fixed-rates.create', freightTable.id)"
                    class="inline-flex items-center gap-1 rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-500 transition-colors"
                >
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    Nova Taxa
                </Link>
            </div>
            <table class="min-w-full divide-y divide-gray-100">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Nome</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Preço (R$)</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">KM Médio</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Pedágio (R$)</th>
                        <th class="px-6 py-3" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    <tr v-for="rate in freightTable.fixed_rates" :key="rate.id" class="hover:bg-gray-50 transition-colors">
                        <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">{{ rate.name }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-right text-sm text-gray-700">{{ rate.price }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-right text-sm text-gray-600">{{ rate.avg_km ?? '—' }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-right text-sm text-gray-600">{{ rate.tolls ?? '—' }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                            <Link
                                v-if="$page.props.auth.user.can?.['freight_tables.manage']"
                                :href="route('fixed-rates.edit', rate.id)"
                                class="font-medium text-indigo-600 hover:text-indigo-800 mr-4 transition-colors"
                            >Editar</Link>
                            <button
                                v-if="$page.props.auth.user.can?.['freight_tables.manage']"
                                class="font-medium text-red-500 hover:text-red-700 transition-colors"
                                @click="destroyRate(rate)"
                            >Remover</button>
                        </td>
                    </tr>
                    <tr v-if="!freightTable.fixed_rates?.length">
                        <td colspan="5" class="px-6 py-10 text-center text-sm text-gray-500">Nenhuma taxa cadastrada.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-else class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200 p-6">
            <p class="text-sm text-gray-500">Esta tabela utiliza precificação por KM. Configure as taxas na página do cliente.</p>
        </div>
    </AuthenticatedLayout>
</template>
