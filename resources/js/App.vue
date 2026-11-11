<script setup>
import { computed, onMounted, ref } from 'vue';
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router';
import { useAuthStore } from './stores/auth';
import SideIcon from './components/SideIcon.vue';

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();
const menuAbierto = ref(false);

const iconos = {
    sobre: 'M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75',
    documentos: 'M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5A3.375 3.375 0 0 0 10.125 2.25H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z',
    registrar: 'M12 9v6m3-3H9m12 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
    turnos: 'M11.35 3.836c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m8.9-4.414c.376.023.75.05 1.124.08 1.131.094 1.976 1.057 1.976 2.192V16.5A2.25 2.25 0 0 1 18 18.75h-2.25m-7.5-10.5H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V18.75m-7.5-10.5h6.375c.621 0 1.125.504 1.125 1.125v9.375m-8.25-3 1.5 1.5 3-3.75',
    areas: 'm9.06 11.9 8.07-8.06a2.85 2.85 0 1 1 4.03 4.03l-8.06 8.08M7.07 14.94c-1.66 0-3 1.35-3 3.02 0 1.33-2.5 1.52-2 2.02 1.08 1.1 2.49 2.02 4 2.02 2.2 0 4-1.8 4-4.04a3.01 3.01 0 0 0-3-3.02z',
    cargos: 'M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0M12 12.75h.008v.008H12v-.008Z',
    titulares: 'M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM5.25 20.25a8.25 8.25 0 0 1 13.5 0',
    usuarios: 'M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z',
    bitacora: 'M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25',
    menu: 'M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5',
    inicio: 'M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25',
    salir: 'M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9',
};

const rol = computed(() => auth.user?.roles?.[0]?.name || '');
const iniciales = computed(() =>
    (auth.user?.name || '')
        .split(' ')
        .map((parte) => parte[0])
        .slice(0, 2)
        .join('')
        .toUpperCase(),
);

onMounted(async () => {
    if (auth.isAuthenticated && !auth.user) {
        try {
            await auth.fetchMe();
        } catch {
            // El interceptor de axios se encarga de la sesión expirada.
        }
    }
});

async function logout() {
    menuAbierto.value = false;
    await auth.logout();
    router.push({ name: 'login' });
}

function activo(prefijo) {
    return route.path.startsWith(prefijo);
}

function activoDocumentos() {
    return route.path.startsWith('/documentos') && !route.path.startsWith('/documentos/nuevo');
}

function claseItem(activo) {
    return activo
        ? 'bg-accent-500 font-semibold text-white shadow-sm'
        : 'text-white/85 hover:bg-white/10 hover:text-white';
}

function cerrarMenu() {
    menuAbierto.value = false;
}
</script>

