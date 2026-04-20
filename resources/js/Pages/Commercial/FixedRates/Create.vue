<script>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'

export default {
    components: { AuthenticatedLayout, Head, Link },

    props: {
        freightTable: Object,
        vehicleTypes: Array,
    },

    setup() {
        const form = useForm({
            name: '',
            avg_km: null,
            prices: [{ vehicle_type_id: '', price: '', tolls: null, fuel_cost: null }],
        })
        return { form }
    },

    methods: {
        addRow() {
            this.form.prices.push({ vehicle_type_id: '', price: '', tolls: null, fuel_cost: null })
        },
        removeRow(index) {
            this.form.prices.splice(index, 1)
        },
        submit() {
            this.form.post(route('freight-tables.fixed-rates.store', this.freightTable.id))
        },
    },
}
</script>

<template>
    <Head title="Nova Taxa Fixa" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('freight-tables.show', freightTable.id)" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                </Link>
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">Nova Taxa Fixa</h1>
                    <p class="text-sm text-gray-500">{{ freightTable.name }}</p>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-2xl">
            <form @submit.prevent="submit">
                <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                    <div class="p-6 space-y-5">
                        <!-- Route info -->
                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Nome / Destino *</label>
                                <input v-model="form.name" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                                <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">KM Médio</label>
                                <input v-model="form.avg_km" type="number" step="0.01" min="0" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                            </div>
                        </div>

                        <!-- Prices per vehicle type -->
                        <div>
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Preços por Tipo de Veículo</h3>
                                <button type="button" @click="addRow" class="inline-flex items-center gap-1 text-xs font-medium text-indigo-600 hover:text-indigo-800 transition-colors">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                    </svg>
                                    Adicionar tipo
                                </button>
                            </div>

                            <p v-if="form.errors.prices" class="mb-2 text-xs text-red-600">{{ form.errors.prices }}</p>

                            <div class="space-y-3">
                                <div
                                    v-for="(row, i) in form.prices"
                                    :key="i"
                                    class="grid grid-cols-12 gap-2 items-start rounded-lg border border-gray-200 bg-gray-50 p-3"
                                >
                                    <div class="col-span-12 sm:col-span-3">
                                        <label class="block text-xs text-gray-500 mb-1">Tipo *</label>
                                        <select
                                            v-model="row.vehicle_type_id"
                                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 bg-white"
                                        >
                                            <option value="">Selecione</option>
                                            <option v-for="vt in vehicleTypes" :key="vt.id" :value="vt.id">{{ vt.label }}</option>
                                        </select>
                                        <p v-if="form.errors[`prices.${i}.vehicle_type_id`]" class="mt-1 text-xs text-red-600">Obrigatório</p>
                                    </div>
                                    <div class="col-span-12 sm:col-span-3">
                                        <label class="block text-xs text-gray-500 mb-1">Preço (R$) *</label>
                                        <input v-model="row.price" type="number" step="0.01" min="0" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                                        <p v-if="form.errors[`prices.${i}.price`]" class="mt-1 text-xs text-red-600">Obrigatório</p>
                                    </div>
                                    <div class="col-span-6 sm:col-span-2">
                                        <label class="block text-xs text-gray-500 mb-1">Pedágio (R$)</label>
                                        <input v-model="row.tolls" type="number" step="0.01" min="0" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                                    </div>
                                    <div class="col-span-6 sm:col-span-2">
                                        <label class="block text-xs text-gray-500 mb-1">Combustível (R$)</label>
                                        <input v-model="row.fuel_cost" type="number" step="0.01" min="0" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                                    </div>
                                    <div class="col-span-12 sm:col-span-2 flex items-end pb-0.5">
                                        <button
                                            v-if="form.prices.length > 1"
                                            type="button"
                                            @click="removeRow(i)"
                                            class="mt-5 text-sm text-red-500 hover:text-red-700 transition-colors"
                                        >Remover</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 border-t border-gray-100 bg-gray-50 px-6 py-4">
                        <Link :href="route('freight-tables.show', freightTable.id)" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                            Cancelar
                        </Link>
                        <button type="submit" :disabled="form.processing" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-500 transition-colors disabled:opacity-50">
                            Salvar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
