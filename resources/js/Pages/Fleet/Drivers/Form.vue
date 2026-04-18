<script>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { vMaska } from 'maska/vue'

export default {
    components: { AuthenticatedLayout, Head, Link },
    directives: { maska: vMaska },

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
            this.form.cpf = this.cleanCPF(this.form.cpf)
            if (this.isEdit) {
                this.form.put(`/drivers/${this.driver.id}`)
            } else {
                this.form.post('/drivers')
            }
        },
        cleanCPF(value) {
            return (value || '').replace(/\D/g, '')
        }
    },
}
</script>

<template>
    <Head :title="pageTitle" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link href="/drivers" class="text-gray-400 hover:text-gray-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                </Link>
                <h1 class="text-xl font-semibold text-gray-900">{{ pageTitle }}</h1>
            </div>
        </template>

        <div class="mx-auto max-w-lg">
            <form class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200" @submit.prevent="submit">

                <div class="px-6 py-5 border-b border-gray-100">
                    <h2 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Dados do Motorista</h2>
                </div>

                <div class="px-6 py-5 space-y-5">

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Nome completo <span class="text-red-500">*</span></label>
                        <input
                            v-model="form.name"
                            type="text"
                            placeholder="Nome do motorista"
                            class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        />
                        <p v-if="form.errors.name" class="mt-1.5 text-xs text-red-600">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">CPF <span class="text-red-500">*</span></label>
                        <input
                            v-model="form.cpf"
                            v-maska="'###.###.###-##'"
                            type="text"
                            placeholder="000.000.000-00"
                            class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm font-mono tracking-wide shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        />
                        <p v-if="form.errors.cpf" class="mt-1.5 text-xs text-red-600">{{ form.errors.cpf }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Telefone</label>
                        <input
                            v-model="form.phone"
                            v-maska="['(##) ####-####', '(##) #####-####']"
                            type="text"
                            placeholder="(11) 99999-0000"
                            class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm font-mono tracking-wide shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Data de Nascimento</label>
                        <input
                            v-model="form.birth_date"
                            type="date"
                            class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        />
                    </div>

                    <div class="flex items-center gap-2.5">
                        <input id="active" v-model="form.active" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                        <label for="active" class="text-sm font-medium text-gray-700">Motorista ativo</label>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-gray-100 bg-gray-50 px-6 py-4">
                    <Link
                        href="/drivers"
                        class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 transition-colors"
                    >
                        Cancelar
                    </Link>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50 transition-colors"
                    >
                        {{ isEdit ? 'Salvar alterações' : 'Criar motorista' }}
                    </button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
