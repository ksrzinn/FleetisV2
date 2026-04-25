<script>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head } from '@inertiajs/vue3'
export default {
    components: { AuthenticatedLayout, Head },
    props: { maintenanceRecords: Object, vehicles: Array, filters: Object },
}
</script>
<template>
    <Head title="Manutenções" />
    <AuthenticatedLayout><template #header><h1 class="text-xl font-semibold text-gray-900">Manutenções</h1></template></AuthenticatedLayout>
</template>
