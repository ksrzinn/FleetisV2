<script>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head } from '@inertiajs/vue3'
export default {
    components: { AuthenticatedLayout, Head },
    props: { fuelRecords: Object, vehicles: Array, drivers: Array, filters: Object },
}
</script>
<template>
    <Head title="Abastecimentos" />
    <AuthenticatedLayout><template #header><h1 class="text-xl font-semibold text-gray-900">Abastecimentos</h1></template></AuthenticatedLayout>
</template>
