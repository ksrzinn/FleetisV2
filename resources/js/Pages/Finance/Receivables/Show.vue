<script>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'

const STATUS_LABELS = {
    open:           'Em Aberto',
    partially_paid: 'Parcialmente Pago',
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
    pix:          'PIX',
    transferencia: 'Transferência',
    dinheiro:     'Dinheiro',
    cheque:       'Cheque',
    boleto:       'Boleto',
}

export default {
    components: { AuthenticatedLayout, Head, Link },

    props: {
        receivable: Object,
        payments:   Array,
    },

    setup(props) {
        const paymentForm = useForm({
            amount:  '',
            method:  '',
            notes:   '',
            paid_at: '',
        })
        return { paymentForm }
    },

    data() {
        return {
            statusLabels:  STATUS_LABELS,
            statusColors:  STATUS_COLORS,
            methodLabels:  METHOD_LABELS,
            showPaymentForm: false,
        }
    },

    computed: {
        remaining() {
            return Math.max(0, Number(this.receivable.amount_due) - Number(this.receivable.amount_paid))
        },
        canRecordPayment() {
            return this.receivable.status !== 'paid'
        },
    },

    methods: {
        submitPayment() {
            this.paymentForm.post(`/receivables/${this.receivable.id}/payments`, {
                onSuccess: () => {
                    this.showPaymentForm = false
                    this.paymentForm.reset()
                },
            })
        },
        formatCurrency(val) {
            if (val === null || val === undefined) return '—'
            return Number(val).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
        },
        formatDate(val) {
            if (!val) return '—'
            const [y, m, d] = val.split('T')[0].split('-')
            return `${d}/${m}/${y}`
        },
    },
}
</script>

<template>
    <Head :title="`Recebível #${receivable.id}`" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <Link href="/receivables" class="text-gray-400 hover:text-gray-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                        </svg>
                    </Link>
                    <h1 class="text-xl font-semibold text-gray-900">Recebível #{{ receivable.id }}</h1>
                    <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', statusColors[receivable.status]]">
                        {{ statusLabels[receivable.status] }}
                    </span>
                </div>
                <button v-if="canRecordPayment" @click="showPaymentForm = true"
                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-500 transition-colors">
                    Registrar Pagamento
                </button>
            </div>
        </template>

        <div class="mx-auto max-w-3xl space-y-6">

            <!-- Receivable info -->
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                <div class="px-6 py-5 border-b border-gray-100">
                    <h2 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Informações</h2>
                </div>
                <dl class="divide-y divide-gray-100">
                    <div class="grid grid-cols-3 gap-4 px-6 py-4 text-sm">
                        <dt class="font-medium text-gray-500">Cliente</dt>
                        <dd class="col-span-2 text-gray-900">{{ receivable.client?.name }}</dd>
                    </div>
                    <div v-if="receivable.freight_id" class="grid grid-cols-3 gap-4 px-6 py-4 text-sm">
                        <dt class="font-medium text-gray-500">Frete</dt>
                        <dd class="col-span-2">
                            <Link :href="`/freights/${receivable.freight_id}`" class="text-indigo-600 hover:text-indigo-500">
                                #{{ receivable.freight_id }}
                            </Link>
                        </dd>
                    </div>
                    <div class="grid grid-cols-3 gap-4 px-6 py-4 text-sm">
                        <dt class="font-medium text-gray-500">Valor Total</dt>
                        <dd class="col-span-2 font-semibold text-gray-900">{{ formatCurrency(receivable.amount_due) }}</dd>
                    </div>
                    <div class="grid grid-cols-3 gap-4 px-6 py-4 text-sm">
                        <dt class="font-medium text-gray-500">Recebido</dt>
                        <dd class="col-span-2 text-green-700">{{ formatCurrency(receivable.amount_paid) }}</dd>
                    </div>
                    <div class="grid grid-cols-3 gap-4 px-6 py-4 text-sm">
                        <dt class="font-medium text-gray-500">Saldo Devedor</dt>
                        <dd class="col-span-2 font-semibold" :class="remaining > 0 ? 'text-red-600' : 'text-gray-400'">
                            {{ formatCurrency(remaining) }}
                        </dd>
                    </div>
                    <div class="grid grid-cols-3 gap-4 px-6 py-4 text-sm">
                        <dt class="font-medium text-gray-500">Vencimento</dt>
                        <dd class="col-span-2 text-gray-900">{{ formatDate(receivable.due_date) }}</dd>
                    </div>
                </dl>
            </div>

            <!-- Payments list -->
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                <div class="px-6 py-5 border-b border-gray-100">
                    <h2 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Pagamentos</h2>
                </div>
                <div v-if="payments.length === 0" class="px-6 py-8 text-center text-sm text-gray-400">
                    Nenhum pagamento registrado.
                </div>
                <ul v-else class="divide-y divide-gray-100">
                    <li v-for="p in payments" :key="p.id" class="flex items-center justify-between px-6 py-4 text-sm">
                        <div>
                            <span class="font-medium text-gray-900">{{ formatCurrency(p.amount) }}</span>
                            <span class="ml-2 text-gray-500">via {{ methodLabels[p.method] ?? p.method }}</span>
                            <span v-if="p.notes" class="ml-2 text-gray-400 italic">— {{ p.notes }}</span>
                        </div>
                        <span class="text-gray-400">{{ formatDate(p.paid_at) }}</span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Payment modal -->
        <div v-if="showPaymentForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
            <div class="w-full max-w-md rounded-xl bg-white shadow-xl ring-1 ring-gray-200">
                <div class="px-6 py-5 border-b border-gray-100">
                    <h3 class="text-base font-semibold text-gray-900">Registrar Pagamento</h3>
                    <p class="mt-1 text-sm text-gray-500">Saldo devedor: {{ formatCurrency(remaining) }}</p>
                </div>
                <div class="px-6 py-5 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Valor <span class="text-red-500">*</span></label>
                        <input v-model="paymentForm.amount" type="number" step="0.01" :max="remaining" min="0.01"
                            placeholder="0,00"
                            class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                        <p v-if="paymentForm.errors.amount" class="mt-1.5 text-xs text-red-600">{{ paymentForm.errors.amount }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Método <span class="text-red-500">*</span></label>
                        <select v-model="paymentForm.method"
                            class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                            <option value="">Selecione...</option>
                            <option v-for="(label, key) in methodLabels" :key="key" :value="key">{{ label }}</option>
                        </select>
                        <p v-if="paymentForm.errors.method" class="mt-1.5 text-xs text-red-600">{{ paymentForm.errors.method }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Data do pagamento</label>
                        <input v-model="paymentForm.paid_at" type="datetime-local"
                            class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Observações</label>
                        <textarea v-model="paymentForm.notes" rows="2" placeholder="Opcional..."
                            class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                    </div>
                </div>
                <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3">
                    <button @click="showPaymentForm = false"
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
