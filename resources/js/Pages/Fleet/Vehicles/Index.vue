<script>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'

export default {
    components: { AuthenticatedLayout, Head, Link },

    props: {
        vehicles: Object,
        vehicleTypes: Array,
        filters: Object,
    },

    data() {
        return {
            search: this.filters?.search ?? '',
            active: this.filters?.active ?? '',
        }
    },

    methods: {
        applyFilters() {
            router.get('/vehicles', { search: this.search, active: this.active }, {
                preserveState: true,
                replace: true,
            })
        },

        destroy(vehicle) {
            if (!confirm(`Remover veículo ${vehicle.license_plate}?`)) return
            router.delete(`/vehicles/${vehicle.id}`)
        },

        kindLabel(kind) {
            return kind === 'vehicle' ? 'Veículo' : 'Reboque'
        },
    },
}
</script>

<template>
    <Head title="Veículos" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-800">
                    Veículos
                </h2>
                <Link
                    href="/vehicles/create"
                    class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500"
                >
                    + Novo Veículo
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- Filters -->
                <div class="mb-4 flex gap-3">
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Buscar placa..."
                        class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        @input="applyFilters"
                    />
                    <select
                        v-model="active"
                        class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm"
                        @change="applyFilters"
                    >
                        <option value="">
                            Todos
                        </option>
                        <option value="true">
                            Ativos
                        </option>
                        <option value="false">
                            Inativos
                        </option>
                    </select>
                </div>

                <!-- Table -->
                <div class="overflow-hidden rounded-lg bg-white shadow">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                    Placa
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                    Tipo
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                    Marca/Modelo
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                    Ano
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                    Status
                                </th>
                                <th class="px-6 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr
                                v-for="vehicle in vehicles.data"
                                :key="vehicle.id"
                            >
                                <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">
                                    {{ vehicle.license_plate }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                    {{ kindLabel(vehicle.kind) }} — {{ vehicle.vehicle_type?.label }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                    {{ vehicle.brand }} {{ vehicle.model }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                    {{ vehicle.year }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm">
                                    <span
                                        :class="vehicle.active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'"
                                        class="rounded-full px-2 py-1 text-xs font-medium"
                                    >
                                        {{ vehicle.active ? 'Ativo' : 'Inativo' }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                    <Link
                                        :href="`/vehicles/${vehicle.id}/edit`"
                                        class="mr-3 text-indigo-600 hover:text-indigo-900"
                                    >
                                        Editar
                                    </Link>
                                    <button
                                        class="text-red-600 hover:text-red-900"
                                        @click="destroy(vehicle)"
                                    >
                                        Remover
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="vehicles.data.length === 0">
                                <td
                                    colspan="6"
                                    class="px-6 py-10 text-center text-sm text-gray-500"
                                >
                                    Nenhum veículo encontrado.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div
                    v-if="vehicles.links"
                    class="mt-4 flex justify-end gap-1"
                >
                    <template
                        v-for="link in vehicles.links"
                        :key="link.label"
                    >
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            :class="[link.active ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50',
                                     'rounded border px-3 py-1 text-sm']"
                        >
                            <span v-html="link.label" />
                        </Link>
                        <span
                            v-else
                            class="rounded border px-3 py-1 text-sm text-gray-400"
                            v-html="link.label"
                        />
                    </template>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
