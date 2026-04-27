<script>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'

const BILL_TYPE_LABELS = {
    one_time:    'Único',
    installment: 'Parcelado',
    recurring:   'Recorrente',
}

const BILL_TYPE_COLORS = {
    one_time:    'bg-gray-100 text-gray-700',
    installment: 'bg-blue-100 text-blue-700',
    recurring:   'bg-purple-100 text-purple-700',
}

export default {
    components: { AuthenticatedLayout, Head, Link },

    props: {
        bills:   Object,
        filters: Object,
    },

    setup() {
        const deleteForm = useForm({})
        return { deleteForm }
    },

    data() {
        return {
            billType:     this.filters?.bill_type    ?? '',
            supplier:     this.filters?.supplier     ?? '',
            dueDateFrom:  this.filters?.due_date_from ?? '',
            dueDateTo:    this.filters?.due_date_to   ?? '',
            deleteTarget: null,
            typeLabels:   BILL_TYPE_LABELS,
            typeColors:   BILL_TYPE_COLORS,
        }
    },

    methods: {
        applyFilters() {
            router.get('/bills', {
                bill_type:     this.billType    || undefined,
                supplier:      this.supplier    || undefined,
                due_date_from: this.dueDateFrom || undefined,
                due_date_to:   this.dueDateTo   || undefined,
            }, { preserveState: true, replace: true })
        },
        activePreset() {
            const pad = (n) => String(n).padStart(2, '0')
            const fmt = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`

            const now   = new Date()
            const today = fmt(now)

            const dayOfWeek  = (now.getDay() + 6) % 7
            const monday     = new Date(now); monday.setDate(now.getDate() - dayOfWeek)
            const sunday     = new Date(monday); sunday.setDate(monday.getDate() + 6)
            const weekStart  = fmt(monday)
            const weekEnd    = fmt(sunday)

            const monthStart = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-01`
            const lastDay    = new Date(now.getFullYear(), now.getMonth() + 1, 0)
            const monthEnd   = fmt(lastDay)

            if (this.dueDateFrom === today      && this.dueDateTo === today)    return 'today'
            if (this.dueDateFrom === weekStart  && this.dueDateTo === weekEnd)  return 'week'
            if (this.dueDateFrom === monthStart && this.dueDateTo === monthEnd) return 'month'
            return null
        },
        applyPreset(preset) {
            const pad = (n) => String(n).padStart(2, '0')
            const fmt = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
            const now = new Date()

            if (this.activePreset() === preset) {
                this.dueDateFrom = ''
                this.dueDateTo   = ''
                this.applyFilters()
                return
            }

            if (preset === 'today') {
                const today = fmt(now)
                this.dueDateFrom = today
                this.dueDateTo   = today
            } else if (preset === 'week') {
                const dayOfWeek = (now.getDay() + 6) % 7
                const monday    = new Date(now); monday.setDate(now.getDate() - dayOfWeek)
                const sunday    = new Date(monday); sunday.setDate(monday.getDate() + 6)
                this.dueDateFrom = fmt(monday)
                this.dueDateTo   = fmt(sunday)
            } else if (preset === 'month') {
                const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0)
                this.dueDateFrom = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-01`
                this.dueDateTo   = fmt(lastDay)
            }

            this.applyFilters()
        },
        progressLabel(bill) {
            const paid  = bill.paid_installments_count
            const total = bill.installments_count
            if (bill.bill_type === 'recurring' && !bill.recurrence_end) {
                return `${paid}/∞`
            }
            return `${paid}/${total}`
        },
        confirmDelete(bill) {
            this.deleteTarget = bill
        },
        executeDelete() {
            this.deleteForm.delete(`/bills/${this.deleteTarget.id}`, {
                onSuccess: () => { this.deleteTarget = null },
            })
        },
        formatCurrency(val) {
            if (val === null || val === undefined) return '—'
            return Number(val).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
        },
        formatDate(val) {
            if (!val) return '—'
            const [y, m, d] = (val.split('T')[0] || val).split('-')
            return `${d}/${m}/${y}`
        },
    },
}
</script>

<template>
    <Head title="Contas a Pagar" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold text-gray-900">Contas a Pagar</h1>
                <Link href="/bills/create" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-500 transition-colors">
                    + Nova Conta
                </Link>
            </div>
        </template>

        <!-- Filters -->
        <div class="mb-5 flex flex-wrap gap-3 items-center">
            <select v-model="billType" @change="applyFilters"
                class="rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                <option value="">Todos os tipos</option>
                <option v-for="(label, key) in typeLabels" :key="key" :value="key">{{ label }}</option>
            </select>
            <input v-model="supplier" type="text" placeholder="Buscar fornecedor..." @input="applyFilters"
                class="rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
            <input v-model="dueDateFrom" type="date" @change="applyFilters"
                class="rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
            <input v-model="dueDateTo" type="date" @change="applyFilters"
                class="rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
            <button @click="applyPreset('today')"
                :class="activePreset() === 'today'
                    ? 'rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white shadow-sm'
                    : 'rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50'">
                Hoje
            </button>
            <button @click="applyPreset('week')"
                :class="activePreset() === 'week'
                    ? 'rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white shadow-sm'
                    : 'rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50'">
                Esta Semana
            </button>
            <button @click="applyPreset('month')"
                :class="activePreset() === 'month'
                    ? 'rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white shadow-sm'
                    : 'rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50'">
                Este Mês
            </button>
        </div>

        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
            <table class="min-w-full divide-y divide-gray-100">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Fornecedor</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Tipo</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Vencimento</th>
                        <th class="px-6 py-3.5 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Valor</th>
                        <th class="px-6 py-3.5 text-center text-xs font-semibold uppercase tracking-wide text-gray-500">Parcelas</th>
                        <th class="px-6 py-3.5" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    <tr v-if="!bills.data.length">
                        <td colspan="6" class="px-6 py-10 text-center text-sm text-gray-400">Nenhuma conta encontrada.</td>
                    </tr>
                    <tr v-for="b in bills.data" :key="b.id" class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4">
                            <p class="text-sm font-medium text-gray-900">{{ b.supplier }}</p>
                            <p v-if="b.description" class="text-xs text-gray-400 truncate max-w-xs">{{ b.description }}</p>
                        </td>
                        <td class="px-6 py-4">
                            <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', typeColors[b.bill_type] ?? 'bg-gray-100 text-gray-700']">
                                {{ typeLabels[b.bill_type] ?? b.bill_type }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-700">{{ formatDate(b.due_date) }}</td>
                        <td class="px-6 py-4 text-sm text-right font-medium text-gray-900">{{ formatCurrency(b.total_amount) }}</td>
                        <td class="px-6 py-4 text-sm text-center text-gray-700">{{ progressLabel(b) }}</td>
                        <td class="px-6 py-4 text-right space-x-3">
                            <Link :href="`/bills/${b.id}`" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">Ver</Link>
                            <Link :href="`/bills/${b.id}/edit`" class="text-sm font-medium text-gray-600 hover:text-gray-500">Editar</Link>
                            <button @click="confirmDelete(b)" class="text-sm font-medium text-red-600 hover:text-red-500">Excluir</button>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div v-if="bills.last_page > 1" class="border-t border-gray-100 px-6 py-4 flex items-center justify-between text-sm text-gray-500">
                <span>{{ bills.total }} registros</span>
                <div class="flex gap-2">
                    <Link v-if="bills.prev_page_url" :href="bills.prev_page_url" class="rounded px-3 py-1 hover:bg-gray-100">Anterior</Link>
                    <Link v-if="bills.next_page_url" :href="bills.next_page_url" class="rounded px-3 py-1 hover:bg-gray-100">Próximo</Link>
                </div>
            </div>
        </div>

        <!-- Delete confirmation modal -->
        <div v-if="deleteTarget" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
            <div class="w-full max-w-sm rounded-xl bg-white shadow-xl ring-1 ring-gray-200 p-6">
                <h3 class="text-base font-semibold text-gray-900">Excluir conta?</h3>
                <p class="mt-2 text-sm text-gray-500">Esta ação não pode ser desfeita. Contas com pagamentos não podem ser excluídas.</p>
                <div class="mt-5 flex justify-end gap-3">
                    <button @click="deleteTarget = null" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancelar</button>
                    <button @click="executeDelete" :disabled="deleteForm.processing" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-500 disabled:opacity-60">Excluir</button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
