<script setup>
import { onMounted, reactive, ref } from 'vue';
import client from '../../api/client';

const items = ref([]);
const roles = ref([]);
const meta = ref(null);
const error = ref('');
const cargando = ref(false);
const editId = ref(null);

const form = reactive({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role_ids: [],
    activo: true,
});

async function cargar(page = 1) {
    cargando.value = true;
    error.value = '';
    try {
        const { data } = await client.get('/admin/users', { params: { page } });
        items.value = data.data;
        meta.value = data.meta;
    } catch (err) {
        error.value = err.response?.data?.message || 'Error al cargar usuarios.';
    } finally {
        cargando.value = false;
    }
}

async function cargarRoles() {
    const { data } = await client.get('/catalogos/roles');
    roles.value = data.data;
}

onMounted(() => {
    cargar();
    cargarRoles();
});

function nuevo() {
    editId.value = null;
    form.name = '';
    form.email = '';
    form.password = '';
    form.password_confirmation = '';
    form.role_ids = [];
    form.activo = true;
}

function editar(item) {
    editId.value = item.id;
    form.name = item.name;
    form.email = item.email;
    form.password = '';
    form.password_confirmation = '';
    form.role_ids = item.roles?.map((r) => r.id) || [];
    form.activo = item.activo;
}

async function guardar() {
    error.value = '';
    const payload = {
        name: form.name,
        email: form.email,
        role_ids: form.role_ids,
        activo: form.activo,
    };
    if (form.password) {
        payload.password = form.password;
        payload.password_confirmation = form.password_confirmation;
    }

    try {
        if (editId.value) {
            await client.put(`/admin/users/${editId.value}`, payload);
        } else {
            await client.post('/admin/users', payload);
        }
        nuevo();
        await cargar();
    } catch (err) {
        if (err.response?.status === 422) {
            error.value = Object.values(err.response.data.errors || {}).flat().join(' ');
        } else {
            error.value = err.response?.data?.message || 'No se pudo guardar el usuario.';
        }
    }
}
</script>

<template>
    <div class="mx-auto max-w-4xl">
        <h1 class="text-xl font-semibold text-ink">Usuarios y permisos</h1>

        <div v-if="error" class="mt-4 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
            {{ error }}
        </div>

        <form class="mt-4 grid grid-cols-1 gap-3 rounded-lg border bg-white p-4 sm:grid-cols-2" @submit.prevent="guardar">
            <label class="text-sm font-medium">
                Nombre *
                <input v-model="form.name" type="text" required class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm" />
            </label>
            <label class="text-sm font-medium">
                Correo *
                <input v-model="form.email" type="email" required class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm" />
            </label>
            <label class="text-sm font-medium">
                Contraseña {{ editId ? '(dejar vacío para no cambiar)' : '*' }}
                <input
                    v-model="form.password"
                    type="password"
                    :required="!editId"
                    autocomplete="new-password"
                    class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm"
                />
            </label>
            <label class="text-sm font-medium">
                Confirmar contraseña
                <input
                    v-model="form.password_confirmation"
                    type="password"
                    :required="!editId"
                    autocomplete="new-password"
                    class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm"
                />
            </label>
            <fieldset class="sm:col-span-2">
                <legend class="text-sm font-medium">Perfiles</legend>
                <div class="mt-2 flex flex-wrap gap-4 text-sm">
                    <label v-for="rol in roles" :key="rol.id" class="flex items-center gap-2">
                        <input v-model="form.role_ids" type="checkbox" :value="rol.id" />
                        {{ rol.name }}
                    </label>
                </div>
            </fieldset>
            <label class="flex items-center gap-2 text-sm">
                <input v-model="form.activo" type="checkbox" />
                Usuario activo
            </label>
            <div class="flex gap-3">
                <button type="submit" class="rounded bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">
                    {{ editId ? 'Guardar cambios' : 'Crear usuario' }}
                </button>
                <button v-if="editId" type="button" class="rounded border px-4 py-2 text-sm text-gray-600 hover:bg-gray-100" @click="nuevo">
                    Cancelar
                </button>
            </div>
        </form>

        <div class="mt-4 overflow-x-auto rounded-lg border bg-white">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b bg-primary-700 text-xs font-medium uppercase text-white">
                    <tr>
                        <th class="px-4 py-3">Nombre</th>
                        <th class="px-4 py-3">Correo</th>
                        <th class="px-4 py-3">Perfiles</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="cargando">
                        <td colspan="5" class="px-4 py-6 text-center text-gray-500">Cargando…</td>
                    </tr>
                    <tr v-for="item in items" :key="item.id" class="border-b last:border-0 hover:bg-gray-50">
                        <td class="px-4 py-3">{{ item.name }}</td>
                        <td class="px-4 py-3">{{ item.email }}</td>
                        <td class="px-4 py-3 text-xs">
                            <span
                                v-for="rol in item.roles"
                                :key="rol.id"
                                class="mr-1 rounded-full bg-primary-50 px-2 py-0.5 text-primary-700"
                            >
                                {{ rol.name }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span
                                class="rounded-full px-2 py-0.5 text-xs"
                                :class="item.activo ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                            >
                                {{ item.activo ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button type="button" class="text-primary-700 hover:underline" @click="editar(item)">Editar</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="meta" class="mt-3 flex items-center justify-between text-sm text-gray-600">
            <span>Página {{ meta.current_page }} de {{ meta.last_page }}</span>
            <div class="flex gap-2">
                <button
                    type="button"
                    class="rounded border px-3 py-1.5 disabled:opacity-40"
                    :disabled="meta.current_page <= 1"
                    @click="cargar(meta.current_page - 1)"
                >
                    Anterior
                </button>
                <button
                    type="button"
                    class="rounded border px-3 py-1.5 disabled:opacity-40"
                    :disabled="meta.current_page >= meta.last_page"
                    @click="cargar(meta.current_page + 1)"
                >
                    Siguiente
                </button>
            </div>
        </div>
    </div>
</template>
