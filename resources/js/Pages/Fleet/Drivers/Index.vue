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
                <h1 class="text-xl font-semibold text-gray-900">Motoristas</h1>
                <Link
                    href="/drivers/create"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-500 transition-colors"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    Novo Motorista
                </Link>
            </div>
        </template>

        <!-- Filters -->
        <div class="mb-5 flex flex-wrap gap-3">
            <div class="relative">
                <svg class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" />
                </svg>
                <input
                    v-model="search"
                    type="text"
                    placeholder="Buscar nome ou CPF..."
                    class="rounded-lg border border-gray-300 py-2 pl-9 pr-4 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                    @input="applyFilters"
                />
            </div>
            <select
                v-model="active"
                class="rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                @change="applyFilters"
            >
                <option value="">Todos os status</option>
                <option value="true">Ativos</option>
                <option value="false">Inativos</option>
            </select>
        </div>

        <!-- Table card -->
        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
            <table class="min-w-full divide-y divide-gray-100">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Nome</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">CPF</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Telefone</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Status</th>
                        <th class="px-6 py-3.5" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    <tr v-for="driver in drivers.data" :key="driver.id" class="hover:bg-gray-50 transition-colors">
                        <td class="whitespace-nowrap px-6 py-4">
                            <span class="text-sm font-medium text-gray-900">{{ driver.name }}</span>
                        </td>
                        <td class="whitespace-nowrap px-6 py-4">
                            <span class="font-mono text-sm text-gray-600">{{ driver.cpf }}</span>
                        </td>
                        <td class="whitespace-nowrap px-6 py-4">
                            <span class="font-mono text-sm text-gray-600">{{ driver.phone ?? '—' }}</span>
                        </td>
                        <td class="whitespace-nowrap px-6 py-4">
                            <span
                                :class="driver.active
                                    ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200'
                                    : 'bg-gray-100 text-gray-500 ring-1 ring-gray-200'"
                                class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium"
                            >
                                <span :class="driver.active ? 'bg-emerald-500' : 'bg-gray-400'" class="h-1.5 w-1.5 rounded-full" />
                                {{ driver.active ? 'Ativo' : 'Inativo' }}
                            </span>
                        </td>
                        <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                            <Link :href="`/drivers/${driver.id}/compensations`" class="font-medium text-gray-500 hover:text-gray-800 mr-4 transition-colors">
                                Remunerações
                            </Link>
                            <Link :href="`/drivers/${driver.id}/edit`" class="font-medium text-indigo-600 hover:text-indigo-800 mr-4 transition-colors">
                                Editar
                            </Link>
                            <button class="font-medium text-red-500 hover:text-red-700 transition-colors" @click="destroy(driver)">
                                Remover
                            </button>
                        </td>
                    </tr>
                    <tr v-if="drivers.data.length === 0">
                        <td colspan="5" class="px-6 py-16 text-center">
                            <svg class="mx-auto mb-3 h-10 w-10 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            <p class="text-sm text-gray-500">Nenhum motorista encontrado.</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="drivers.links && drivers.last_page > 1" class="mt-5 flex justify-end gap-1">
            <template v-for="link in drivers.links" :key="link.label">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    :class="[
                        link.active
                            ? 'bg-indigo-600 text-white border-indigo-600'
                            : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50',
                        'rounded-lg border px-3 py-1.5 text-sm font-medium transition-colors'
                    ]"
                >
                    <span v-html="link.label" />
                </Link>
                <span v-else class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-gray-400" v-html="link.label" />
            </template>
        </div>
    </AuthenticatedLayout>
</template>
