<script>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, useForm } from '@inertiajs/vue3'

const TYPE_LABELS = {
    percentage:        'Percentual',
    fixed_per_freight: 'Fixo por Frete',
    monthly_salary:    'Salário Mensal',
}

export default {
    components: { AuthenticatedLayout, Head },

    props: {
        driver:  Object,
        active:  Array,
        history: Array,
        canEdit: Boolean,
    },

    setup(props) {
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
            <div class="flex items-center gap-2">
                <a href="/drivers" class="text-sm text-gray-500 hover:text-gray-700">Motoristas</a>
                <span class="text-gray-400">/</span>
                <h2 class="text-xl font-semibold text-gray-800">{{ driver.name }} — Remunerações</h2>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-3xl space-y-8 px-4 sm:px-6 lg:px-8">

                <!-- Active compensations -->
                <div class="rounded-lg bg-white p-6 shadow">
                    <h3 class="mb-4 text-base font-semibold text-gray-800">Remunerações Ativas</h3>
                    <div v-if="active.length" class="space-y-2">
                        <div v-for="comp in active" :key="comp.id"
                             class="flex items-center justify-between rounded-md border border-gray-100 bg-gray-50 px-4 py-3">
                            <div>
                                <span class="text-sm font-medium text-gray-700">{{ typeLabel(comp.type) }}</span>
                                <span class="ml-3 text-sm text-gray-900">{{ activeAmount(comp) }}</span>
                            </div>
                            <span class="text-xs text-gray-400">desde {{ comp.effective_from }}</span>
                        </div>
                    </div>
                    <p v-else class="text-sm text-gray-500">Nenhuma remuneração ativa.</p>
                </div>

                <!-- Add compensation form (operators/admins only) -->
                <div v-if="canEdit" class="rounded-lg bg-white p-6 shadow">
                    <h3 class="mb-4 text-base font-semibold text-gray-800">Registrar Nova Remuneração</h3>
                    <form class="space-y-4" @submit.prevent="submit">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Tipo</label>
                                <select v-model="form.type" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                                    <option value="percentage">Percentual</option>
                                    <option value="fixed_per_freight">Fixo por Frete</option>
                                    <option value="monthly_salary">Salário Mensal</option>
                                </select>
                                <p v-if="form.errors.type" class="mt-1 text-xs text-red-600">{{ form.errors.type }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">{{ amountLabel }}</label>
                                <input v-model="form[amountField]" type="number" step="0.01" min="0.01"
                                       class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm" />
                                <p v-if="form.errors[amountField]" class="mt-1 text-xs text-red-600">{{ form.errors[amountField] }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Vigência a partir de</label>
                                <input v-model="form.effective_from" type="date"
                                       class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm" />
                                <p v-if="form.errors.effective_from" class="mt-1 text-xs text-red-600">{{ form.errors.effective_from }}</p>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" :disabled="form.processing"
                                    class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500 disabled:opacity-50">
                                Registrar
                            </button>
                        </div>
                    </form>
                </div>

                <!-- History -->
                <div class="rounded-lg bg-white p-6 shadow">
                    <h3 class="mb-4 text-base font-semibold text-gray-800">Histórico</h3>
                    <table v-if="history.length" class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr class="text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                <th class="pb-2">Tipo</th>
                                <th class="pb-2">Valor</th>
                                <th class="pb-2">Início</th>
                                <th class="pb-2">Fim</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="comp in history" :key="comp.id">
                                <td class="py-2 text-gray-700">{{ typeLabel(comp.type) }}</td>
                                <td class="py-2 text-gray-900">{{ activeAmount(comp) }}</td>
                                <td class="py-2 text-gray-500">{{ comp.effective_from }}</td>
                                <td class="py-2 text-gray-500">{{ comp.effective_to }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="text-sm text-gray-500">Sem histórico de remunerações anteriores.</p>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
