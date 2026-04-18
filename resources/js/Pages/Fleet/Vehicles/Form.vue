<script>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { vMaska } from 'maska/vue'

export default {
    components: { AuthenticatedLayout, Head, Link },
    directives: { maska: vMaska },

    props: {
        vehicle: { type: Object, default: null },
        vehicleTypes: Array,
    },

    setup(props) {
        const form = useForm({
            kind:            props.vehicle?.kind ?? 'vehicle',
            vehicle_type_id: props.vehicle?.vehicle_type_id ?? '',
            license_plate:   props.vehicle?.license_plate ?? '',
            renavam:         props.vehicle?.renavam ?? '',
            brand:           props.vehicle?.brand ?? '',
            model:           props.vehicle?.model ?? '',
            year:            props.vehicle?.year ?? new Date().getFullYear(),
            notes:           props.vehicle?.notes ?? '',
            active:          props.vehicle?.active ?? true,
        })
        return { form }
    },

    computed: {
        isEdit() { return !!this.vehicle },
        filteredTypes() {
            return this.vehicleTypes.filter(t =>
                this.form.kind === 'trailer' ? t.code !== 'vuc' : true
            )
        },
        pageTitle() { return this.isEdit ? 'Editar Veículo' : 'Novo Veículo' },
    },

    methods: {
        submit() {
            if (this.isEdit) {
                this.form.put(`/vehicles/${this.vehicle.id}`)
            } else {
                this.form.post('/vehicles')
            }
        },
    },
}
</script>

<template>
    <Head :title="pageTitle" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link href="/vehicles" class="text-gray-400 hover:text-gray-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                </Link>
                <h1 class="text-xl font-semibold text-gray-900">{{ pageTitle }}</h1>
            </div>
        </template>

        <div class="mx-auto max-w-2xl">
            <form class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200" @submit.prevent="submit">

                <div class="px-6 py-5 border-b border-gray-100">
                    <h2 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Identificação</h2>
                </div>

                <div class="px-6 py-5 grid grid-cols-2 gap-5">

                    <!-- Kind -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Tipo</label>
                        <select v-model="form.kind" class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                            <option value="vehicle">Veículo</option>
                            <option value="trailer">Reboque</option>
                        </select>
                        <p v-if="form.errors.kind" class="mt-1.5 text-xs text-red-600">{{ form.errors.kind }}</p>
                    </div>

                    <!-- Vehicle Type -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Categoria</label>
                        <select v-model="form.vehicle_type_id" class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                            <option value="">Selecione...</option>
                            <option v-for="t in filteredTypes" :key="t.id" :value="t.id">{{ t.label }}</option>
                        </select>
                        <p v-if="form.errors.vehicle_type_id" class="mt-1.5 text-xs text-red-600">{{ form.errors.vehicle_type_id }}</p>
                    </div>

                    <!-- License Plate -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Placa</label>
                        <input
                            v-model="form.license_plate"
                            v-maska="{ mask: ['AAA-####', 'AAA#A##'], tokens: { A: { pattern: /[A-Za-z]/, uppercase: true }, '#': { pattern: /[0-9]/ } } }"
                            type="text"
                            placeholder="ABC-1234"
                            class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm font-mono tracking-wider shadow-sm uppercase focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        />
                        <p v-if="form.errors.license_plate" class="mt-1.5 text-xs text-red-600">{{ form.errors.license_plate }}</p>
                    </div>

                    <!-- RENAVAM -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">RENAVAM</label>
                        <input
                            v-model="form.renavam"
                            v-maska="'##########-#'"
                            type="text"
                            placeholder="0000000000-0"
                            class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm font-mono tracking-wide shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        />
                        <p v-if="form.errors.renavam" class="mt-1.5 text-xs text-red-600">{{ form.errors.renavam }}</p>
                    </div>

                    <!-- Brand -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Marca</label>
                        <input
                            v-model="form.brand"
                            type="text"
                            placeholder="Volvo, Scania..."
                            class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        />
                        <p v-if="form.errors.brand" class="mt-1.5 text-xs text-red-600">{{ form.errors.brand }}</p>
                    </div>

                    <!-- Model -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Modelo</label>
                        <input
                            v-model="form.model"
                            type="text"
                            placeholder="FH 540, R 450..."
                            class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        />
                        <p v-if="form.errors.model" class="mt-1.5 text-xs text-red-600">{{ form.errors.model }}</p>
                    </div>

                    <!-- Year -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Ano</label>
                        <input
                            v-model="form.year"
                            type="number"
                            min="1980"
                            :max="new Date().getFullYear() + 1"
                            class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        />
                        <p v-if="form.errors.year" class="mt-1.5 text-xs text-red-600">{{ form.errors.year }}</p>
                    </div>

                    <!-- Active -->
                    <div class="flex items-center gap-2.5 pt-7">
                        <input id="active" v-model="form.active" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                        <label for="active" class="text-sm font-medium text-gray-700">Veículo ativo</label>
                    </div>
                </div>

                <!-- Notes -->
                <div class="px-6 pb-5">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Observações</label>
                    <textarea
                        v-model="form.notes"
                        rows="3"
                        placeholder="Informações adicionais..."
                        class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                    />
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end gap-3 border-t border-gray-100 bg-gray-50 px-6 py-4">
                    <Link
                        href="/vehicles"
                        class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 transition-colors"
                    >
                        Cancelar
                    </Link>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50 transition-colors"
                    >
                        {{ isEdit ? 'Salvar alterações' : 'Criar veículo' }}
                    </button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
