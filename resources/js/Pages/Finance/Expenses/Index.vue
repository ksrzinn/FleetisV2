<script>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head } from '@inertiajs/vue3'
export default {
    components: { AuthenticatedLayout, Head },
    props: { expenses: Object, categories: Array, vehicles: Array, filters: Object },
}
</script>
<template>
    <Head title="Despesas" />
    <AuthenticatedLayout><template #header><h1 class="text-xl font-semibold text-gray-900">Despesas</h1></template></AuthenticatedLayout>
</template>
