<script>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'

export default {
    components: { AuthenticatedLayout, Head, Link },

    props: {
        bill: { type: Object, default: null },
    },

    setup(props) {
        const form = useForm({
            supplier:           props.bill?.supplier ?? '',
            description:        props.bill?.description ?? '',
            bill_type:          props.bill?.bill_type ?? 'one_time',
            total_amount:       props.bill?.total_amount ?? '',
            due_date:           props.bill?.due_date?.split('T')[0] ?? '',
            recurrence_cadence: props.bill?.recurrence_cadence ?? '',
            recurrence_day:     props.bill?.recurrence_day ?? '',
            recurrence_end:     props.bill?.recurrence_end?.split('T')[0] ?? '',
            installment_count:  props.bill?.installment_count ?? '',
        })
        return { form }
    },

    computed: {
        isEdit() {
            return !!this.bill
        },
        showCadenceFields() {
            return this.form.bill_type === 'recurring' || this.form.bill_type === 'installment'
        },
        showDayField() {
            return this.showCadenceFields && (this.form.recurrence_cadence === 'monthly' || this.form.recurrence_cadence === 'yearly')
        },
        showEndDate() {
            return this.form.bill_type === 'recurring'
        },
        showInstallmentCount() {
            return this.form.bill_type === 'installment'
        },
        totalAmountLabel() {
            return this.form.bill_type === 'recurring' ? 'Valor por parcela' : 'Valor total'
        },
    },

    methods: {
        submit() {
            if (this.isEdit) {
                this.form.put(`/bills/${this.bill.id}`)
            } else {
                this.form.post('/bills')
            }
        },
    },
}
</script>

<template>
    <Head :title="isEdit ? 'Editar Conta' : 'Nova Conta a Pagar'" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link href="/bills" class="text-gray-400 hover:text-gray-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                </Link>
                <h1 class="text-xl font-semibold text-gray-900">{{ isEdit ? 'Editar Conta' : 'Nova Conta a Pagar' }}</h1>
            </div>
        </template>

        <div class="mx-auto max-w-2xl">
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                <div class="px-6 py-5 border-b border-gray-100">
                    <h2 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Dados da Conta</h2>
                </div>

                <form @submit.prevent="submit" class="px-6 py-5 space-y-5">

                    <!-- Supplier -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            Fornecedor <span class="text-red-500">*</span>
                        </label>
                        <input v-model="form.supplier" type="text" placeholder="Nome do fornecedor..."
                            class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                        <p v-if="form.errors.supplier" class="mt-1.5 text-xs text-red-600">{{ form.errors.supplier }}</p>
                    </div>

                    <!-- Description -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Descrição</label>
                        <textarea v-model="form.description" rows="2" placeholder="Observações opcionais..."
                            class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                        <p v-if="form.errors.description" class="mt-1.5 text-xs text-red-600">{{ form.errors.description }}</p>
                    </div>

                    <!-- Bill type -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            Tipo <span class="text-red-500">*</span>
                        </label>
                        <div class="flex gap-3">
                            <label v-for="(label, key) in { one_time: 'Único', installment: 'Parcelado', recurring: 'Recorrente' }"
                                :key="key"
                                class="flex-1 cursor-pointer rounded-lg border px-4 py-3 text-center text-sm font-medium transition-colors"
                                :class="form.bill_type === key
                                    ? 'border-indigo-500 bg-indigo-50 text-indigo-700'
                                    : 'border-gray-300 text-gray-700 hover:bg-gray-50'">
                                <input v-model="form.bill_type" type="radio" :value="key" class="sr-only" />
                                {{ label }}
                            </label>
                        </div>
                        <p v-if="form.errors.bill_type" class="mt-1.5 text-xs text-red-600">{{ form.errors.bill_type }}</p>
                    </div>

                    <!-- Amount + Due date -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                {{ totalAmountLabel }} <span class="text-red-500">*</span>
                            </label>
                            <input v-model="form.total_amount" type="number" step="0.01" min="0.01" placeholder="0,00"
                                class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                            <p v-if="form.errors.total_amount" class="mt-1.5 text-xs text-red-600">{{ form.errors.total_amount }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                Primeiro vencimento <span class="text-red-500">*</span>
                            </label>
                            <input v-model="form.due_date" type="date"
                                class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                            <p v-if="form.errors.due_date" class="mt-1.5 text-xs text-red-600">{{ form.errors.due_date }}</p>
                        </div>
                    </div>

                    <!-- Installment count (installment type only) -->
                    <div v-if="showInstallmentCount">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            Número de parcelas <span class="text-red-500">*</span>
                        </label>
                        <input v-model="form.installment_count" type="number" min="2" max="360" placeholder="Ex: 12"
                            class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                        <p v-if="form.errors.installment_count" class="mt-1.5 text-xs text-red-600">{{ form.errors.installment_count }}</p>
                    </div>

                    <!-- Cadence fields (recurring + installment) -->
                    <template v-if="showCadenceFields">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                    Periodicidade <span class="text-red-500">*</span>
                                </label>
                                <select v-model="form.recurrence_cadence"
                                    class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                    <option value="">Selecione...</option>
                                    <option value="weekly">Semanal</option>
                                    <option value="biweekly">Quinzenal</option>
                                    <option value="monthly">Mensal</option>
                                    <option value="yearly">Anual</option>
                                </select>
                                <p v-if="form.errors.recurrence_cadence" class="mt-1.5 text-xs text-red-600">{{ form.errors.recurrence_cadence }}</p>
                            </div>

                            <div v-if="showDayField">
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                    Dia do vencimento <span class="text-red-500">*</span>
                                </label>
                                <input v-model="form.recurrence_day" type="number" min="1" max="28" placeholder="Ex: 10"
                                    class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                                <p class="mt-1 text-xs text-gray-400">Máximo dia 28 (evita variação mensal).</p>
                                <p v-if="form.errors.recurrence_day" class="mt-1.5 text-xs text-red-600">{{ form.errors.recurrence_day }}</p>
                            </div>
                        </div>

                        <!-- Recurrence end (recurring only) -->
                        <div v-if="showEndDate">
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Data final (opcional)</label>
                            <input v-model="form.recurrence_end" type="date"
                                class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                            <p class="mt-1 text-xs text-gray-400">Deixe em branco para recorrência indefinida.</p>
                            <p v-if="form.errors.recurrence_end" class="mt-1.5 text-xs text-red-600">{{ form.errors.recurrence_end }}</p>
                        </div>
                    </template>

                    <div class="flex justify-end gap-3 pt-2 border-t border-gray-100">
                        <Link href="/bills"
                            class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Cancelar
                        </Link>
                        <button type="submit" :disabled="form.processing"
                            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-500 disabled:opacity-60 transition-colors">
                            {{ form.processing ? 'Salvando...' : (isEdit ? 'Salvar alterações' : 'Criar conta') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
