<script>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'

const TYPE_LABELS = {
    percentage:        'Percentual',
    fixed_per_freight: 'Fixo por Frete',
    monthly_salary:    'Salário Mensal',
}

export default {
    components: { AuthenticatedLayout, Head, Link },

    props: {
        driver:  Object,
        active:  Array,
        history: Array,
        canEdit: Boolean,
    },

    setup() {
        const form = useForm({
            type:           'percentage',
            percentage:     '',
            fixed_amount:   '',
            monthly_salary: '',
            effective_from: new Date().toISOString().slice(0, 10),
        })
        return { form }
    },

    computed: {
        amountField() {
            const map = {
                percentage:        'percentage',
                fixed_per_freight: 'fixed_amount',
                monthly_salary:    'monthly_salary',
            }
            return map[this.form.type]
        },

        amountLabel() {
            const map = {
                percentage:        'Percentual (%)',
                fixed_per_freight: 'Valor por Frete (R$)',
                monthly_salary:    'Salário Mensal (R$)',
            }
            return map[this.form.type]
        },
    },

    methods: {
        typeLabel(type) {
            return TYPE_LABELS[type] ?? type
        },

        activeAmount(comp) {
            if (comp.type === 'percentage') return `${comp.percentage}%`
            if (comp.type === 'fixed_per_freight') return `R$ ${Number(comp.fixed_amount).toFixed(2)}`
            return `R$ ${Number(comp.monthly_salary).toFixed(2)}`
        },

        submit() {
            const payload = {
                type:           this.form.type,
                effective_from: this.form.effective_from,
                percentage:     this.form.type === 'percentage' ? this.form.percentage : undefined,
                fixed_amount:   this.form.type === 'fixed_per_freight' ? this.form.fixed_amount : undefined,
                monthly_salary: this.form.type === 'monthly_salary' ? this.form.monthly_salary : undefined,
            }
            this.form.transform(() => payload).post(`/drivers/${this.driver.id}/compensations`)
        },
    },
}
</script>

<template>
    <Head :title="`Remunerações — ${driver.name}`" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link href="/drivers" class="text-gray-400 hover:text-gray-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                </Link>
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">Remunerações</h1>
                    <p class="text-sm text-gray-500">{{ driver.name }}</p>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-3xl space-y-6">

            <!-- Active compensations -->
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                <div class="border-b border-gray-100 px-6 py-5">
                    <h2 class="text-sm font-medium uppercase tracking-wide text-gray-500">Remunerações Ativas</h2>
                </div>
                <div class="px-6 py-5">
                    <div v-if="active.length" class="space-y-3">
                        <div
                            v-for="comp in active"
                            :key="comp.id"
                            class="flex items-center justify-between rounded-lg border border-gray-100 bg-gray-50 px-4 py-3.5"
                        >
                            <div class="flex items-center gap-3">
                                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-100 text-indigo-600">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </span>
                                <div>
                                    <p class="text-sm font-medium text-gray-800">{{ typeLabel(comp.type) }}</p>
                                    <p class="text-xs text-gray-500">desde {{ comp.effective_from }}</p>
                                </div>
                            </div>
                            <span class="text-base font-semibold text-gray-900">{{ activeAmount(comp) }}</span>
                        </div>
                    </div>
                    <div v-else class="py-6 text-center">
                        <p class="text-sm text-gray-500">Nenhuma remuneração ativa.</p>
                    </div>
                </div>
            </div>

            <!-- Add compensation form -->
            <div v-if="canEdit" class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                <div class="border-b border-gray-100 px-6 py-5">
                    <h2 class="text-sm font-medium uppercase tracking-wide text-gray-500">Registrar Nova Remuneração</h2>
                </div>
                <form class="px-6 py-5" @submit.prevent="submit">
                    <div class="grid grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Tipo</label>
                            <select
                                v-model="form.type"
                                class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                            >
                                <option value="percentage">Percentual</option>
                                <option value="fixed_per_freight">Fixo por Frete</option>
                                <option value="monthly_salary">Salário Mensal</option>
                            </select>
                            <p v-if="form.errors.type" class="mt-1.5 text-xs text-red-600">{{ form.errors.type }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ amountLabel }}</label>
                            <input
                                v-model="form[amountField]"
                                type="number"
                                step="0.01"
                                min="0.01"
                                class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                            />
                            <p v-if="form.errors[amountField]" class="mt-1.5 text-xs text-red-600">{{ form.errors[amountField] }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Vigência a partir de</label>
                            <input
                                v-model="form.effective_from"
                                type="date"
                                class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                            />
                            <p v-if="form.errors.effective_from" class="mt-1.5 text-xs text-red-600">{{ form.errors.effective_from }}</p>
                        </div>
                    </div>

                    <div class="mt-5 flex justify-end border-t border-gray-100 pt-5">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50 transition-colors"
                        >
                            Registrar remuneração
                        </button>
                    </div>
                </form>
            </div>

            <!-- History -->
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                <div class="border-b border-gray-100 px-6 py-5">
                    <h2 class="text-sm font-medium uppercase tracking-wide text-gray-500">Histórico</h2>
                </div>
                <div v-if="history.length">
                    <table class="min-w-full divide-y divide-gray-100">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Tipo</th>
                                <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Valor</th>
                                <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Início</th>
                                <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Fim</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="comp in history" :key="comp.id" class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 text-sm text-gray-700">{{ typeLabel(comp.type) }}</td>
                                <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ activeAmount(comp) }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ comp.effective_from }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ comp.effective_to }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="px-6 py-10 text-center">
                    <p class="text-sm text-gray-500">Sem histórico de remunerações anteriores.</p>
                </div>
            </div>

        </div>
    </AuthenticatedLayout>
</template>
