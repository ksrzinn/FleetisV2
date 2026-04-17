<script>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'

export default {
    components: { AuthenticatedLayout, Head, Link },

    props: {
        drivers: Object,
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
            router.get('/drivers', { search: this.search, active: this.active }, {
                preserveState: true,
                replace: true,
            })
        },

        destroy(driver) {
            if (!confirm(`Remover motorista ${driver.name}?`)) return
            router.delete(`/drivers/${driver.id}`)
        },
    },
}
</script>

<template>
    <Head title="Motoristas" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-800">Motoristas</h2>
                <Link href="/drivers/create"
                      class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                    + Novo Motorista
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

                <div class="mb-4 flex gap-3">
                    <input v-model="search" type="text" placeholder="Buscar nome..."
                           class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm"
                           @input="applyFilters" />
                    <select v-model="active" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm" @change="applyFilters">
                        <option value="">Todos</option>
                        <option value="true">Ativos</option>
                        <option value="false">Inativos</option>
                    </select>
                </div>

                <div class="overflow-hidden rounded-lg bg-white shadow">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Nome</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">CPF</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Telefone</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                                <th class="px-6 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr v-for="driver in drivers.data" :key="driver.id">
                                <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">{{ driver.name }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">{{ driver.cpf }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">{{ driver.phone ?? '—' }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm">
                                    <span :class="driver.active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'"
                                          class="rounded-full px-2 py-1 text-xs font-medium">
                                        {{ driver.active ? 'Ativo' : 'Inativo' }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                    <Link :href="`/drivers/${driver.id}/compensations`" class="mr-3 text-gray-600 hover:text-gray-900">
                                        Remunerações
                                    </Link>
                                    <Link :href="`/drivers/${driver.id}/edit`" class="mr-3 text-indigo-600 hover:text-indigo-900">
                                        Editar
                                    </Link>
                                    <button class="text-red-600 hover:text-red-900" @click="destroy(driver)">
                                        Remover
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="drivers.data.length === 0">
                                <td colspan="5" class="px-6 py-10 text-center text-sm text-gray-500">
                                    Nenhum motorista encontrado.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="drivers.links" class="mt-4 flex justify-end gap-1">
                    <template v-for="link in drivers.links" :key="link.label">
                        <Link v-if="link.url" :href="link.url"
                              :class="[link.active ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50',
                                       'rounded border px-3 py-1 text-sm']">
                            <span v-html="link.label" />
                        </Link>
                        <span v-else class="rounded border px-3 py-1 text-sm text-gray-400" v-html="link.label" />
                    </template>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
