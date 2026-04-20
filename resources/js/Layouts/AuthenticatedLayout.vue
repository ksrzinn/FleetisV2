<script>
import { Link } from '@inertiajs/vue3'
import ApplicationLogo from '@/Components/ApplicationLogo.vue'

export default {
    components: { Link, ApplicationLogo },

    data() {
        return {
            mobileOpen: false,
        }
    },

    computed: {
        navItems() {
            return [
                {
                    label: 'Dashboard',
                    route: 'dashboard',
                    match: 'dashboard',
                    icon: `<path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />`,
                },
                {
                    label: 'Veículos',
                    route: 'vehicles.index',
                    match: 'vehicles.*',
                    icon: `<path stroke-linecap="round" stroke-linejoin="round" d="M8 17h8M3 11h18M5 11V7a2 2 0 012-2h10a2 2 0 012 2v4M7 17v2m10-2v2" />`,
                },
                {
                    label: 'Motoristas',
                    route: 'drivers.index',
                    match: 'drivers.*',
                    icon: `<path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />`,
                },
                {
                    label: 'Clientes',
                    route: 'clients.index',
                    match: 'clients.*',
                    icon: `<path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />`,
                },
            ]
        },
    },

    methods: {
        isActive(match) {
            return route().current(match)
        },
    },
}
</script>

<template>
    <div class="flex h-screen overflow-hidden bg-gray-50">

        <!-- Mobile overlay -->
        <div
            v-if="mobileOpen"
            class="fixed inset-0 z-20 bg-black/50 lg:hidden"
            @click="mobileOpen = false"
        />

        <!-- Sidebar -->
        <aside
            :class="[
                'fixed inset-y-0 left-0 z-30 flex w-64 flex-col bg-slate-900 transition-transform duration-200 ease-in-out lg:static lg:translate-x-0',
                mobileOpen ? 'translate-x-0' : '-translate-x-full',
            ]"
        >
            <!-- Logo -->
            <div class="flex h-16 shrink-0 items-center gap-3 px-6 border-b border-slate-700">
                <ApplicationLogo class="h-8 w-auto fill-current text-white" />
                <span class="text-lg font-bold tracking-tight text-white">Fleetis</span>
            </div>

            <!-- Nav -->
            <nav class="flex-1 overflow-y-auto px-3 py-4">
                <ul class="space-y-1">
                    <li v-for="item in navItems" :key="item.route">
                        <Link
                            :href="route(item.route)"
                            :class="[
                                'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
                                isActive(item.match)
                                    ? 'bg-indigo-600 text-white'
                                    : 'text-slate-300 hover:bg-slate-800 hover:text-white',
                            ]"
                            @click="mobileOpen = false"
                        >
                            <svg
                                class="h-5 w-5 shrink-0"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.75"
                                viewBox="0 0 24 24"
                                v-html="item.icon"
                            />
                            {{ item.label }}
                        </Link>
                    </li>
                </ul>
            </nav>

            <!-- User -->
            <div class="shrink-0 border-t border-slate-700 p-4">
                <div class="mb-3 px-1">
                    <p class="text-sm font-medium text-white truncate">{{ $page.props.auth.user.name }}</p>
                    <p class="text-xs text-slate-400 truncate">{{ $page.props.auth.user.email }}</p>
                </div>
                <div class="flex gap-2">
                    <Link
                        :href="route('profile.edit')"
                        class="flex-1 rounded-md px-3 py-1.5 text-center text-xs font-medium text-slate-300 hover:bg-slate-800 hover:text-white transition-colors"
                    >
                        Perfil
                    </Link>
                    <Link
                        :href="route('logout')"
                        method="post"
                        as="button"
                        class="flex-1 rounded-md px-3 py-1.5 text-center text-xs font-medium text-slate-300 hover:bg-slate-800 hover:text-white transition-colors"
                    >
                        Sair
                    </Link>
                </div>
            </div>
        </aside>

        <!-- Main -->
        <div class="flex flex-1 flex-col overflow-hidden">

            <!-- Topbar (mobile only) -->
            <header class="flex h-16 shrink-0 items-center gap-4 border-b border-gray-200 bg-white px-4 lg:hidden">
                <button
                    class="rounded-md p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700"
                    @click="mobileOpen = true"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                <span class="text-sm font-semibold text-gray-800">Fleetis</span>
            </header>

            <!-- Page header slot (desktop) -->
            <header
                v-if="$slots.header"
                class="hidden border-b border-gray-200 bg-white px-8 py-5 lg:block"
            >
                <slot name="header" />
            </header>

            <!-- Page content -->
            <main class="flex-1 overflow-y-auto p-6 lg:p-8">
                <slot />
            </main>
        </div>
    </div>
</template>
