<script>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, useForm } from '@inertiajs/vue3'

export default {
    components: { AuthenticatedLayout, Head },

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
            <h2 class="text-xl font-semibold text-gray-800">
                {{ pageTitle }}
            </h2>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
                <form
                    class="space-y-6 rounded-lg bg-white p-6 shadow"
                    @submit.prevent="submit"
                >
                    <div class="grid grid-cols-2 gap-4">
                        <!-- Kind -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Tipo</label>
                            <select
                                v-model="form.kind"
                                class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm"
                            >
                                <option value="vehicle">
                                    Veículo
                                </option>
                                <option value="trailer">
                                    Reboque
                                </option>
                            </select>
                            <p
                                v-if="form.errors.kind"
                                class="mt-1 text-xs text-red-600"
                            >
                                {{ form.errors.kind }}
                            </p>
                        </div>

                        <!-- Vehicle Type -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Categoria</label>
                            <select
                                v-model="form.vehicle_type_id"
                                class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm"
                            >
                                <option value="">
                                    Selecione...
                                </option>
                                <option
                                    v-for="t in filteredTypes"
                                    :key="t.id"
                                    :value="t.id"
                                >
                                    {{ t.label }}
                                </option>
                            </select>
                            <p
                                v-if="form.errors.vehicle_type_id"
                                class="mt-1 text-xs text-red-600"
                            >
                                {{ form.errors.vehicle_type_id }}
                            </p>
                        </div>

                        <!-- License Plate -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Placa</label>
                            <input
                                v-model="form.license_plate"
                                type="text"
                                class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm"
                            />
                            <p
                                v-if="form.errors.license_plate"
                                class="mt-1 text-xs text-red-600"
                            >
                                {{ form.errors.license_plate }}
                            </p>
                        </div>

                        <!-- RENAVAM -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700">RENAVAM</label>
                            <input
                                v-model="form.renavam"
                                type="text"
                                class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm"
                            />
                            <p
                                v-if="form.errors.renavam"
                                class="mt-1 text-xs text-red-600"
                            >
                                {{ form.errors.renavam }}
                            </p>
                        </div>

                        <!-- Brand -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Marca</label>
                            <input
                                v-model="form.brand"
                                type="text"
                                class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm"
                            />
                            <p
                                v-if="form.errors.brand"
                                class="mt-1 text-xs text-red-600"
                            >
                                {{ form.errors.brand }}
                            </p>
                        </div>

                        <!-- Model -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Modelo</label>
                            <input
                                v-model="form.model"
                                type="text"
                                class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm"
                            />
                            <p
                                v-if="form.errors.model"
                                class="mt-1 text-xs text-red-600"
                            >
                                {{ form.errors.model }}
                            </p>
                        </div>

                        <!-- Year -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Ano</label>
                            <input
                                v-model="form.year"
                                type="number"
                                min="1980"
                                class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm"
                            />
                            <p
                                v-if="form.errors.year"
                                class="mt-1 text-xs text-red-600"
                            >
                                {{ form.errors.year }}
                            </p>
                        </div>

                        <!-- Active -->
                        <div class="flex items-center gap-2 pt-6">
                            <input
                                id="active"
                                v-model="form.active"
                                type="checkbox"
                                class="rounded border-gray-300 text-indigo-600"
                            />
                            <label
                                for="active"
                                class="text-sm font-medium text-gray-700"
                            >Ativo</label>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Observações</label>
                        <textarea
                            v-model="form.notes"
                            rows="3"
                            class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm"
                        />
                    </div>

                    <div class="flex justify-end gap-3">
                        <a
                            href="/vehicles"
                            class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
                        >
                            Cancelar
                        </a>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500 disabled:opacity-50"
                        >
                            {{ isEdit ? 'Salvar' : 'Criar Veículo' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
