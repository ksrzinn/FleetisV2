<script>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'

const STATUS_LABELS = {
    open:            'Em Aberto',
    partially_paid:  'Parcialmente Pago',
    paid:            'Pago',
    overdue:         'Vencido',
}

const STATUS_COLORS = {
    open:           'bg-blue-100 text-blue-700',
    partially_paid: 'bg-yellow-100 text-yellow-700',
    paid:           'bg-green-100 text-green-700',
    overdue:        'bg-red-100 text-red-700',
}

export default {
    components: { AuthenticatedLayout, Head, Link },

    props: {
        receivables: Object,
        filters:     Object,
    },

    data() {
        return {
            status:       this.filters?.status ?? '',
            client_id:    this.filters?.client_id ?? '',
            due_date_from: this.filters?.due_date_from ?? '',
            due_date_to:   this.filters?.due_date_to ?? '',
            statusLabels:  STATUS_LABELS,
            statusColors:  STATUS_COLORS,
        }
    },

    methods: {
        applyFilters() {
            router.get('/receivables', {
                status:       this.status,
                client_id:    this.client_id,
                due_date_from: this.due_date_from,
                due_date_to:   this.due_date_to,
            }, { preserveState: true, replace: true })
        },
        formatCurrency(val) {
            if (val === null || val === undefined) return '—'
            return Number(val).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
        },
        formatDate(val) {
            if (!val) return '—'
            const [y, m, d] = val.split('-')
            return `${d}/${m}/${y}`
        },
        remaining(r) {
            return Math.max(0, Number(r.amount_due) - Number(r.amount_paid))
        },
    },
}
</script>

<template>
    <Head title="Contas a Receber" />
    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900">Contas a Receber</h1>
        </template>

        <!-- Filters -->
        <div class="mb-5 flex flex-wrap gap-3">
            <select v-model="status" @change="applyFilters"
                class="rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                <option value="">Todos os status</option>
                <option v-for="(label, key) in statusLabels" :key="key" :value="key">{{ label }}</option>
            </select>
            <input v-model="due_date_from" type="date" @change="applyFilters"
                class="rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                placeholder="Venc. de" />
            <input v-model="due_date_to" type="date" @change="applyFilters"
                class="rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                placeholder="Venc. até" />
        </div>

        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
            <table class="min-w-full divide-y divide-gray-100">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Cliente</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Frete</th>
                        <th class="px-6 py-3.5 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Valor</th>
                        <th class="px-6 py-3.5 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Recebido</th>
                        <th class="px-6 py-3.5 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Saldo</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Vencimento</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Status</th>
                        <th class="px-6 py-3.5" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    <tr v-if="receivables.data.length === 0">
                        <td colspan="8" class="px-6 py-10 text-center text-sm text-gray-400">Nenhuma conta a receber encontrada.</td>
                    </tr>
                    <tr v-for="r in receivables.data" :key="r.id" class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4 text-sm text-gray-900">{{ r.client?.name }}</td>
                        <td class="px-6 py-4 text-sm text-gray-500">{{ r.freight_id ? `#${r.freight_id}` : '—' }}</td>
                        <td class="px-6 py-4 text-sm text-right text-gray-900">{{ formatCurrency(r.amount_due) }}</td>
                        <td class="px-6 py-4 text-sm text-right text-gray-700">{{ formatCurrency(r.amount_paid) }}</td>
                        <td class="px-6 py-4 text-sm text-right font-medium" :class="remaining(r) > 0 ? 'text-red-600' : 'text-gray-400'">
                            {{ formatCurrency(remaining(r)) }}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-700">{{ formatDate(r.due_date) }}</td>
                        <td class="px-6 py-4">
                            <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', statusColors[r.status]]">
                                {{ statusLabels[r.status] }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <Link :href="`/receivables/${r.id}`" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">Ver</Link>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div v-if="receivables.last_page > 1" class="border-t border-gray-100 px-6 py-4 flex items-center justify-between text-sm text-gray-500">
                <span>{{ receivables.total }} registros</span>
                <div class="flex gap-2">
                    <Link v-if="receivables.prev_page_url" :href="receivables.prev_page_url" class="rounded px-3 py-1 hover:bg-gray-100">Anterior</Link>
                    <Link v-if="receivables.next_page_url" :href="receivables.next_page_url" class="rounded px-3 py-1 hover:bg-gray-100">Próximo</Link>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
