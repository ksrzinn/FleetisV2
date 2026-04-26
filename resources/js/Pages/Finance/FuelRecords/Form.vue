<script>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'

export default {
    components: { AuthenticatedLayout, Head, Link },

    props: {
        fuelRecord: { type: Object, default: null },
        vehicles:   Array,
        drivers:    Array,
    },

    setup(props) {
        const form = useForm({
            vehicle_id:      props.fuelRecord?.vehicle_id ?? '',
            driver_id:       props.fuelRecord?.driver_id ?? '',
            liters:          props.fuelRecord?.liters ?? '',
            price_per_liter: props.fuelRecord?.price_per_liter ?? '',
            odometer_km:     props.fuelRecord?.odometer_km ?? '',
            fueled_at:       props.fuelRecord?.fueled_at?.split('T')[0] ?? '',
            station:         props.fuelRecord?.station ?? '',
        })
        return { form }
    },

    computed: {
        isEdit() {
            return !!this.fuelRecord
        },
        totalPreview() {
            const l = parseFloat(this.form.liters)
            const p = parseFloat(this.form.price_per_liter)
            if (!l || !p || isNaN(l) || isNaN(p)) return null
            return (l * p).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
        },
    },

    methods: {
        submit() {
            if (this.isEdit) {
                this.form.put(`/fuel-records/${this.fuelRecord.id}`)
            } else {
                this.form.post('/fuel-records')
            }
        },
    },
}
</script>

<template>
    <Head :title="isEdit ? 'Editar Abastecimento' : 'Novo Abastecimento'" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link href="/fuel-records" class="text-gray-400 hover:text-gray-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                </Link>
                <h1 class="text-xl font-semibold text-gray-900">{{ isEdit ? 'Editar Abastecimento' : 'Novo Abastecimento' }}</h1>
            </div>
        </template>

        <div class="mx-auto max-w-2xl">
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                <div class="px-6 py-5 border-b border-gray-100">
                    <h2 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Dados do Abastecimento</h2>
                </div>

                <form @submit.prevent="submit" class="px-6 py-5 space-y-5">

                    <!-- Vehicle -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            Veículo <span class="text-red-500">*</span>
                        </label>
                        <select v-model="form.vehicle_id"
                            class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                            <option value="">Selecione...</option>
                            <option v-for="v in vehicles" :key="v.id" :value="v.id">{{ v.license_plate }}</option>
                        </select>
                        <p v-if="form.errors.vehicle_id" class="mt-1.5 text-xs text-red-600">{{ form.errors.vehicle_id }}</p>
                    </div>

                    <!-- Driver -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Motorista</label>
                        <select v-model="form.driver_id"
                            class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                            <option value="">Nenhum</option>
                            <option v-for="d in drivers" :key="d.id" :value="d.id">{{ d.name }}</option>
                        </select>
                        <p v-if="form.errors.driver_id" class="mt-1.5 text-xs text-red-600">{{ form.errors.driver_id }}</p>
                    </div>

                    <!-- Liters + Price per liter -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                Litros <span class="text-red-500">*</span>
                            </label>
                            <input v-model="form.liters" type="number" step="0.001" min="0.001" placeholder="0,000"
                                class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                            <p v-if="form.errors.liters" class="mt-1.5 text-xs text-red-600">{{ form.errors.liters }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                Preço por litro <span class="text-red-500">*</span>
                            </label>
                            <input v-model="form.price_per_liter" type="number" step="0.0001" min="0.0001" placeholder="0,0000"
                                class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                            <p v-if="form.errors.price_per_liter" class="mt-1.5 text-xs text-red-600">{{ form.errors.price_per_liter }}</p>
                        </div>
                    </div>

                    <!-- Total preview -->
                    <div v-if="totalPreview" class="rounded-lg bg-indigo-50 px-4 py-3">
                        <p class="text-sm text-indigo-700">
                            Total estimado: <span class="font-semibold">{{ totalPreview }}</span>
                        </p>
                    </div>

                    <!-- Date + Odometer -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                Data <span class="text-red-500">*</span>
                            </label>
                            <input v-model="form.fueled_at" type="date"
                                class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                            <p v-if="form.errors.fueled_at" class="mt-1.5 text-xs text-red-600">{{ form.errors.fueled_at }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Odômetro (km)</label>
                            <input v-model="form.odometer_km" type="number" min="0" placeholder="Ex: 120000"
                                class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                            <p v-if="form.errors.odometer_km" class="mt-1.5 text-xs text-red-600">{{ form.errors.odometer_km }}</p>
                        </div>
                    </div>

                    <!-- Station -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Posto</label>
                        <input v-model="form.station" type="text" placeholder="Nome do posto..."
                            class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                        <p v-if="form.errors.station" class="mt-1.5 text-xs text-red-600">{{ form.errors.station }}</p>
                    </div>

                    <div class="flex justify-end gap-3 pt-2 border-t border-gray-100">
                        <Link href="/fuel-records"
                            class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Cancelar
                        </Link>
                        <button type="submit" :disabled="form.processing"
                            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-500 disabled:opacity-60 transition-colors">
                            {{ form.processing ? 'Salvando...' : (isEdit ? 'Salvar alterações' : 'Registrar abastecimento') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
