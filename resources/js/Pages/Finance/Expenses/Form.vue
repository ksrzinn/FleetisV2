<script>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head } from '@inertiajs/vue3'
export default {
    components: { AuthenticatedLayout, Head },
    props: { expense: Object, categories: Array, vehicles: Array },
}
</script>
<template>
    <Head title="Despesa" />
    <AuthenticatedLayout><template #header><h1 class="text-xl font-semibold text-gray-900">Despesa</h1></template></AuthenticatedLayout>
</template>
