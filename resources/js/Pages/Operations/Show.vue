<script>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { vMaska } from 'maska/vue'

const STATUS_LABELS = {
    to_start: 'A Iniciar',
    in_route: 'Em Rota',
    finished: 'Finalizado',
    awaiting_payment: 'Aguardando Pagamento',
    completed: 'Concluído',
}

const STATUS_COLORS = {
    to_start: 'bg-gray-100 text-gray-700',
    in_route: 'bg-blue-100 text-blue-700',
    finished: 'bg-yellow-100 text-yellow-700',
    awaiting_payment: 'bg-orange-100 text-orange-700',
    completed: 'bg-green-100 text-green-700',
}

export default {
    components: { AuthenticatedLayout, Head, Link },
    directives: { maska: vMaska },

    props: {
        freight: Object,
        tollDefault: { type: [Number, String], default: null },
        estimatedLiters: { type: [Number, String], default: null },
        canComputeFreightValue: { type: Boolean, default: true },
        canDelete: { type: Boolean, default: false },
        rateEditLink: { type: String, default: null },
    },

    setup(props) {
        const finishForm = useForm({
            transition: 'to_finished',
            distance_km: '',
            toll: props.tollDefault ? String(props.tollDefault) : '',
            fuel_price_per_liter: '',
        })
        return { finishForm }
    },

    data() {
        return {
            showFinishModal: false,
            showDeleteConfirm: false,
            statusLabels: STATUS_LABELS,
            statusColors: STATUS_COLORS,
        }
    },

    computed: {
        estimatedFuelCost() {
            const liters = this.estimatedLitersComputed
            const price = parseFloat(this.finishForm.fuel_price_per_liter)
            if (!liters || !price) return null
            return (liters * price).toFixed(2)
        },
        estimatedLitersComputed() {
            const km = parseFloat(this.finishForm.distance_km)
            const consumo = this.freight.vehicle?.consumo_medio
            if (!km || !consumo) return this.estimatedLiters
            return (km / parseFloat(consumo)).toFixed(2)
        },
    },

    methods: {
        transition(transitionKey) {
            this.$inertia.post(`/freights/${this.freight.id}/transition`, { transition: transitionKey })
        },
        submitFinish() {
            this.finishForm.post(`/freights/${this.freight.id}/transition`, {
                onSuccess: () => { this.showFinishModal = false },
            })
        },
        formatCurrency(val) {
            if (val === null || val === undefined) return '—'
            return Number(val).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
        },
        formatDate(val) {
            if (!val) return '—'
            return new Date(val).toLocaleString('pt-BR')
        },
        deleteFreight() {
            this.$inertia.delete(`/freights/${this.freight.id}`)
        },
    },
}
</script>

