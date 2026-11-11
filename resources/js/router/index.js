import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import LoginView from '../views/LoginView.vue';
import DocumentsListView from '../views/DocumentsListView.vue';
import DocumentCreateView from '../views/DocumentCreateView.vue';
import DocumentDetailView from '../views/DocumentDetailView.vue';
import TurnosView from '../views/TurnosView.vue';
import AreasView from '../views/admin/AreasView.vue';
import CargosView from '../views/admin/CargosView.vue';
import TitularesView from '../views/admin/TitularesView.vue';
import UsuariosView from '../views/admin/UsuariosView.vue';
import BitacoraView from '../views/BitacoraView.vue';
import DashboardView from '../views/DashboardView.vue';

const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/login', name: 'login', component: LoginView, meta: { guest: true } },
        { path: '/', name: 'dashboard', component: DashboardView, meta: { requiresAuth: true } },
        { path: '/documentos', name: 'documents.index', component: DocumentsListView, meta: { requiresAuth: true } },
        {
            path: '/documentos/nuevo',
            name: 'documents.create',
            component: DocumentCreateView,
            meta: { requiresAuth: true, permissions: ['documents.create'] },
        },
        {
            path: '/documentos/:folio',
            name: 'documents.show',
            component: DocumentDetailView,
            props: true,
            meta: { requiresAuth: true },
        },
        {
            path: '/turnos',
            name: 'turnos.index',
            component: TurnosView,
            meta: { requiresAuth: true, permissions: ['documents.view'] },
        },
        {
            path: '/admin/areas',
            name: 'admin.areas',
            component: AreasView,
            meta: { requiresAuth: true, permissions: ['admin.catalogs'] },
        },
        {
            path: '/admin/cargos',
            name: 'admin.cargos',
            component: CargosView,
            meta: { requiresAuth: true, permissions: ['admin.catalogs'] },
        },
        {
            path: '/admin/titulares',
            name: 'admin.titulares',
            component: TitularesView,
            meta: { requiresAuth: true, permissions: ['admin.catalogs'] },
        },
        {
            path: '/admin/usuarios',
            name: 'admin.usuarios',
            component: UsuariosView,
            meta: { requiresAuth: true, permissions: ['admin.users'] },
        },
        {
            path: '/bitacora',
            name: 'bitacora',
            component: BitacoraView,
            meta: { requiresAuth: true, permissions: ['audit.view'] },
        },
    ],
});

router.beforeEach(async (to) => {
    const auth = useAuthStore();

    if (to.meta.requiresAuth && !auth.isAuthenticated) {
        return { name: 'login', query: { redirect: to.fullPath } };
    }

    if (to.meta.guest && auth.isAuthenticated) {
        return { name: 'dashboard' };
    }

    if (to.meta.requiresAuth && !auth.user) {
        try {
            await auth.fetchMe();
        } catch {
            return { name: 'login' };
        }
    }

    if (to.meta.permissions?.length) {
        const authorized = to.meta.permissions.every((permission) => auth.has(permission));
        if (!authorized) {
            return { name: 'documents.index' };
        }
    }

    return true;
});

export default router;