<template>
    <div class="min-h-screen bg-cream text-gray-900">
        <aside
            v-if="auth.isAuthenticated"
            class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col bg-primary-800 text-white transition-transform duration-200 lg:translate-x-0"
            :class="menuAbierto ? 'translate-x-0' : '-translate-x-full'"
        >
            <div class="flex items-center gap-3 px-5 py-5">
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-accent-500 text-primary-900">
                    <SideIcon :path="iconos.sobre" class="h-5 w-5" />
                </span>
                <span class="text-sm font-semibold leading-tight">
                    Sistema de<br />Correspondencia
                </span>
            </div>

            <nav class="flex-1 overflow-y-auto px-3 pb-36">
                <RouterLink
                    to="/"
                    class="mb-1 mt-1 flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm transition-colors"
                    :class="claseItem(route.path === '/')"
                    @click="cerrarMenu"
                >
                    <SideIcon :path="iconos.inicio" />
                    Inicio
                </RouterLink>
                <p class="px-3 pb-1 pt-4 text-[11px] font-semibold uppercase tracking-wider text-accent-300">
                    Correspondencia
                </p>
                <RouterLink
                    to="/documentos"
                    class="mb-1 flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm transition-colors"
                    :class="claseItem(activoDocumentos())"
                    @click="cerrarMenu"
                >
                    <SideIcon :path="iconos.documentos" />
                    Documentos
                </RouterLink>
                <RouterLink
                    v-if="auth.has('documents.create')"
                    to="/documentos/nuevo"
                    class="mb-1 flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm transition-colors"
                    :class="claseItem(activo('/documentos/nuevo'))"
                    @click="cerrarMenu"
                >
                    <SideIcon :path="iconos.registrar" />
                    Registrar
                </RouterLink>

                <p
                    v-if="auth.has('documents.view')"
                    class="px-3 pb-1 pt-4 text-[11px] font-semibold uppercase tracking-wider text-accent-300"
                >
                    Turnos
                </p>
                <RouterLink
                    v-if="auth.has('documents.view')"
                    to="/turnos"
                    class="mb-1 flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm transition-colors"
                    :class="claseItem(activo('/turnos'))"
                    @click="cerrarMenu"
                >
                    <SideIcon :path="iconos.turnos" />
                    Bandeja de turnos
                </RouterLink>

                <template v-if="auth.has('admin.catalogs') || auth.has('admin.users')">
                    <p class="px-3 pb-1 pt-4 text-[11px] font-semibold uppercase tracking-wider text-accent-300">
                        Administración
                    </p>
                    <template v-if="auth.has('admin.catalogs')">
                        <RouterLink
                            to="/admin/areas"
                            class="mb-1 flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm transition-colors"
                            :class="claseItem(activo('/admin/areas'))"
                            @click="cerrarMenu"
                        >
                            <SideIcon :path="iconos.areas" />
                            Áreas
                        </RouterLink>
                        <RouterLink
                            to="/admin/cargos"
                            class="mb-1 flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm transition-colors"
                            :class="claseItem(activo('/admin/cargos'))"
                            @click="cerrarMenu"
                        >
                            <SideIcon :path="iconos.cargos" />
                            Cargos
                        </RouterLink>
                        <RouterLink
                            to="/admin/titulares"
                            class="mb-1 flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm transition-colors"
                            :class="claseItem(activo('/admin/titulares'))"
                            @click="cerrarMenu"
                        >
                            <SideIcon :path="iconos.titulares" />
                            Titulares
                        </RouterLink>
                    </template>
                    <RouterLink
                        v-if="auth.has('admin.users')"
                        to="/admin/usuarios"
                        class="mb-1 flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm transition-colors"
                        :class="claseItem(activo('/admin/usuarios'))"
                        @click="cerrarMenu"
                    >
                        <SideIcon :path="iconos.usuarios" />
                        Usuarios
                    </RouterLink>
                </template>

                <template v-if="auth.has('audit.view')">
                    <p class="px-3 pb-1 pt-4 text-[11px] font-semibold uppercase tracking-wider text-accent-300">
                        Auditoría
                    </p>
                    <RouterLink
                        to="/bitacora"
                        class="mb-1 flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm transition-colors"
                        :class="claseItem(activo('/bitacora'))"
                        @click="cerrarMenu"
                    >
                        <SideIcon :path="iconos.bitacora" />
                        Bitácora
                    </RouterLink>
                </template>
            </nav>

            <div class="pointer-events-none absolute inset-x-0 bottom-0 h-32 overflow-hidden" aria-hidden="true">
                <svg
                    class="h-32 w-full"
                    viewBox="0 0 256 128"
                    fill="none"
                    preserveAspectRatio="none"
                >
                    <g class="stroke-accent-500 opacity-70" stroke-width="1.6">
                        <path d="M14 96V72h14v24" />
                        <path d="M34 96V58h12v38" />
                        <path d="M52 96V78h16v18" />
                        <path d="M74 96V50h10v46" />
                        <path d="M90 96V66h14v30" />
                        <path d="M112 96V44c0-8 6-14 14-14s14 6 14 14v52" />
                        <path d="M144 96V62h12v34" />
                        <path d="M162 96V74h16v22" />
                        <path d="M184 96V54h10v42" />
                        <path d="M200 96V70h14v26" />
                        <path d="M220 96V60h12v36" />
                        <path d="M238 96V80h10v16" />
                    </g>
                    <path class="fill-accent-500 opacity-30" d="M0 88c40-14 80 14 128 0s88-14 128 0v40H0Z" />
                    <path class="fill-accent-500 opacity-55" d="M0 104c48-14 80 12 128 0s80-12 128 0v24H0Z" />
                </svg>
            </div>
        </aside>

        <div
            v-if="auth.isAuthenticated && menuAbierto"
            class="fixed inset-0 z-30 bg-black/50 lg:hidden"
            @click="cerrarMenu"
        />

        <div :class="auth.isAuthenticated ? 'lg:pl-64' : ''">
            <header class="sticky top-0 z-20 border-b border-primary-100 bg-white">
                <div class="flex h-14 items-center justify-between gap-4 px-4">
                    <div class="flex min-w-0 items-center gap-3">
                        <button
                            v-if="auth.isAuthenticated"
                            type="button"
                            class="rounded-lg p-1.5 text-primary-700 hover:bg-primary-50 lg:hidden"
                            aria-label="Abrir menú"
                            @click="menuAbierto = true"
                        >
                            <SideIcon :path="iconos.menu" class="h-5 w-5" />
                        </button>
                        <RouterLink to="/" class="flex min-w-0 items-center gap-2.5">
                            <span class="hidden h-8 w-8 items-center justify-center rounded-lg bg-primary-600 text-white sm:flex">
                                <SideIcon :path="iconos.sobre" />
                            </span>
                            <span class="truncate text-base font-bold text-primary-700">
                                Sistema de Correspondencia
                            </span>
                        </RouterLink>
                        <span class="hidden h-5 w-px bg-gray-200 md:block" />
                        <span class="hidden truncate text-sm font-semibold text-blue-600 md:block">
                            Comisión para la Reconstrucción
                        </span>
                    </div>

                    <div v-if="auth.isAuthenticated" class="flex items-center gap-3">
                        <div v-if="auth.user" class="hidden text-right sm:block">
                            <p class="text-sm font-semibold leading-tight text-ink">{{ auth.user.name }}</p>
                            <p class="text-xs leading-tight text-gray-500">{{ rol }}</p>
                        </div>
                        <span
                            class="flex h-9 w-9 items-center justify-center rounded-full border-2 border-primary-600 text-xs font-bold text-primary-700"
                        >
                            {{ iniciales }}
                        </span>
                        <button
                            type="button"
                            class="flex items-center gap-1.5 rounded-lg border border-primary-600 px-3 py-1.5 text-sm font-medium text-primary-700 transition-colors hover:bg-primary-50"
                            @click="logout"
                        >
                            <SideIcon :path="iconos.salir" />
                            Salir
                        </button>
                    </div>
                </div>
            </header>

            <main class="mx-auto max-w-7xl px-4 py-6">
                <RouterView />
            </main>
        </div>
    </div>
</template>