<template>
    <Head :title="`Frete #${freight.id}`" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <Link href="/freights" class="text-gray-400 hover:text-gray-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                        </svg>
                    </Link>
                    <h1 class="text-xl font-semibold text-gray-900">Frete #{{ freight.id }}</h1>
                    <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', statusColors[freight.status]]">
                        {{ statusLabels[freight.status] }}
                    </span>
                </div>

                <!-- Transition buttons -->
                <div class="flex gap-2">
                    <button v-if="freight.status === 'to_start'" @click="transition('to_in_route')"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-500 transition-colors">
                        Iniciar Frete
                    </button>
                    <button v-if="freight.status === 'in_route'" @click="showFinishModal = true"
                        class="rounded-lg bg-yellow-500 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-yellow-400 transition-colors">
                        Finalizar Frete
                    </button>
                    <template v-if="freight.status === 'finished'">
                        <button v-if="canComputeFreightValue" @click="transition('to_awaiting_payment')"
                            class="rounded-lg bg-orange-500 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-orange-400 transition-colors">
                            Enviar para Pagamento
                        </button>
                    </template>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-3xl space-y-6">

            <!-- No-price warning banner -->
            <div v-if="freight.status === 'finished' && !canComputeFreightValue"
                class="rounded-xl border border-amber-200 bg-amber-50 p-5">
                <div class="flex gap-3">
                    <svg class="h-5 w-5 shrink-0 text-amber-500 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                    </svg>
                    <div class="flex-1">
                        <p class="text-sm font-semibold text-amber-800">Não é possível enviar para pagamento</p>
                        <p class="mt-1 text-sm text-amber-700">
                            Nenhum preço está cadastrado para o tipo de veículo deste frete. Sem valor definido, a cobrança não pode ser gerada.
                        </p>
                        <div class="mt-4 flex flex-wrap gap-3">
                            <a v-if="rateEditLink" :href="rateEditLink"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-500 transition-colors">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75" />
                                </svg>
                                Configurar preço na tarifa
                            </a>
                            <button v-if="canDelete" @click="showDeleteConfirm = true"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-red-300 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50 transition-colors">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                </svg>
                                Excluir frete
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main info card -->
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                <div class="px-6 py-5 border-b border-gray-100">
                    <h2 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Informações</h2>
                </div>
                <dl class="divide-y divide-gray-100">
                    <div class="grid grid-cols-3 gap-4 px-6 py-4 text-sm">
                        <dt class="font-medium text-gray-500">Cliente</dt>
                        <dd class="col-span-2 text-gray-900">{{ freight.client?.name }}</dd>
                    </div>
                    <div class="grid grid-cols-3 gap-4 px-6 py-4 text-sm">
                        <dt class="font-medium text-gray-500">Tarifa</dt>
                        <dd class="col-span-2 text-gray-900">{{ freight.pricing_model === 'fixed' ? 'Fixo' : 'Por Km' }}</dd>
                    </div>
                    <template v-if="freight.origin">
                        <div class="grid grid-cols-3 gap-4 px-6 py-4 text-sm">
                            <dt class="font-medium text-gray-500">Origem / Destino</dt>
                            <dd class="col-span-2 text-gray-900">{{ freight.origin }} → {{ freight.destination }}</dd>
                        </div>
                    </template>
                    <div class="grid grid-cols-3 gap-4 px-6 py-4 text-sm">
                        <dt class="font-medium text-gray-500">Veículo</dt>
                        <dd class="col-span-2 font-mono text-gray-900">{{ freight.vehicle?.license_plate }} — {{ freight.vehicle?.brand }} {{ freight.vehicle?.model }}</dd>
                    </div>
                    <div v-if="freight.trailer" class="grid grid-cols-3 gap-4 px-6 py-4 text-sm">
                        <dt class="font-medium text-gray-500">Reboque</dt>
                        <dd class="col-span-2 font-mono text-gray-900">{{ freight.trailer.license_plate }}</dd>
                    </div>
                    <div class="grid grid-cols-3 gap-4 px-6 py-4 text-sm">
                        <dt class="font-medium text-gray-500">Motorista</dt>
                        <dd class="col-span-2 text-gray-900">{{ freight.driver?.name ?? '—' }}</dd>
                    </div>
                </dl>
            </div>

            <!-- Costs card (shown after finished) -->
            <div v-if="freight.finished_at" class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                <div class="px-6 py-5 border-b border-gray-100">
                    <h2 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Custos</h2>
                </div>
                <dl class="divide-y divide-gray-100">
                    <div class="grid grid-cols-3 gap-4 px-6 py-4 text-sm">
                        <dt class="font-medium text-gray-500">Km percorrido</dt>
                        <dd class="col-span-2 text-gray-900">{{ freight.distance_km ? `${freight.distance_km} km` : '—' }}</dd>
                    </div>
                    <div class="grid grid-cols-3 gap-4 px-6 py-4 text-sm">
                        <dt class="font-medium text-gray-500">Pedágio</dt>
                        <dd class="col-span-2 text-gray-900">{{ formatCurrency(freight.toll) }}</dd>
                    </div>
                    <div class="grid grid-cols-3 gap-4 px-6 py-4 text-sm">
                        <dt class="font-medium text-gray-500">Preço combustível (L)</dt>
                        <dd class="col-span-2 text-gray-900">{{ freight.fuel_price_per_liter ? formatCurrency(freight.fuel_price_per_liter) : '—' }}</dd>
                    </div>
                    <div v-if="freight.freight_value" class="grid grid-cols-3 gap-4 px-6 py-4 text-sm">
                        <dt class="font-medium text-gray-500">Valor do frete</dt>
                        <dd class="col-span-2 font-semibold text-gray-900">{{ formatCurrency(freight.freight_value) }}</dd>
                    </div>
                </dl>
            </div>

            <!-- Status history -->
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                <div class="px-6 py-5 border-b border-gray-100">
                    <h2 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Histórico</h2>
                </div>
                <ul class="divide-y divide-gray-100">
                    <li v-for="h in freight.status_history" :key="h.id" class="flex items-center gap-4 px-6 py-3 text-sm">
                        <div class="flex-1">
                            <span class="text-gray-500">{{ h.from_status ? statusLabels[h.from_status] + ' →' : '' }}</span>
                            <span class="ml-1 font-medium text-gray-900">{{ statusLabels[h.to_status] }}</span>
                            <span class="ml-2 text-gray-400">por {{ h.user?.name ?? 'sistema' }}</span>
                        </div>
                        <span class="text-gray-400">{{ formatDate(h.occurred_at) }}</span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Finish modal -->
        <div v-if="showFinishModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
            <div class="w-full max-w-md rounded-xl bg-white shadow-xl ring-1 ring-gray-200">
                <div class="px-6 py-5 border-b border-gray-100">
                    <h3 class="text-base font-semibold text-gray-900">Finalizar Frete</h3>
                </div>
                <div class="px-6 py-5 space-y-4">
                    <div v-if="freight.pricing_model === 'per_km'">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Km percorrido <span class="text-red-500">*</span></label>
                        <input v-model="finishForm.distance_km" type="number" step="0.1" min="1" placeholder="Ex: 480"
                            class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                        <p v-if="finishForm.errors.distance_km" class="mt-1.5 text-xs text-red-600">{{ finishForm.errors.distance_km }}</p>
                    </div>
                    <div v-else>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Km percorrido</label>
                        <input v-model="finishForm.distance_km" type="number" step="0.1" min="1" placeholder="Opcional"
                            class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            Pedágio {{ freight.pricing_model === 'per_km' ? '*' : '' }}
                            <span v-if="freight.pricing_model === 'fixed' && tollDefault" class="text-xs text-gray-400">(pré-preenchido da tarifa)</span>
                        </label>
                        <input
                            v-model="finishForm.toll"
                            v-maska="{ mask: ['#,##', '##,##', '###,##', '####,##', '#####,##'], tokens: { '#': { pattern: /[0-9]/ } } }"
                            type="text" inputmode="numeric" placeholder="0,00"
                            class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        />
                        <p v-if="finishForm.errors.toll" class="mt-1.5 text-xs text-red-600">{{ finishForm.errors.toll }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Preço do combustível (R$/L)</label>
                        <input
                            v-model="finishForm.fuel_price_per_liter"
                            v-maska="{ mask: ['#,####', '##,####'], tokens: { '#': { pattern: /[0-9]/ } } }"
                            type="text" inputmode="numeric" placeholder="0,0000"
                            class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        />
                    </div>

                    <!-- Computed estimates -->
                    <div v-if="estimatedLitersComputed" class="rounded-lg bg-blue-50 px-4 py-3 text-sm space-y-1">
                        <div class="flex justify-between text-blue-800">
                            <span>Litros estimados</span>
                            <span class="font-medium">{{ estimatedLitersComputed }} L</span>
                        </div>
                        <div v-if="estimatedFuelCost" class="flex justify-between text-blue-800">
                            <span>Custo combustível estimado</span>
                            <span class="font-medium">{{ formatCurrency(estimatedFuelCost) }}</span>
                        </div>
                    </div>
                </div>
                <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3">
                    <button @click="showFinishModal = false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancelar</button>
                    <button :disabled="finishForm.processing" @click="submitFinish"
                        class="rounded-lg bg-yellow-500 px-4 py-2 text-sm font-medium text-white hover:bg-yellow-400 disabled:opacity-60">
                        {{ finishForm.processing ? 'Salvando...' : 'Confirmar' }}
                    </button>
                </div>
            </div>
        </div>
        <!-- Delete confirmation modal -->
        <div v-if="showDeleteConfirm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
            <div class="w-full max-w-sm rounded-xl bg-white shadow-xl ring-1 ring-gray-200">
                <div class="px-6 py-5 border-b border-gray-100">
                    <h3 class="text-base font-semibold text-gray-900">Excluir frete #{{ freight.id }}?</h3>
                </div>
                <div class="px-6 py-5">
                    <p class="text-sm text-gray-600">Esta ação não pode ser desfeita. O frete e todo seu histórico de status serão removidos.</p>
                </div>
                <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3">
                    <button @click="showDeleteConfirm = false"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Cancelar
                    </button>
                    <button @click="deleteFreight"
                        class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-500">
                        Excluir
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
