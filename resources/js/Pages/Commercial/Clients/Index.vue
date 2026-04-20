<script>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'

export default {
    components: { AuthenticatedLayout, Head, Link },

    props: {
        clients: Object,
        filters: Object,
    },

    data() {
        return {
            localFilters: {
                search: this.filters?.search ?? '',
                active: this.filters?.active ?? '',
            },
        }
    },

    methods: {
        search() {
            router.get(route('clients.index'), this.localFilters, { preserveState: true, replace: true })
        },
        destroy(client) {
            if (confirm(`Remover cliente ${client.name}?`)) {
                router.delete(route('clients.destroy', client.id))
            }
        },
    },
}
</script>

<template>
    <Head title="Clientes" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold text-gray-900">Clientes</h1>
                <Link
                    v-if="$page.props.auth.user.can?.['clients.manage']"
                    :href="route('clients.create')"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-500 transition-colors"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    Novo Cliente
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
                    v-model="localFilters.search"
                    type="text"
                    placeholder="Buscar nome ou documento..."
                    class="rounded-lg border border-gray-300 py-2 pl-9 pr-4 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                    @input="search"
                />
            </div>
            <select
                v-model="localFilters.active"
                class="rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                @change="search"
            >
                <option value="">Todos</option>
                <option value="1">Ativos</option>
                <option value="0">Inativos</option>
            </select>
        </div>

        <!-- Table card -->
        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
            <table class="min-w-full divide-y divide-gray-100">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Nome</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Documento</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">E-mail</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Status</th>
                        <th class="px-6 py-3.5" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    <tr v-for="client in clients.data" :key="client.id" class="hover:bg-gray-50 transition-colors">
                        <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">{{ client.name }}</td>
                        <td class="whitespace-nowrap px-6 py-4 font-mono text-sm text-gray-600">{{ client.document }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">{{ client.email ?? '—' }}</td>
                        <td class="whitespace-nowrap px-6 py-4">
                            <span
                                :class="client.active
                                    ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200'
                                    : 'bg-gray-100 text-gray-500 ring-1 ring-gray-200'"
                                class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium"
                            >
                                <span :class="client.active ? 'bg-emerald-500' : 'bg-gray-400'" class="h-1.5 w-1.5 rounded-full" />
                                {{ client.active ? 'Ativo' : 'Inativo' }}
                            </span>
                        </td>
                        <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                            <Link :href="route('clients.show', client.id)" class="font-medium text-indigo-600 hover:text-indigo-800 mr-4 transition-colors">Ver</Link>
                            <Link
                                v-if="$page.props.auth.user.can?.['clients.manage']"
                                :href="route('clients.edit', client.id)"
                                class="font-medium text-indigo-600 hover:text-indigo-800 mr-4 transition-colors"
                            >Editar</Link>
                            <button
                                v-if="$page.props.auth.user.can?.['clients.delete']"
                                class="font-medium text-red-500 hover:text-red-700 transition-colors"
                                @click="destroy(client)"
                            >Remover</button>
                        </td>
                    </tr>
                    <tr v-if="clients.data.length === 0">
                        <td colspan="5" class="px-6 py-16 text-center">
                            <svg class="mx-auto mb-3 h-10 w-10 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                            <p class="text-sm text-gray-500">Nenhum cliente encontrado.</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="clients.last_page > 1" class="mt-5 flex justify-end gap-1">
            <template v-for="link in clients.links" :key="link.label">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    :class="[
                        link.active
                            ? 'bg-indigo-600 text-white border-indigo-600'
                            : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50',
                        'rounded-lg border px-3 py-1.5 text-sm font-medium transition-colors'
                    ]"
                    v-html="link.label"
                />
                <span
                    v-else
                    class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-gray-400"
                    v-html="link.label"
                />
            </template>
        </div>
    </AuthenticatedLayout>
</template>
