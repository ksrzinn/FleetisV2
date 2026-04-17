<script>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, useForm } from '@inertiajs/vue3'

export default {
    components: { AuthenticatedLayout, Head },

    props: {
        driver: { type: Object, default: null },
    },

    setup(props) {
        const form = useForm({
            name:       props.driver?.name ?? '',
            cpf:        props.driver?.cpf ?? '',
            phone:      props.driver?.phone ?? '',
            birth_date: props.driver?.birth_date ?? '',
            active:     props.driver?.active ?? true,
        })
        return { form }
    },

    computed: {
        isEdit() { return !!this.driver },
        pageTitle() { return this.isEdit ? 'Editar Motorista' : 'Novo Motorista' },
    },

    methods: {
        submit() {
            if (this.isEdit) {
                this.form.put(`/drivers/${this.driver.id}`)
            } else {
                this.form.post('/drivers')
            }
        },
    },
}
</script>

<template>
    <Head :title="pageTitle" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">{{ pageTitle }}</h2>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-lg px-4 sm:px-6 lg:px-8">
                <form class="space-y-5 rounded-lg bg-white p-6 shadow" @submit.prevent="submit">

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Nome *</label>
                        <input v-model="form.name" type="text" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm" />
                        <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">CPF *</label>
                        <input v-model="form.cpf" type="text" placeholder="000.000.000-00" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm" />
                        <p v-if="form.errors.cpf" class="mt-1 text-xs text-red-600">{{ form.errors.cpf }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Telefone</label>
                        <input v-model="form.phone" type="text" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Data de Nascimento</label>
                        <input v-model="form.birth_date" type="date" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm" />
                    </div>

                    <div class="flex items-center gap-2">
                        <input id="active" v-model="form.active" type="checkbox" class="rounded border-gray-300 text-indigo-600" />
                        <label for="active" class="text-sm font-medium text-gray-700">Ativo</label>
                    </div>

                    <div class="flex justify-end gap-3">
                        <a href="/drivers" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            Cancelar
                        </a>
                        <button type="submit" :disabled="form.processing"
                                class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500 disabled:opacity-50">
                            {{ isEdit ? 'Salvar' : 'Criar Motorista' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
