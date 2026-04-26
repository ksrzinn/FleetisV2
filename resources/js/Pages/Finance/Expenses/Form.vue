<script>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import axios from 'axios'

export default {
    components: { AuthenticatedLayout, Head, Link },

    props: {
        expense:    { type: Object, default: null },
        categories: Array,
        vehicles:   Array,
    },

    setup(props) {
        const form = useForm({
            expense_category_id: props.expense?.expense_category_id ?? '',
            amount:              props.expense?.amount ?? '',
            incurred_on:         props.expense?.incurred_on?.split('T')[0] ?? '',
            description:         props.expense?.description ?? '',
            vehicle_id:          props.expense?.vehicle_id ?? '',
        })
        return { form }
    },

    data() {
        return {
            localCategories:  [...this.categories],
            newCategoryName:  '',
            showNewCategory:  false,
            creatingCategory: false,
        }
    },

    computed: {
        isEdit() {
            return !!this.expense
        },
    },

    methods: {
        submit() {
            if (this.isEdit) {
                this.form.put(`/expenses/${this.expense.id}`)
            } else {
                this.form.post('/expenses')
            }
        },

        async createCategory() {
            if (!this.newCategoryName.trim()) return
            this.creatingCategory = true
            try {
                const { data } = await axios.post('/expense-categories', { name: this.newCategoryName.trim() })
                this.localCategories.push(data)
                this.form.expense_category_id = data.id
                this.newCategoryName = ''
                this.showNewCategory = false
            } catch {
                // validation errors bubble back via Laravel
            } finally {
                this.creatingCategory = false
            }
        },
    },
}
</script>

<template>
    <Head :title="isEdit ? 'Editar Despesa' : 'Nova Despesa'" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link href="/expenses" class="text-gray-400 hover:text-gray-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                </Link>
                <h1 class="text-xl font-semibold text-gray-900">{{ isEdit ? 'Editar Despesa' : 'Nova Despesa' }}</h1>
            </div>
        </template>

        <div class="mx-auto max-w-2xl">
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                <div class="px-6 py-5 border-b border-gray-100">
                    <h2 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Dados da Despesa</h2>
                </div>

                <form @submit.prevent="submit" class="px-6 py-5 space-y-5">

                    <!-- Category -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            Categoria <span class="text-red-500">*</span>
                        </label>
                        <div class="flex gap-2">
                            <select v-model="form.expense_category_id"
                                class="flex-1 rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                <option value="">Selecione uma categoria...</option>
                                <option v-for="c in localCategories" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                            <button type="button" @click="showNewCategory = !showNewCategory"
                                class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-600 hover:bg-gray-50 transition-colors">
                                + Nova
                            </button>
                        </div>
                        <p v-if="form.errors.expense_category_id" class="mt-1.5 text-xs text-red-600">{{ form.errors.expense_category_id }}</p>

                        <div v-if="showNewCategory" class="mt-2 flex gap-2">
                            <input v-model="newCategoryName" type="text" placeholder="Nome da nova categoria"
                                @keyup.enter="createCategory"
                                class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                            <button type="button" @click="createCategory" :disabled="creatingCategory"
                                class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500 disabled:opacity-60 transition-colors">
                                {{ creatingCategory ? '...' : 'Criar' }}
                            </button>
                        </div>
                    </div>

                    <!-- Amount -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            Valor <span class="text-red-500">*</span>
                        </label>
                        <input v-model="form.amount" type="number" step="0.01" min="0.01" placeholder="0,00"
                            class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                        <p v-if="form.errors.amount" class="mt-1.5 text-xs text-red-600">{{ form.errors.amount }}</p>
                    </div>

                    <!-- Date -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            Data <span class="text-red-500">*</span>
                        </label>
                        <input v-model="form.incurred_on" type="date"
                            class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                        <p v-if="form.errors.incurred_on" class="mt-1.5 text-xs text-red-600">{{ form.errors.incurred_on }}</p>
                    </div>

                    <!-- Vehicle -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Veículo</label>
                        <select v-model="form.vehicle_id"
                            class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                            <option value="">Nenhum</option>
                            <option v-for="v in vehicles" :key="v.id" :value="v.id">{{ v.license_plate }}</option>
                        </select>
                        <p v-if="form.errors.vehicle_id" class="mt-1.5 text-xs text-red-600">{{ form.errors.vehicle_id }}</p>
                    </div>

                    <!-- Description -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Descrição</label>
                        <textarea v-model="form.description" rows="3" placeholder="Observações opcionais..."
                            class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                        <p v-if="form.errors.description" class="mt-1.5 text-xs text-red-600">{{ form.errors.description }}</p>
                    </div>

                    <div class="flex justify-end gap-3 pt-2 border-t border-gray-100">
                        <Link href="/expenses"
                            class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Cancelar
                        </Link>
                        <button type="submit" :disabled="form.processing"
                            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-500 disabled:opacity-60 transition-colors">
                            {{ form.processing ? 'Salvando...' : (isEdit ? 'Salvar alterações' : 'Criar despesa') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
