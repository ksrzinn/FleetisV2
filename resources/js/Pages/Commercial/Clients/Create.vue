<script>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { vMaska } from 'maska/vue'

export default {
    components: { AuthenticatedLayout, Head, Link },
    directives: { maska: vMaska },

    setup() {
        const form = useForm({
            name: '',
            document: '',
            email: '',
            phone: '',
            active: true,
            payment_term_type: null,
            payment_term_value: null,
        })
        return { form }
    },

    methods: {
        submit() {
            this.form.document = this.stripMask(this.form.document)
            this.form.phone = this.stripMask(this.form.phone)
            console.log(this.form)
            this.form.post(route('clients.store'))
        },
        stripMask(value) {
            return (value || '').replace(/\D/g, '')
        },
    },
}
</script>

<template>
    <Head title="Novo Cliente" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('clients.index')" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                </Link>
                <h1 class="text-xl font-semibold text-gray-900">Novo Cliente</h1>
            </div>
        </template>

        <div class="mx-auto max-w-2xl">
            <form @submit.prevent="submit">
                <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                    <div class="p-6">
                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Nome *</label>
                                <input v-model="form.name" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                                <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">CPF / CNPJ *</label>
                                <input v-model="form.document" v-maska data-maska="['###.###.###-##', '##.###.###/####-##']" type="text" placeholder="CPF ou CNPJ" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                                <p v-if="form.errors.document" class="mt-1 text-xs text-red-600">{{ form.errors.document }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">E-mail</label>
                                <input v-model="form.email" type="email" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                                <p v-if="form.errors.email" class="mt-1 text-xs text-red-600">{{ form.errors.email }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Telefone</label>
                                <input v-model="form.phone" v-maska data-maska="['(##) ####-####', '(##) #####-####']" type="text" placeholder="(11) 99999-0000" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                            </div>
                            <div class="flex items-center gap-2">
                                <input v-model="form.active" type="checkbox" id="active" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                <label for="active" class="text-sm font-medium text-gray-700">Ativo</label>
                            </div>
                        </div>
                    </div>

                    <!-- Payment terms -->
                    <div class="border-t border-gray-100 p-6">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-4">Condições de Pagamento</h3>
                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Tipo de prazo</label>
                                <select v-model="form.payment_term_type" @change="form.payment_term_value = null"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                    <option :value="null">Não configurado (30 dias)</option>
                                    <option value="daily">Pagamento no dia</option>
                                    <option value="days_after">Dias após o frete</option>
                                    <option value="weekly">Dia da semana</option>
                                    <option value="monthly">Dia do mês</option>
                                </select>
                            </div>
                            <div v-if="form.payment_term_type === 'days_after'">
                                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Dias após o frete</label>
                                <input v-model.number="form.payment_term_value" type="number" min="1" max="365" placeholder="Ex: 30"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                            </div>
                            <div v-else-if="form.payment_term_type === 'monthly'">
                                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Dia do mês (1–28)</label>
                                <input v-model.number="form.payment_term_value" type="number" min="1" max="28" placeholder="Ex: 15"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                            </div>
                            <div v-else-if="form.payment_term_type === 'weekly'">
                                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Dia da semana</label>
                                <select v-model.number="form.payment_term_value"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                    <option :value="null">Selecione...</option>
                                    <option :value="1">Segunda-feira</option>
                                    <option :value="2">Terça-feira</option>
                                    <option :value="3">Quarta-feira</option>
                                    <option :value="4">Quinta-feira</option>
                                    <option :value="5">Sexta-feira</option>
                                    <option :value="6">Sábado</option>
                                    <option :value="7">Domingo</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 border-t border-gray-100 bg-gray-50 px-6 py-4">
                        <Link :href="route('clients.index')" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
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
