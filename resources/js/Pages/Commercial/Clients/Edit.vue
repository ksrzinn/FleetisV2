<script>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'

export default {
    components: { AuthenticatedLayout, Head, Link },

    props: { client: Object },

    setup(props) {
        const form = useForm({
            name: props.client.name,
            document: props.client.document,
            email: props.client.email ?? '',
            phone: props.client.phone ?? '',
            address_street: props.client.address_street ?? '',
            address_number: props.client.address_number ?? '',
            address_complement: props.client.address_complement ?? '',
            address_neighborhood: props.client.address_neighborhood ?? '',
            address_city: props.client.address_city ?? '',
            address_state: props.client.address_state ?? '',
            address_zip: props.client.address_zip ?? '',
            active: props.client.active,
        })
        return { form }
    },

    methods: {
        submit() {
            this.form.put(route('clients.update', this.client.id))
        },
    },
}
</script>

<template>
    <Head title="Editar Cliente" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('clients.show', client.id)" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                </Link>
                <h1 class="text-xl font-semibold text-gray-900">Editar Cliente</h1>
            </div>
        </template>

        <div class="mx-auto max-w-2xl">
            <form @submit.prevent="submit">
                <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                    <div class="p-6 space-y-6">
                        <!-- Basic info -->
                        <div>
                            <h2 class="mb-4 text-sm font-semibold text-gray-700">Dados Básicos</h2>
                            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Nome *</label>
                                    <input v-model="form.name" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                                    <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">CPF / CNPJ *</label>
                                    <input v-model="form.document" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                                    <p v-if="form.errors.document" class="mt-1 text-xs text-red-600">{{ form.errors.document }}</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">E-mail</label>
                                    <input v-model="form.email" type="email" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                                    <p v-if="form.errors.email" class="mt-1 text-xs text-red-600">{{ form.errors.email }}</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Telefone</label>
                                    <input v-model="form.phone" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                                </div>
                                <div class="flex items-center gap-2">
                                    <input v-model="form.active" type="checkbox" id="active" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                    <label for="active" class="text-sm font-medium text-gray-700">Ativo</label>
                                </div>
                            </div>
                        </div>

                        <!-- Address -->
                        <div>
                            <h2 class="mb-4 text-sm font-semibold text-gray-700">Endereço</h2>
                            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Logradouro</label>
                                    <input v-model="form.address_street" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Número</label>
                                    <input v-model="form.address_number" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Complemento</label>
                                    <input v-model="form.address_complement" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Bairro</label>
                                    <input v-model="form.address_neighborhood" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Cidade</label>
                                    <input v-model="form.address_city" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">UF</label>
                                    <input v-model="form.address_state" type="text" maxlength="2" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm uppercase focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                                    <p v-if="form.errors.address_state" class="mt-1 text-xs text-red-600">{{ form.errors.address_state }}</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">CEP</label>
                                    <input v-model="form.address_zip" type="text" maxlength="8" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 border-t border-gray-100 bg-gray-50 px-6 py-4">
                        <Link :href="route('clients.show', client.id)" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
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
