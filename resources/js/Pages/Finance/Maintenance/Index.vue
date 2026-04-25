<script>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'

const TYPE_LABELS = {
    preventive:  'Preventiva',
    corrective:  'Corretiva',
    emergency:   'Emergência',
    routine:     'Rotina',
}

const TYPE_COLORS = {
    preventive:  'bg-blue-100 text-blue-700',
    corrective:  'bg-orange-100 text-orange-700',
    emergency:   'bg-red-100 text-red-700',
    routine:     'bg-gray-100 text-gray-700',
}

export default {
    components: { AuthenticatedLayout, Head, Link },

    props: {
        maintenanceRecords: Object,
        vehicles:           Array,
        filters:            Object,
    },

    setup() {
        const deleteForm = useForm({})
        return { deleteForm }
    },

    data() {
        return {
            vehicleId:    this.filters?.vehicle_id ?? '',
            typeFilter:   this.filters?.type ?? '',
            dateFrom:     this.filters?.date_from ?? '',
            dateTo:       this.filters?.date_to ?? '',
            deleteTarget: null,
            typeLabels:   TYPE_LABELS,
            typeColors:   TYPE_COLORS,
        }
    },

    methods: {
        applyFilters() {
            router.get('/maintenance-records', {
                'filter[vehicle_id]': this.vehicleId || undefined,
                'filter[type]':       this.typeFilter || undefined,
                'filter[date_from]':  this.dateFrom || undefined,
                'filter[date_to]':    this.dateTo || undefined,
            }, { preserveState: true, replace: true })
        },
        confirmDelete(record) {
            this.deleteTarget = record
        },
        executeDelete() {
            this.deleteForm.delete(`/maintenance-records/${this.deleteTarget.id}`, {
                onSuccess: () => { this.deleteTarget = null },
            })
        },
        formatCurrency(val) {
            if (val === null || val === undefined) return '—'
            return Number(val).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
        },
        formatDate(val) {
            if (!val) return '—'
            const [y, m, d] = (val.split('T')[0] || val).split('-')
            return `${d}/${m}/${y}`
        },
    },
}
</script>

<template>
    <Head title="Manutenções" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold text-gray-900">Manutenções</h1>
                <Link href="/maintenance-records/create" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-500 transition-colors">
                    + Nova Manutenção
                </Link>
            </div>
        </template>

        <!-- Filters -->
        <div class="mb-5 flex flex-wrap gap-3">
            <select v-model="vehicleId" @change="applyFilters"
                class="rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                <option value="">Todos os veículos</option>
                <option v-for="v in vehicles" :key="v.id" :value="v.id">{{ v.license_plate }}</option>
            </select>
            <select v-model="typeFilter" @change="applyFilters"
                class="rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                <option value="">Todos os tipos</option>
                <option v-for="(label, key) in typeLabels" :key="key" :value="key">{{ label }}</option>
            </select>
            <input v-model="dateFrom" type="date" @change="applyFilters"
                class="rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
            <input v-model="dateTo" type="date" @change="applyFilters"
                class="rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
        </div>

        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
            <table class="min-w-full divide-y divide-gray-100">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Data</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Veículo</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Tipo</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Descrição</th>
                        <th class="px-6 py-3.5 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Custo</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Prestador</th>
                        <th class="px-6 py-3.5" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    <tr v-if="!maintenanceRecords.data.length">
                        <td colspan="7" class="px-6 py-10 text-center text-sm text-gray-400">Nenhuma manutenção encontrada.</td>
                    </tr>
                    <tr v-for="r in maintenanceRecords.data" :key="r.id" class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4 text-sm text-gray-700">{{ formatDate(r.performed_on) }}</td>
                        <td class="px-6 py-4 text-sm text-gray-700">{{ r.vehicle?.license_plate ?? '—' }}</td>
                        <td class="px-6 py-4">
                            <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', typeColors[r.type] ?? 'bg-gray-100 text-gray-700']">
                                {{ typeLabels[r.type] ?? r.type }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500 max-w-xs truncate">{{ r.description }}</td>
                        <td class="px-6 py-4 text-sm text-right font-medium text-gray-900">{{ formatCurrency(r.cost) }}</td>
                        <td class="px-6 py-4 text-sm text-gray-500">{{ r.provider ?? '—' }}</td>
                        <td class="px-6 py-4 text-right space-x-3">
                            <Link :href="`/maintenance-records/${r.id}/edit`" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">Editar</Link>
                            <button @click="confirmDelete(r)" class="text-sm font-medium text-red-600 hover:text-red-500">Excluir</button>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div v-if="maintenanceRecords.last_page > 1" class="border-t border-gray-100 px-6 py-4 flex items-center justify-between text-sm text-gray-500">
                <span>{{ maintenanceRecords.total }} registros</span>
                <div class="flex gap-2">
                    <Link v-if="maintenanceRecords.prev_page_url" :href="maintenanceRecords.prev_page_url" class="rounded px-3 py-1 hover:bg-gray-100">Anterior</Link>
                    <Link v-if="maintenanceRecords.next_page_url" :href="maintenanceRecords.next_page_url" class="rounded px-3 py-1 hover:bg-gray-100">Próximo</Link>
                </div>
            </div>
        </div>

        <!-- Delete confirmation modal -->
        <div v-if="deleteTarget" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
            <div class="w-full max-w-sm rounded-xl bg-white shadow-xl ring-1 ring-gray-200 p-6">
                <h3 class="text-base font-semibold text-gray-900">Excluir manutenção?</h3>
                <p class="mt-2 text-sm text-gray-500">Esta ação não pode ser desfeita.</p>
                <div class="mt-5 flex justify-end gap-3">
                    <button @click="deleteTarget = null" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancelar</button>
                    <button @click="executeDelete" :disabled="deleteForm.processing" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-500 disabled:opacity-60">Excluir</button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
