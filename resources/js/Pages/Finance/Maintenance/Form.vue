<script>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head } from '@inertiajs/vue3'
export default {
    components: { AuthenticatedLayout, Head },
    props: { maintenanceRecord: Object, vehicles: Array },
}
</script>
<template>
    <Head title="Manutenção" />
    <AuthenticatedLayout><template #header><h1 class="text-xl font-semibold text-gray-900">Manutenção</h1></template></AuthenticatedLayout>
</template>
