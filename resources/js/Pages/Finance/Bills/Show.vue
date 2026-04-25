<script>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'

const BILL_TYPE_LABELS = {
    one_time:    'Único',
    installment: 'Parcelado',
    recurring:   'Recorrente',
}

const STATUS_LABELS = {
    open:           'Em Aberto',
    partially_paid: 'Parcial',
    paid:           'Pago',
    overdue:        'Vencido',
}

const STATUS_COLORS = {
    open:           'bg-blue-100 text-blue-700',
    partially_paid: 'bg-yellow-100 text-yellow-700',
    paid:           'bg-green-100 text-green-700',
    overdue:        'bg-red-100 text-red-700',
}

const METHOD_LABELS = {
    pix:           'PIX',
    transferencia: 'Transferência',
    dinheiro:      'Dinheiro',
    cheque:        'Cheque',
    boleto:        'Boleto',
}

export default {
    components: { AuthenticatedLayout, Head, Link },

    props: {
        bill:                Object,
        outstanding_balance: [String, Number],
        methods:             Array,
    },

    setup() {
        const paymentForm = useForm({
            amount:  '',
            method:  '',
            paid_at: '',
            notes:   '',
        })
        return { paymentForm }
    },

    data() {
        return {
            selectedInstallment: null,
            typeLabels:          BILL_TYPE_LABELS,
            statusLabels:        STATUS_LABELS,
            statusColors:        STATUS_COLORS,
            methodLabels:        METHOD_LABELS,
        }
    },

    computed: {
        methodOptions() {
            return this.methods.map(key => ({ key, label: METHOD_LABELS[key] ?? key }))
        },
    },

    methods: {
        openPaymentModal(installment) {
            this.selectedInstallment = installment
            this.paymentForm.reset()
        },

        submitPayment() {
            this.paymentForm.post(`/bill-installments/${this.selectedInstallment.id}/payments`, {
                onSuccess: () => {
                    this.selectedInstallment = null
                    this.paymentForm.reset()
                },
            })
        },

        remainingFor(installment) {
            const amount = parseFloat(installment.amount)
            const paid   = parseFloat(installment.paid_amount ?? 0)
            return Math.max(0, amount - paid)
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
    <Head :title="`Conta — ${bill.supplier}`" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <Link href="/bills" class="text-gray-400 hover:text-gray-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                        </svg>
                    </Link>
                    <h1 class="text-xl font-semibold text-gray-900">{{ bill.supplier }}</h1>
                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium bg-gray-100 text-gray-700">
                        {{ typeLabels[bill.bill_type] ?? bill.bill_type }}
                    </span>
                </div>
                <Link :href="`/bills/${bill.id}/edit`"
                    class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                    Editar
                </Link>
            </div>
        </template>

        <div class="mx-auto max-w-3xl space-y-6">

            <!-- Bill summary -->
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                <div class="px-6 py-5 border-b border-gray-100">
                    <h2 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Resumo</h2>
                </div>
                <dl class="divide-y divide-gray-100">
                    <div class="grid grid-cols-3 gap-4 px-6 py-4 text-sm">
                        <dt class="font-medium text-gray-500">Valor total</dt>
                        <dd class="col-span-2 font-semibold text-gray-900">{{ formatCurrency(bill.total_amount) }}</dd>
                    </div>
                    <div class="grid grid-cols-3 gap-4 px-6 py-4 text-sm">
                        <dt class="font-medium text-gray-500">Saldo devedor</dt>
                        <dd class="col-span-2 font-semibold" :class="Number(outstanding_balance) > 0 ? 'text-red-600' : 'text-gray-400'">
                            {{ formatCurrency(outstanding_balance) }}
                        </dd>
                    </div>
                    <div class="grid grid-cols-3 gap-4 px-6 py-4 text-sm">
                        <dt class="font-medium text-gray-500">Primeiro vencimento</dt>
                        <dd class="col-span-2 text-gray-900">{{ formatDate(bill.due_date) }}</dd>
                    </div>
                    <div v-if="bill.description" class="grid grid-cols-3 gap-4 px-6 py-4 text-sm">
                        <dt class="font-medium text-gray-500">Descrição</dt>
                        <dd class="col-span-2 text-gray-700">{{ bill.description }}</dd>
                    </div>
                </dl>
            </div>

            <!-- Installments -->
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                <div class="px-6 py-5 border-b border-gray-100">
                    <h2 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Parcelas</h2>
                </div>
                <table class="min-w-full divide-y divide-gray-100">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">#</th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Vencimento</th>
                            <th class="px-6 py-3.5 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Valor</th>
                            <th class="px-6 py-3.5 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Pago</th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Status</th>
                            <th class="px-6 py-3.5" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        <tr v-for="inst in bill.installments" :key="inst.id" class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 text-sm text-gray-700">{{ inst.sequence }}</td>
                            <td class="px-6 py-4 text-sm text-gray-700">{{ formatDate(inst.due_date) }}</td>
                            <td class="px-6 py-4 text-sm text-right font-medium text-gray-900">{{ formatCurrency(inst.amount) }}</td>
                            <td class="px-6 py-4 text-sm text-right text-gray-700">{{ formatCurrency(inst.paid_amount) }}</td>
                            <td class="px-6 py-4">
                                <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', statusColors[inst.status] ?? 'bg-gray-100 text-gray-700']">
                                    {{ statusLabels[inst.status] ?? inst.status }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button v-if="inst.status !== 'paid'"
                                    @click="openPaymentModal(inst)"
                                    class="text-sm font-medium text-indigo-600 hover:text-indigo-500">
                                    Pagar
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Payment modal -->
        <div v-if="selectedInstallment" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
            <div class="w-full max-w-md rounded-xl bg-white shadow-xl ring-1 ring-gray-200">
                <div class="px-6 py-5 border-b border-gray-100">
                    <h3 class="text-base font-semibold text-gray-900">Registrar Pagamento</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        Parcela {{ selectedInstallment.sequence }} —
                        Saldo: {{ formatCurrency(remainingFor(selectedInstallment)) }}
                    </p>
                </div>
                <div class="px-6 py-5 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Valor <span class="text-red-500">*</span></label>
                        <input v-model="paymentForm.amount" type="number" step="0.01" min="0.01"
                            :max="remainingFor(selectedInstallment)" placeholder="0,00"
                            class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                        <p v-if="paymentForm.errors.amount" class="mt-1.5 text-xs text-red-600">{{ paymentForm.errors.amount }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Método <span class="text-red-500">*</span></label>
                        <select v-model="paymentForm.method"
                            class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                            <option value="">Selecione...</option>
                            <option v-for="opt in methodOptions" :key="opt.key" :value="opt.key">{{ opt.label }}</option>
                        </select>
                        <p v-if="paymentForm.errors.method" class="mt-1.5 text-xs text-red-600">{{ paymentForm.errors.method }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Data do pagamento <span class="text-red-500">*</span></label>
                        <input v-model="paymentForm.paid_at" type="date"
                            class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                        <p v-if="paymentForm.errors.paid_at" class="mt-1.5 text-xs text-red-600">{{ paymentForm.errors.paid_at }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Observações</label>
                        <textarea v-model="paymentForm.notes" rows="2" placeholder="Opcional..."
                            class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                    </div>
                </div>
                <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3">
                    <button @click="selectedInstallment = null"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Cancelar
                    </button>
                    <button :disabled="paymentForm.processing" @click="submitPayment"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500 disabled:opacity-60">
                        {{ paymentForm.processing ? 'Salvando...' : 'Confirmar' }}
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
