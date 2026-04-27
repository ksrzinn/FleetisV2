<script>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link } from '@inertiajs/vue3'

export default {
    components: { AuthenticatedLayout, Head, Link },

    props: {
        vehicles:                      { type: Array,          default: () => [] },
        freightsReceivableOutstanding: { type: [String, Number], default: '0' },
    },

    methods: {
        formatCurrency(val) {
            if (val === null || val === undefined) return '—'
            return Number(val).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
        },
    },
}
</script>

<template>
    <Head title="Relatório de Veículos" />
    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900">Relatório de Veículos</h1>
        </template>

        <!-- Fretes a Receber KPI -->
        <div class="mb-6">
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 max-w-xs">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Fretes a Receber (em aberto)</p>
                <p class="mt-2 text-2xl font-bold text-blue-600">{{ formatCurrency(freightsReceivableOutstanding) }}</p>
                <p class="mt-1 text-xs text-gray-400">Recebíveis de fretes não totalmente pagos</p>
            </div>
        </div>

        <!-- Vehicles table -->
        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">Veículo</th>
                        <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500">Receita</th>
                        <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500">Combustível</th>
                        <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500">Manutenção</th>
                        <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500">Fretes</th>
                        <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500">Km Total</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-if="vehicles.length === 0">
                        <td colspan="7" class="px-6 py-10 text-center text-sm text-gray-400">Nenhum veículo registrado.</td>
                    </tr>
                    <tr
                        v-for="v in vehicles"
                        :key="v.id"
                        class="hover:bg-gray-50 transition-colors"
                    >
                        <td class="px-6 py-4">
                            <p class="text-sm font-medium text-gray-900">{{ v.license_plate }}</p>
                            <p class="text-xs text-gray-400">{{ v.brand }} {{ v.model }}</p>
                        </td>
                        <td class="px-6 py-4 text-right text-sm text-gray-900">{{ formatCurrency(v.revenue) }}</td>
                        <td class="px-6 py-4 text-right text-sm text-gray-900">{{ formatCurrency(v.fuel_cost) }}</td>
                        <td class="px-6 py-4 text-right text-sm text-gray-900">{{ formatCurrency(v.maintenance_cost) }}</td>
                        <td class="px-6 py-4 text-right text-sm text-gray-900">{{ v.freight_count }}</td>
                        <td class="px-6 py-4 text-right text-sm text-gray-900">{{ v.total_km ? `${Number(v.total_km).toLocaleString('pt-BR')} km` : '—' }}</td>
                        <td class="px-6 py-4 text-right">
                            <Link
                                :href="`/reports/vehicles/${v.id}`"
                                class="text-xs font-medium text-indigo-600 hover:text-indigo-500"
                            >
                                Detalhar &rarr;
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AuthenticatedLayout>
</template>
