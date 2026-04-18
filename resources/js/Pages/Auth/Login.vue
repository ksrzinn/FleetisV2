<script>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

export default {
    components: { GuestLayout, Head, Link },

    props: {
        canResetPassword: Boolean,
        status: String,
    },

    data() {
        return {
            form: useForm({ email: '', password: '', remember: false }),
        };
    },

    methods: {
        submit() {
            this.form.post(route('login'), {
                onFinish: () => this.form.reset('password'),
            });
        },
    },
};
</script>

<template>
    <GuestLayout>
        <Head title="Entrar" />

        <div class="mb-8">
            <h2 class="text-2xl font-bold text-gray-900">Entrar na sua conta</h2>
            <p class="mt-1 text-sm text-gray-500">Bem-vindo de volta.</p>
        </div>

        <div v-if="status" class="mb-5 rounded-lg bg-green-50 px-4 py-3 text-sm font-medium text-green-700 ring-1 ring-green-200">
            {{ status }}
        </div>

        <form class="space-y-5" @submit.prevent="submit">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">E-mail</label>
                <input
                    v-model="form.email"
                    type="email"
                    autofocus
                    autocomplete="username"
                    placeholder="voce@empresa.com.br"
                    class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                />
                <p v-if="form.errors.email" class="mt-1.5 text-xs text-red-600">{{ form.errors.email }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Senha</label>
                <input
                    v-model="form.password"
                    type="password"
                    autocomplete="current-password"
                    class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                />
                <p v-if="form.errors.password" class="mt-1.5 text-xs text-red-600">{{ form.errors.password }}</p>
            </div>

            <div class="flex items-center justify-between">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input v-model="form.remember" type="checkbox" name="remember" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                    <span class="text-sm text-gray-600">Lembrar de mim</span>
                </label>
                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="text-sm text-indigo-600 hover:text-indigo-800 hover:underline"
                >
                    Esqueceu a senha?
                </Link>
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50 transition-colors"
            >
                Entrar
            </button>

            <p class="text-center text-sm text-gray-500">
                Não tem conta?
                <Link :href="route('register')" class="font-medium text-indigo-600 hover:text-indigo-800 hover:underline">
                    Criar conta
                </Link>
            </p>
        </form>
    </GuestLayout>
</template>
