<script>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'

export default {
    components: { AuthenticatedLayout, Head, Link },

    props: { rate: Object },

    setup(props) {
        const form = useForm({ rate_per_km: props.rate.rate_per_km })
        return { form }
    },

    methods: {
        submit() {
            this.form.put(route('per-km-rates.update', this.rate.id))
        },
    },
}
</script>

<template>
    <Head title="Editar Taxa por KM" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('clients.show', rate.client_id)" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                </Link>
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">Editar Taxa por KM</h1>
                    <p class="text-sm font-mono text-gray-500">{{ rate.state }}</p>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-sm">
            <form @submit.prevent="submit">
                <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                    <div class="p-6">
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Taxa por KM (R$) *</label>
                        <input v-model="form.rate_per_km" type="number" step="0.0001" min="0" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                        <p v-if="form.errors.rate_per_km" class="mt-1 text-xs text-red-600">{{ form.errors.rate_per_km }}</p>
                    </div>
                    <div class="flex justify-end gap-3 border-t border-gray-100 bg-gray-50 px-6 py-4">
                        <Link :href="route('clients.show', rate.client_id)" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
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
