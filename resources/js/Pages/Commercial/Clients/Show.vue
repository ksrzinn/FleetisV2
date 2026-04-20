<script>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'

export default {
    components: { AuthenticatedLayout, Head, Link },

    props: { client: Object },

    methods: {
        destroyRate(rate) {
            if (confirm('Remover esta taxa?')) {
                router.delete(route('per-km-rates.destroy', rate.id))
            }
        },
        destroyTable(table) {
            if (confirm(`Remover tabela "${table.name}"?`)) {
                router.delete(route('freight-tables.destroy', table.id))
            }
        },
    },
}
</script>

<template>
    <Head :title="client.name" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <Link :href="route('clients.index')" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                        </svg>
                    </Link>
                    <div>
                        <h1 class="text-xl font-semibold text-gray-900">{{ client.name }}</h1>
                        <p class="text-sm text-gray-500 font-mono">{{ client.document }}</p>
                    </div>
                </div>
                <Link
                    :href="route('clients.edit', client.id)"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    Editar
                </Link>
            </div>
        </template>

        <!-- Details card -->
        <div class="mb-6 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-700">Dados do Cliente</h2>
            </div>
            <dl class="grid grid-cols-2 gap-x-6 gap-y-4 p-6 sm:grid-cols-4">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Tipo</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ client.document_type }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">E-mail</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ client.email ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Telefone</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ client.phone ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Status</dt>
                    <dd class="mt-1">
                        <span
                            :class="client.active
                                ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200'
                                : 'bg-gray-100 text-gray-500 ring-1 ring-gray-200'"
                            class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium"
                        >
                            <span :class="client.active ? 'bg-emerald-500' : 'bg-gray-400'" class="h-1.5 w-1.5 rounded-full" />
                            {{ client.active ? 'Ativo' : 'Inativo' }}
                        </span>
                    </dd>
                </div>
            </dl>
        </div>

        <!-- Freight Tables -->
        <div class="mb-6 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-700">Tabelas de Frete</h2>
                <Link
                    :href="route('clients.freight-tables.create', client.id)"
                    class="inline-flex items-center gap-1 rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-500 transition-colors"
                >
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    Nova Tabela
                </Link>
            </div>
            <table class="min-w-full divide-y divide-gray-100">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Nome</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Modelo</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Status</th>
                        <th class="px-6 py-3" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    <tr v-for="table in client.freight_tables" :key="table.id" class="hover:bg-gray-50 transition-colors">
                        <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">{{ table.name }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600 capitalize">{{ table.pricing_model === 'per_km' ? 'Por KM' : 'Fixo' }}</td>
                        <td class="whitespace-nowrap px-6 py-4">
                            <span
                                :class="table.active
                                    ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200'
                                    : 'bg-gray-100 text-gray-500 ring-1 ring-gray-200'"
                                class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium"
                            >
                                <span :class="table.active ? 'bg-emerald-500' : 'bg-gray-400'" class="h-1.5 w-1.5 rounded-full" />
                                {{ table.active ? 'Ativa' : 'Inativa' }}
                            </span>
                        </td>
                        <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                            <Link :href="route('freight-tables.show', table.id)" class="font-medium text-indigo-600 hover:text-indigo-800 mr-4 transition-colors">Ver</Link>
                            <Link
                                :href="route('freight-tables.edit', table.id)"
                                class="font-medium text-indigo-600 hover:text-indigo-800 mr-4 transition-colors"
                            >Editar</Link>
                            <button
                                class="font-medium text-red-500 hover:text-red-700 transition-colors"
                                @click="destroyTable(table)"
                            >Remover</button>
                        </td>
                    </tr>
                    <tr v-if="!client.freight_tables?.length">
                        <td colspan="4" class="px-6 py-10 text-center text-sm text-gray-500">Nenhuma tabela de frete cadastrada.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Per-KM Rates -->
        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-700">Taxas por KM</h2>
                <Link
                    :href="route('clients.per-km-rates.create', client.id)"
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
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">UF</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Taxa / KM (R$)</th>
                        <th class="px-6 py-3" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    <tr v-for="rate in client.per_km_rates" :key="rate.id" class="hover:bg-gray-50 transition-colors">
                        <td class="whitespace-nowrap px-6 py-4 text-sm font-mono font-semibold text-gray-900">{{ rate.state }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-right text-sm text-gray-700">{{ rate.rate_per_km }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                            <Link
                                :href="route('per-km-rates.edit', rate.id)"
                                class="font-medium text-indigo-600 hover:text-indigo-800 mr-4 transition-colors"
                            >Editar</Link>
                            <button
                                class="font-medium text-red-500 hover:text-red-700 transition-colors"
                                @click="destroyRate(rate)"
                            >Remover</button>
                        </td>
                    </tr>
                    <tr v-if="!client.per_km_rates?.length">
                        <td colspan="3" class="px-6 py-10 text-center text-sm text-gray-500">Nenhuma taxa por KM cadastrada.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AuthenticatedLayout>
</template>
