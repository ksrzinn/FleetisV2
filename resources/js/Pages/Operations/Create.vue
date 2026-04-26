<script>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import axios from 'axios'

export default {
    components: { AuthenticatedLayout, Head, Link },

    props: {
        clients: Array,
        vehicles: Array,
        trailers: Array,
        drivers: Array,
        vehicleTypes: Array,
        brStates: Object,
    },

    setup() {
        const form = useForm({
            client_id: '',
            pricing_model: 'fixed',
            origin: '',
            destination: '',
            fixed_rate_id: '',
            per_km_rate_id: '',
            client_freight_table_id: '',
            per_km_state: '',
            vehicle_id: '',
            trailer_id: '',
            driver_id: '',
        })
        return { form }
    },

    data() {
        return {
            step: 1,
            freightTables: [],
            fixedRates: [],
            perKmRates: [],
            loadingRates: false,
            rateError: '',
        }
    },

    computed: {
        selectedVehicle() {
            return this.vehicles.find(v => v.id === this.form.vehicle_id) ?? null
        },
        requiresTrailer() {
            if (!this.selectedVehicle) return false
            const type = this.vehicleTypes.find(t => t.id === this.selectedVehicle.vehicle_type_id)
            return type?.requires_trailer ?? false
        },
        canAdvanceStep1() {
            if (!this.form.client_id || !this.form.pricing_model) return false
            if (this.form.pricing_model === 'per_km') {
                return this.form.origin.trim() !== '' && this.form.destination.trim() !== ''
            }
            return true
        },
        canAdvanceStep2() {
            if (this.form.pricing_model === 'fixed') return !!this.form.fixed_rate_id
            return !!this.form.per_km_rate_id
        },
        canAdvanceStep3() {
            if (!this.form.vehicle_id || !this.form.driver_id) return false
            if (this.requiresTrailer && !this.form.trailer_id) return false
            return true
        },
        reviewClient() {
            return this.clients.find(c => c.id === this.form.client_id)
        },
        reviewVehicle() {
            return this.selectedVehicle
        },
        reviewDriver() {
            return this.drivers.find(d => d.id === this.form.driver_id)
        },
        reviewTrailer() {
            return this.trailers.find(t => t.id === this.form.trailer_id)
        },
    },

    watch: {
        'form.client_id': 'loadRates',
        'form.pricing_model': 'loadRates',
        'form.client_freight_table_id'(tableId) {
            this.fixedRates = this.freightTables.find(t => t.id === tableId)?.fixed_rates ?? []
            this.form.fixed_rate_id = ''
        },
        'form.per_km_state'(state) {
            const match = this.perKmRates.find(r => r.state === state)
            this.form.per_km_rate_id = match?.id ?? ''
            this.rateError = match ? '' : 'Nenhuma tarifa cadastrada para este estado.'
        },
    },

    methods: {
        async loadRates() {
            if (!this.form.client_id || !this.form.pricing_model) return
            this.loadingRates = true
            this.rateError = ''
            this.freightTables = []
            this.fixedRates = []
            this.perKmRates = []
            this.form.fixed_rate_id = ''
            this.form.per_km_rate_id = ''
            this.form.client_freight_table_id = ''
            this.form.per_km_state = ''

            try {
                const { data } = await axios.get('/freight-rates', {
                    params: { client_id: this.form.client_id, pricing_model: this.form.pricing_model },
                })
                this.freightTables = data.tables ?? []
                this.perKmRates = data.rates ?? []
            } finally {
                this.loadingRates = false
            }
        },

        submit() {
            this.form.post('/freights')
        },

        formatCurrency(val) {
            if (!val) return '—'
            return Number(val).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
        },
    },
}
</script>

