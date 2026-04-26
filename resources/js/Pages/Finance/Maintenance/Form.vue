<script>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'

export default {
    components: { AuthenticatedLayout, Head, Link },

    props: {
        maintenanceRecord: { type: Object, default: null },
        vehicles:          Array,
    },

    setup(props) {
        const form = useForm({
            vehicle_id:   props.maintenanceRecord?.vehicle_id ?? '',
            type:         props.maintenanceRecord?.type ?? '',
            description:  props.maintenanceRecord?.description ?? '',
            cost:         props.maintenanceRecord?.cost ?? '',
            odometer_km:  props.maintenanceRecord?.odometer_km ?? '',
            performed_on: props.maintenanceRecord?.performed_on?.split('T')[0] ?? '',
            provider:     props.maintenanceRecord?.provider ?? '',
        })
        return { form }
    },

    computed: {
        isEdit() {
            return !!this.maintenanceRecord
        },
    },

    methods: {
        submit() {
            if (this.isEdit) {
                this.form.put(`/maintenance-records/${this.maintenanceRecord.id}`)
            } else {
                this.form.post('/maintenance-records')
            }
        },
    },
}
</script>

<template>
    <Head :title="isEdit ? 'Editar Manutenção' : 'Nova Manutenção'" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link href="/maintenance-records" class="text-gray-400 hover:text-gray-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                </Link>
                <h1 class="text-xl font-semibold text-gray-900">{{ isEdit ? 'Editar Manutenção' : 'Nova Manutenção' }}</h1>
            </div>
        </template>

        <div class="mx-auto max-w-2xl">
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                <div class="px-6 py-5 border-b border-gray-100">
                    <h2 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Dados da Manutenção</h2>
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

                    <!-- Type -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            Tipo <span class="text-red-500">*</span>
                        </label>
                        <select v-model="form.type"
                            class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                            <option value="">Selecione...</option>
                            <option value="preventive">Preventiva</option>
                            <option value="corrective">Corretiva</option>
                            <option value="emergency">Emergência</option>
                            <option value="routine">Rotina</option>
                        </select>
                        <p v-if="form.errors.type" class="mt-1.5 text-xs text-red-600">{{ form.errors.type }}</p>
                    </div>

                    <!-- Description -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            Descrição <span class="text-red-500">*</span>
                        </label>
                        <textarea v-model="form.description" rows="3" placeholder="Descreva o serviço realizado..."
                            class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                        <p v-if="form.errors.description" class="mt-1.5 text-xs text-red-600">{{ form.errors.description }}</p>
                    </div>

                    <!-- Cost + Odometer -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                Custo <span class="text-red-500">*</span>
                            </label>
                            <input v-model="form.cost" type="number" step="0.01" min="0.01" placeholder="0,00"
                                class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                            <p v-if="form.errors.cost" class="mt-1.5 text-xs text-red-600">{{ form.errors.cost }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Odômetro (km)</label>
                            <input v-model="form.odometer_km" type="number" min="0" placeholder="Ex: 120000"
                                class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                            <p v-if="form.errors.odometer_km" class="mt-1.5 text-xs text-red-600">{{ form.errors.odometer_km }}</p>
                        </div>
                    </div>

                    <!-- Date + Provider -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                Data <span class="text-red-500">*</span>
                            </label>
                            <input v-model="form.performed_on" type="date"
                                class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                            <p v-if="form.errors.performed_on" class="mt-1.5 text-xs text-red-600">{{ form.errors.performed_on }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Prestador</label>
                            <input v-model="form.provider" type="text" placeholder="Oficina ou prestador..."
                                class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                            <p v-if="form.errors.provider" class="mt-1.5 text-xs text-red-600">{{ form.errors.provider }}</p>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-2 border-t border-gray-100">
                        <Link href="/maintenance-records"
                            class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Cancelar
                        </Link>
                        <button type="submit" :disabled="form.processing"
                            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-500 disabled:opacity-60 transition-colors">
                            {{ form.processing ? 'Salvando...' : (isEdit ? 'Salvar alterações' : 'Registrar manutenção') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
