<script>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'

const STATUS_LABELS = {
    to_start: 'A Iniciar',
    in_route: 'Em Rota',
    finished: 'Finalizado',
    awaiting_payment: 'Aguard. Pagamento',
    completed: 'Concluído',
}

const STATUS_COLORS = {
    to_start: 'bg-gray-100 text-gray-700',
    in_route: 'bg-blue-100 text-blue-700',
    finished: 'bg-yellow-100 text-yellow-700',
    awaiting_payment: 'bg-orange-100 text-orange-700',
    completed: 'bg-green-100 text-green-700',
}

export default {
    components: { AuthenticatedLayout, Head, Link },

    props: {
        freights: Object,
        filters: Object,
    },

    data() {
        return {
            search: this.filters?.search ?? '',
            status: this.filters?.status ?? '',
            statusLabels: STATUS_LABELS,
            statusColors: STATUS_COLORS,
        }
    },

    methods: {
        applyFilters() {
            router.get('/freights', { search: this.search, status: this.status }, {
                preserveState: true,
                replace: true,
            })
        },
        pricingLabel(model) {
            return model === 'fixed' ? 'Fixo' : 'Por Km'
        },
        ratePrice(freight) {
            const vehicleTypeId = freight.vehicle?.vehicle_type_id
            if (!vehicleTypeId) return null
            if (freight.pricing_model === 'fixed') {
                return freight.fixed_rate?.prices?.find(p => p.vehicle_type_id === vehicleTypeId)?.price ?? null
            }
            return freight.per_km_rate?.prices?.find(p => p.vehicle_type_id === vehicleTypeId)?.rate_per_km ?? null
        },
        formatCurrency(val) {
            if (val === null || val === undefined) return '—'
            return Number(val).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
        },
    },
}
</script>

<template>
    <Head title="Fretes" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold text-gray-900">Fretes</h1>
                <Link
                    href="/freights/create"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-500 transition-colors"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    Novo Frete
                </Link>
            </div>
        </template>

        <div class="mb-5 flex flex-wrap gap-3">
            <div class="relative">
                <svg class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" />
                </svg>
                <input v-model="search" type="text" placeholder="Buscar cliente..." @input="applyFilters"
                    class="rounded-lg border border-gray-300 py-2 pl-9 pr-4 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
            </div>
            <select v-model="status" @change="applyFilters"
                class="rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                <option value="">Todos os status</option>
                <option v-for="(label, key) in statusLabels" :key="key" :value="key">{{ label }}</option>
            </select>
        </div>

        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
            <table class="min-w-full divide-y divide-gray-100">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Cliente</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Veículo</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Motorista</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Tipo</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Status</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Valor</th>
                        <th class="px-6 py-3.5" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    <tr v-if="freights.data.length === 0">
                        <td colspan="7" class="px-6 py-10 text-center text-sm text-gray-400">Nenhum frete encontrado.</td>
                    </tr>
                    <tr v-for="freight in freights.data" :key="freight.id" class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4 text-sm text-gray-900">{{ freight.client?.name }}</td>
                        <td class="px-6 py-4 text-sm font-mono text-gray-700">{{ freight.vehicle?.license_plate }}</td>
                        <td class="px-6 py-4 text-sm text-gray-700">{{ freight.driver?.name ?? '—' }}</td>
                        <td class="px-6 py-4 text-sm text-gray-500">{{ pricingLabel(freight.pricing_model) }}</td>
                        <td class="px-6 py-4">
                            <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', statusColors[freight.status]]">
                                {{ statusLabels[freight.status] }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-700">
                            <template v-if="freight.freight_value">
                                {{ formatCurrency(freight.freight_value) }}
                            </template>
                            <template v-else-if="ratePrice(freight) !== null">
                                <span class="text-gray-500">{{ formatCurrency(ratePrice(freight)) }}</span>
                                <span v-if="freight.pricing_model === 'per_km'" class="text-xs text-gray-400">/km</span>
                            </template>
                            <template v-else>—</template>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <Link :href="`/freights/${freight.id}`" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">Ver</Link>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div v-if="freights.last_page > 1" class="border-t border-gray-100 px-6 py-4 flex items-center justify-between text-sm text-gray-500">
                <span>{{ freights.total }} fretes</span>
                <div class="flex gap-2">
                    <Link v-if="freights.prev_page_url" :href="freights.prev_page_url" class="rounded px-3 py-1 hover:bg-gray-100">Anterior</Link>
                    <Link v-if="freights.next_page_url" :href="freights.next_page_url" class="rounded px-3 py-1 hover:bg-gray-100">Próximo</Link>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
