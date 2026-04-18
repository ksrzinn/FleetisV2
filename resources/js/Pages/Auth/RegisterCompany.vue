<script>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { vMaska } from 'maska/vue';

export default {
    components: { GuestLayout, Head, Link },
    directives: { maska: vMaska },

    data() {
        return {
            form: useForm({
                company_name: '',
                cnpj: '',
                name: '',
                email: '',
                password: '',
                password_confirmation: '',
            }),
        };
    },

    methods: {
        submit() {
            this.form.post(route('register'), {
                onFinish: () => this.form.reset('password', 'password_confirmation'),
            });
        },
    },
};
</script>

<template>
    <GuestLayout>
        <Head title="Criar conta" />

        <div class="mb-8">
            <h2 class="text-2xl font-bold text-gray-900">Criar sua conta</h2>
            <p class="mt-1 text-sm text-gray-500">Preencha os dados da empresa para começar.</p>
        </div>

        <form class="space-y-5" @submit.prevent="submit">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Nome da empresa</label>
                <input
                    v-model="form.company_name"
                    type="text"
                    placeholder="Transportadora Exemplo Ltda"
                    autofocus
                    class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                />
                <p v-if="form.errors.company_name" class="mt-1.5 text-xs text-red-600">{{ form.errors.company_name }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">CNPJ</label>
                <input
                    v-model="form.cnpj"
                    v-maska="'##.###.###/####-##'"
                    type="text"
                    placeholder="00.000.000/0000-00"
                    class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm font-mono tracking-wide shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                />
                <p v-if="form.errors.cnpj" class="mt-1.5 text-xs text-red-600">{{ form.errors.cnpj }}</p>
            </div>

            <div class="border-t border-gray-100 pt-5">
                <p class="mb-4 text-xs font-semibold uppercase tracking-wide text-gray-400">Dados do responsável</p>

                <div class="space-y-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Seu nome</label>
                        <input
                            v-model="form.name"
                            type="text"
                            placeholder="Nome completo"
                            autocomplete="name"
                            class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        />
                        <p v-if="form.errors.name" class="mt-1.5 text-xs text-red-600">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">E-mail</label>
                        <input
                            v-model="form.email"
                            type="email"
                            placeholder="voce@empresa.com.br"
                            autocomplete="username"
                            class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        />
                        <p v-if="form.errors.email" class="mt-1.5 text-xs text-red-600">{{ form.errors.email }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Senha</label>
                        <input
                            v-model="form.password"
                            type="password"
                            autocomplete="new-password"
                            class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        />
                        <p v-if="form.errors.password" class="mt-1.5 text-xs text-red-600">{{ form.errors.password }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Confirmar senha</label>
                        <input
                            v-model="form.password_confirmation"
                            type="password"
                            autocomplete="new-password"
                            class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        />
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between pt-2">
                <Link
                    :href="route('login')"
                    class="text-sm text-indigo-600 hover:text-indigo-800 hover:underline"
                >
                    Já tem conta?
                </Link>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50 transition-colors"
                >
                    Criar conta
                </button>
            </div>
        </form>
    </GuestLayout>
</template>
