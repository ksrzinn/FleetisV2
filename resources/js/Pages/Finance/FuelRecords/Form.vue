<script>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head } from '@inertiajs/vue3'
export default {
    components: { AuthenticatedLayout, Head },
    props: { fuelRecord: Object, vehicles: Array, drivers: Array },
}
</script>
<template>
    <Head title="Abastecimento" />
    <AuthenticatedLayout><template #header><h1 class="text-xl font-semibold text-gray-900">Abastecimento</h1></template></AuthenticatedLayout>
</template>
