<script>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link } from '@inertiajs/vue3'

export default {
    components: { AuthenticatedLayout, Head, Link },

    props: {
        vehicle:        { type: Object, required: true },
        metrics:        { type: Object, default: () => ({}) },
        recentFreights: { type: Array,  default: () => [] },
    },

    methods: {
        formatCurrency(val) {
            if (val === null || val === undefined) return '—'
            return Number(val).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
        },
        formatDate(val) {
            if (!val) return '—'
            return new Date(val).toLocaleDateString('pt-BR')
        },
    },
}
</script>

<template>
    <Head :title="`Veículo ${vehicle.license_plate}`" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link href="/reports/vehicles" class="text-sm text-indigo-600 hover:text-indigo-500">&larr; Voltar</Link>
                <h1 class="text-xl font-semibold text-gray-900">{{ vehicle.license_plate }}</h1>
                <span class="text-sm text-gray-400">{{ vehicle.brand }} {{ vehicle.model }}</span>
            </div>
        </template>

        <!-- KPI Cards -->
        <div class="mb-6 grid grid-cols-2 gap-5 sm:grid-cols-4">
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Receita Total</p>
                <p class="mt-2 text-xl font-bold text-gray-900">{{ formatCurrency(metrics.revenue) }}</p>
            </div>
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Combustível</p>
                <p class="mt-2 text-xl font-bold text-gray-900">{{ formatCurrency(metrics.fuel_cost) }}</p>
            </div>
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Manutenção</p>
                <p class="mt-2 text-xl font-bold text-gray-900">{{ formatCurrency(metrics.maintenance_cost) }}</p>
            </div>
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Total de Fretes</p>
                <p class="mt-2 text-xl font-bold text-gray-900">{{ metrics.freight_count ?? 0 }}</p>
            </div>
        </div>

        <!-- Recent Freights -->
        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
            <div class="border-b border-gray-100 px-6 py-4">
                <h2 class="text-sm font-semibold text-gray-900">Fretes Recentes</h2>
            </div>
            <div v-if="recentFreights.length === 0" class="px-6 py-10 text-center text-sm text-gray-400">
                Nenhum frete registrado para este veículo.
            </div>
            <ul v-else class="divide-y divide-gray-100">
                <li
                    v-for="f in recentFreights"
                    :key="f.id"
                    class="flex items-center justify-between px-6 py-3 hover:bg-gray-50"
                >
                    <div>
                        <Link :href="`/freights/${f.id}`" class="text-sm font-medium text-gray-900 hover:text-indigo-600">
                            Frete #{{ f.id }}
                        </Link>
                        <p class="text-xs text-gray-400">{{ f.client?.name ?? '—' }} &bull; {{ formatDate(f.created_at) }}</p>
                    </div>
                    <p class="text-sm font-semibold text-gray-900">{{ formatCurrency(f.freight_value) }}</p>
                </li>
            </ul>
        </div>
    </AuthenticatedLayout>
</template>
