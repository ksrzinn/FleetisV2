<script>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'

const FREIGHT_STATUS_LABELS = {
    to_start:         'A Iniciar',
    in_route:         'Em Rota',
    finished:         'Finalizado',
    awaiting_payment: 'Aguardando Pagamento',
    completed:        'Concluído',
}

const FREIGHT_STATUS_COLORS = {
    to_start:         'bg-gray-100 text-gray-700',
    in_route:         'bg-blue-100 text-blue-700',
    finished:         'bg-yellow-100 text-yellow-700',
    awaiting_payment: 'bg-orange-100 text-orange-700',
    completed:        'bg-green-100 text-green-700',
}

export default {
    components: { AuthenticatedLayout, Head, Link },

    props: {
        revenueSeries:   { type: Array, default: () => [] },
        expenseSeries:   { type: Array, default: () => [] },
        arOutstanding:   { type: [String, Number], default: '0' },
        apOutstanding:   { type: [String, Number], default: '0' },
        freightByStatus: { type: Object, default: () => ({}) },
        recentFreights:  { type: Array, default: () => [] },
        period:          { type: String, default: 'monthly' },
    },

    data() {
        return {
            statusLabels: FREIGHT_STATUS_LABELS,
            statusColors: FREIGHT_STATUS_COLORS,
        }
    },

    computed: {
        totalRevenue() {
            return this.revenueSeries.reduce((sum, p) => sum + Number(p.y ?? 0), 0)
        },
        totalExpenses() {
            return this.expenseSeries.reduce((sum, p) => sum + Number(p.y ?? 0), 0)
        },
        netMargin() {
            return this.totalRevenue - this.totalExpenses
        },
        totalFreights() {
            return Object.values(this.freightByStatus).reduce((s, v) => s + v, 0)
        },
        periodLabel() {
            return { monthly: 'Mensal', weekly: 'Semanal', daily: 'Diário' }[this.period] ?? 'Mensal'
        },
    },

    methods: {
        changePeriod(p) {
            router.get('/dashboard', { period: p }, { preserveState: true, replace: true })
        },
        formatCurrency(val) {
            if (val === null || val === undefined) return '—'
            return Number(val).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
        },
        formatDate(val) {
            if (!val) return '—'
            return new Date(val).toLocaleDateString('pt-BR')
        },
        freightStatusCount(status) {
            return this.freightByStatus[status] ?? 0
        },
    },
}
</script>