<template>
    <Head title="Novo Frete" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link href="/freights" class="text-gray-400 hover:text-gray-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                </Link>
                <h1 class="text-xl font-semibold text-gray-900">Novo Frete</h1>
            </div>
        </template>

        <div class="mx-auto max-w-2xl">
            <!-- Step indicator -->
            <div class="mb-6 flex items-center gap-2">
                <template v-for="n in 4" :key="n">
                    <div :class="['flex h-8 w-8 items-center justify-center rounded-full text-sm font-medium',
                        step === n ? 'bg-indigo-600 text-white' :
                        step > n ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-400']">
                        {{ n }}
                    </div>
                    <div v-if="n < 4" :class="['h-0.5 flex-1', step > n ? 'bg-indigo-300' : 'bg-gray-200']" />
                </template>
            </div>

            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">

                <!-- Step 1: Frete -->
                <div v-if="step === 1">
                    <div class="px-6 py-5 border-b border-gray-100">
                        <h2 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Passo 1 — Frete</h2>
                    </div>
                    <div class="px-6 py-5 space-y-5">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Cliente</label>
                            <select v-model="form.client_id" class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                <option value="">Selecione o cliente...</option>
                                <option v-for="c in clients" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                            <p v-if="form.errors.client_id" class="mt-1.5 text-xs text-red-600">{{ form.errors.client_id }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Tipo de Tarifa</label>
                            <div class="flex gap-4">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" v-model="form.pricing_model" value="fixed" class="text-indigo-600" />
                                    <span class="text-sm text-gray-700">Fixo</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" v-model="form.pricing_model" value="per_km" class="text-indigo-600" />
                                    <span class="text-sm text-gray-700">Por Km</span>
                                </label>
                            </div>
                        </div>

                        <template v-if="form.pricing_model === 'per_km'">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Origem</label>
                                <input v-model="form.origin" type="text" placeholder="Cidade de origem"
                                    class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                                <p v-if="form.errors.origin" class="mt-1.5 text-xs text-red-600">{{ form.errors.origin }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Destino</label>
                                <input v-model="form.destination" type="text" placeholder="Cidade de destino"
                                    class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                                <p v-if="form.errors.destination" class="mt-1.5 text-xs text-red-600">{{ form.errors.destination }}</p>
                            </div>
                        </template>
                    </div>
                    <div class="px-6 py-4 border-t border-gray-100 flex justify-end">
                        <button :disabled="!canAdvanceStep1" @click="step = 2"
                            class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-500 disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                            Próximo
                        </button>
                    </div>
                </div>

                <!-- Step 2: Tarifa -->
                <div v-if="step === 2">
                    <div class="px-6 py-5 border-b border-gray-100">
                        <h2 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Passo 2 — Tarifa</h2>
                    </div>
                    <div class="px-6 py-5 space-y-5">
                        <div v-if="loadingRates" class="text-sm text-gray-400">Carregando tarifas...</div>

                        <!-- Fixed rate selection -->
                        <template v-if="form.pricing_model === 'fixed' && !loadingRates">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Tabela de Fretes</label>
                                <select v-model="form.client_freight_table_id" class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                    <option value="">Selecione a tabela...</option>
                                    <option v-for="t in freightTables" :key="t.id" :value="t.id">{{ t.name }}</option>
                                </select>
                                <p v-if="freightTables.length === 0" class="mt-1.5 text-xs text-amber-600">Nenhuma tabela fixa cadastrada para este cliente.</p>
                            </div>
                            <div v-if="form.client_freight_table_id">
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Rota</label>
                                <select v-model="form.fixed_rate_id" class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                    <option value="">Selecione a rota...</option>
                                    <option v-for="r in fixedRates" :key="r.id" :value="r.id">{{ r.name }} <template v-if="r.avg_km">({{ r.avg_km }} km)</template></option>
                                </select>
                                <p v-if="form.errors.fixed_rate_id" class="mt-1.5 text-xs text-red-600">{{ form.errors.fixed_rate_id }}</p>
                            </div>
                        </template>

                        <!-- Per-km rate selection -->
                        <template v-if="form.pricing_model === 'per_km' && !loadingRates">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Estado</label>
                                <select v-model="form.per_km_state" class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                    <option value="">Selecione o estado...</option>
                                    <option v-for="(label, code) in brStates" :key="code" :value="code">{{ code }} — {{ label }}</option>
                                </select>
                                <p v-if="rateError" class="mt-1.5 text-xs text-red-600">{{ rateError }}</p>
                                <p v-if="form.errors.per_km_rate_id" class="mt-1.5 text-xs text-red-600">{{ form.errors.per_km_rate_id }}</p>
                            </div>
                        </template>
                    </div>
                    <div class="px-6 py-4 border-t border-gray-100 flex justify-between">
                        <button @click="step = 1" class="rounded-lg border border-gray-300 px-5 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">Voltar</button>
                        <button :disabled="!canAdvanceStep2" @click="step = 3"
                            class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-500 disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                            Próximo
                        </button>
                    </div>
                </div>

                <!-- Step 3: Equipe -->
                <div v-if="step === 3">
                    <div class="px-6 py-5 border-b border-gray-100">
                        <h2 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Passo 3 — Equipe</h2>
                    </div>
                    <div class="px-6 py-5 space-y-5">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Veículo</label>
                            <select v-model="form.vehicle_id" class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                <option value="">Selecione o veículo...</option>
                                <option v-for="v in vehicles" :key="v.id" :value="v.id">{{ v.license_plate }} — {{ v.brand }} {{ v.model }}</option>
                            </select>
                            <p v-if="form.errors.vehicle_id" class="mt-1.5 text-xs text-red-600">{{ form.errors.vehicle_id }}</p>
                        </div>

                        <div v-if="requiresTrailer">
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Reboque <span class="text-red-500">*</span></label>
                            <select v-model="form.trailer_id" class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                <option value="">Selecione o reboque...</option>
                                <option v-for="t in trailers" :key="t.id" :value="t.id">{{ t.license_plate }} — {{ t.brand }} {{ t.model }}</option>
                            </select>
                            <p v-if="form.errors.trailer_id" class="mt-1.5 text-xs text-red-600">{{ form.errors.trailer_id }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Motorista</label>
                            <select v-model="form.driver_id" class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                <option value="">Selecione o motorista...</option>
                                <option v-for="d in drivers" :key="d.id" :value="d.id">{{ d.name }}</option>
                            </select>
                            <p v-if="form.errors.driver_id" class="mt-1.5 text-xs text-red-600">{{ form.errors.driver_id }}</p>
                        </div>
                    </div>
                    <div class="px-6 py-4 border-t border-gray-100 flex justify-between">
                        <button @click="step = 2" class="rounded-lg border border-gray-300 px-5 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">Voltar</button>
                        <button :disabled="!canAdvanceStep3" @click="step = 4"
                            class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-500 disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                            Próximo
                        </button>
                    </div>
                </div>

                <!-- Step 4: Revisão -->
                <div v-if="step === 4">
                    <div class="px-6 py-5 border-b border-gray-100">
                        <h2 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Passo 4 — Revisão</h2>
                    </div>
                    <div class="px-6 py-5 space-y-3 text-sm">
                        <div class="grid grid-cols-2 gap-y-3">
                            <span class="text-gray-500">Cliente</span><span class="font-medium text-gray-900">{{ reviewClient?.name }}</span>
                            <span class="text-gray-500">Tipo de tarifa</span><span class="font-medium text-gray-900">{{ form.pricing_model === 'fixed' ? 'Fixo' : 'Por Km' }}</span>
                            <template v-if="form.pricing_model === 'per_km'">
                                <span class="text-gray-500">Origem</span><span class="font-medium text-gray-900">{{ form.origin }}</span>
                                <span class="text-gray-500">Destino</span><span class="font-medium text-gray-900">{{ form.destination }}</span>
                            </template>
                            <span class="text-gray-500">Veículo</span><span class="font-mono font-medium text-gray-900">{{ reviewVehicle?.license_plate }}</span>
                            <template v-if="reviewTrailer">
                                <span class="text-gray-500">Reboque</span><span class="font-mono font-medium text-gray-900">{{ reviewTrailer.license_plate }}</span>
                            </template>
                            <span class="text-gray-500">Motorista</span><span class="font-medium text-gray-900">{{ reviewDriver?.name }}</span>
                        </div>
                    </div>
                    <div class="px-6 py-4 border-t border-gray-100 flex justify-between">
                        <button @click="step = 3" class="rounded-lg border border-gray-300 px-5 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">Voltar</button>
                        <button :disabled="form.processing" @click="submit"
                            class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-500 disabled:opacity-60 transition-colors">
                            {{ form.processing ? 'Criando...' : 'Criar Frete' }}
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
