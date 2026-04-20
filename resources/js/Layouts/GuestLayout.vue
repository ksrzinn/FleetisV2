<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
</script>

<template>
    <div class="flex min-h-screen bg-gray-50">
        <!-- Left branding panel (desktop only) -->
        <div class="hidden lg:flex lg:w-1/2 xl:w-2/5 flex-col justify-between bg-slate-900 px-12 py-10">
            <div class="flex items-center gap-3">
                <ApplicationLogo class="h-8 w-auto fill-current text-white" />
                <span class="text-xl font-bold tracking-tight text-white">Fleetis</span>
            </div>
            <div>
                <h1 class="text-4xl font-bold leading-tight text-white">
                    Gestão de frota<br />descomplicada.
                </h1>
                <p class="mt-4 text-lg text-slate-400">
                    Controle veículos, motoristas e remunerações em um só lugar.
                </p>
            </div>
            <p class="text-sm text-slate-500">&copy; {{ new Date().getFullYear() }} Fleetis</p>
        </div>

        <!-- Right form panel -->
        <div class="flex flex-1 flex-col items-center justify-center px-6 py-12">
            <!-- Mobile logo -->
            <div class="mb-8 flex items-center gap-3 lg:hidden">
                <ApplicationLogo class="h-9 w-auto fill-current text-slate-800" />
                <span class="text-xl font-bold tracking-tight text-slate-800">Fleetis</span>
            </div>

            <div class="w-full max-w-md">
                <slot />
            </div>
        </div>
    </div>
</template>