<template>
    <Head title="Dashboard" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold text-gray-900">Dashboard</h1>
                <div class="flex gap-1 rounded-lg border border-gray-200 bg-white p-1 text-sm shadow-sm">
                    <button
                        v-for="p in ['daily', 'weekly', 'monthly']"
                        :key="p"
                        @click="changePeriod(p)"
                        :class="[
                            'rounded-md px-3 py-1.5 font-medium transition-colors',
                            period === p
                                ? 'bg-indigo-600 text-white shadow-sm'
                                : 'text-gray-500 hover:text-gray-700',
                        ]"
                    >
                        {{ { daily: 'Diário', weekly: 'Semanal', monthly: 'Mensal' }[p] }}
                    </button>
                </div>
            </div>
        </template>

        <!-- KPI Cards -->
        <div class="mb-6 grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4">
            <!-- Revenue -->
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Receita ({{ periodLabel }})</p>
                <p class="mt-2 text-2xl font-bold text-gray-900">{{ formatCurrency(totalRevenue) }}</p>
                <p class="mt-1 text-xs text-gray-400">Fretes concluídos no período</p>
            </div>

            <!-- Expenses -->
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Despesas ({{ periodLabel }})</p>
                <p class="mt-2 text-2xl font-bold text-gray-900">{{ formatCurrency(totalExpenses) }}</p>
                <p class="mt-1 text-xs text-gray-400">Despesas, combustível e manutenção</p>
            </div>

            <!-- Net Margin -->
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Margem ({{ periodLabel }})</p>
                <p
                    class="mt-2 text-2xl font-bold"
                    :class="netMargin >= 0 ? 'text-green-600' : 'text-red-600'"
                >
                    {{ formatCurrency(netMargin) }}
                </p>
                <p class="mt-1 text-xs text-gray-400">Receita menos despesas</p>
            </div>

            <!-- Total Freights -->
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Total de Fretes</p>
                <p class="mt-2 text-2xl font-bold text-gray-900">{{ totalFreights }}</p>
                <p class="mt-1 text-xs text-gray-400">Em todos os status</p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- AR / AP Outstanding -->
            <div class="space-y-4">
                <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">A Receber (aberto)</p>
                    <p class="mt-2 text-xl font-bold text-blue-600">{{ formatCurrency(arOutstanding) }}</p>
                    <Link href="/receivables" class="mt-3 inline-block text-xs font-medium text-indigo-600 hover:text-indigo-500">
                        Ver contas a receber &rarr;
                    </Link>
                </div>

                <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">A Pagar (aberto)</p>
                    <p class="mt-2 text-xl font-bold text-red-600">{{ formatCurrency(apOutstanding) }}</p>
                    <Link href="/bills" class="mt-3 inline-block text-xs font-medium text-indigo-600 hover:text-indigo-500">
                        Ver contas a pagar &rarr;
                    </Link>
                </div>

                <!-- Freight by status breakdown -->
                <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <p class="mb-4 text-xs font-semibold uppercase tracking-wide text-gray-400">Fretes por Status</p>
                    <ul class="space-y-2">
                        <li
                            v-for="(label, status) in statusLabels"
                            :key="status"
                            class="flex items-center justify-between text-sm"
                        >
                            <span class="flex items-center gap-2">
                                <span :class="['inline-flex rounded-full px-2 py-0.5 text-xs font-medium', statusColors[status]]">
                                    {{ label }}
                                </span>
                            </span>
                            <span class="font-semibold text-gray-900">{{ freightStatusCount(status) }}</span>
                        </li>
                    </ul>
                    <div v-if="totalFreights === 0" class="text-center text-sm text-gray-400 py-2">
                        Nenhum frete registrado.
                    </div>
                </div>
            </div>

            <!-- Recent Freights -->
            <div class="lg:col-span-2">
                <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                        <h2 class="text-sm font-semibold text-gray-900">Fretes Recentes</h2>
                        <Link href="/freights" class="text-xs font-medium text-indigo-600 hover:text-indigo-500">
                            Ver todos &rarr;
                        </Link>
                    </div>

                    <div v-if="recentFreights.length === 0" class="px-6 py-10 text-center text-sm text-gray-400">
                        Nenhum frete registrado ainda.
                    </div>

                    <ul v-else class="divide-y divide-gray-100">
                        <li
                            v-for="f in recentFreights"
                            :key="f.id"
                            class="flex items-center justify-between px-6 py-3 hover:bg-gray-50 transition-colors"
                        >
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <Link
                                        :href="`/freights/${f.id}`"
                                        class="text-sm font-medium text-gray-900 hover:text-indigo-600"
                                    >
                                        Frete #{{ f.id }}
                                    </Link>
                                    <span :class="['inline-flex rounded-full px-2 py-0.5 text-xs font-medium', statusColors[f.status] ?? 'bg-gray-100 text-gray-700']">
                                        {{ statusLabels[f.status] ?? f.status }}
                                    </span>
                                </div>
                                <p class="mt-0.5 text-xs text-gray-400 truncate">
                                    {{ f.client?.name ?? '—' }} &bull; {{ f.vehicle?.license_plate ?? '—' }}
                                </p>
                            </div>
                            <div class="ml-4 shrink-0 text-right">
                                <p class="text-sm font-semibold text-gray-900">
                                    {{ f.freight_value ? formatCurrency(f.freight_value) : '—' }}
                                </p>
                                <p class="text-xs text-gray-400">{{ formatDate(f.created_at) }}</p>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
