<script setup>
import { onMounted, reactive, ref } from 'vue';
import client from '../../api/client';

const items = ref([]);
const error = ref('');
const cargando = ref(false);
const editId = ref(null);

const form = reactive({ nombre: '', activo: true });

async function cargar() {
    cargando.value = true;
    error.value = '';
    try {
        const { data } = await client.get('/admin/cargos', { params: { todas: 1 } });
        items.value = data.data;
    } catch (err) {
        error.value = err.response?.data?.message || 'Error al cargar cargos.';
    } finally {
        cargando.value = false;
    }
}

onMounted(cargar);

function nuevo() {
    editId.value = null;
    form.nombre = '';
    form.activo = true;
}

function editar(item) {
    editId.value = item.id;
    form.nombre = item.nombre;
    form.activo = item.activo;
}

async function guardar() {
    error.value = '';
    const payload = { nombre: form.nombre, activo: form.activo };
    try {
        if (editId.value) {
            await client.put(`/admin/cargos/${editId.value}`, payload);
        } else {
            await client.post('/admin/cargos', payload);
        }
        nuevo();
        await cargar();
    } catch (err) {
        if (err.response?.status === 422) {
            error.value = Object.values(err.response.data.errors || {}).flat().join(' ');
        } else {
            error.value = err.response?.data?.message || 'No se pudo guardar el cargo.';
        }
    }
}

async function alternar(item) {
    try {
        await client.put(`/admin/cargos/${item.id}`, { activo: !item.activo });
        await cargar();
    } catch (err) {
        error.value = err.response?.data?.message || 'No se pudo cambiar el estado.';
    }
}
</script>

<template>
    <div class="mx-auto max-w-3xl">
        <h1 class="text-xl font-semibold text-ink">Cargos</h1>

        <div v-if="error" class="mt-4 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
            {{ error }}
        </div>

        <form class="mt-4 flex flex-wrap items-end gap-3 rounded-lg border bg-white p-4" @submit.prevent="guardar">
            <label class="text-sm font-medium">
                Nombre *
                <input v-model="form.nombre" type="text" required class="mt-1 w-64 rounded border border-gray-300 px-3 py-2 text-sm" />
            </label>
            <label class="flex items-center gap-2 pb-2 text-sm">
                <input v-model="form.activo" type="checkbox" />
                Activo
            </label>
            <button type="submit" class="rounded bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">
                {{ editId ? 'Guardar cambios' : 'Agregar cargo' }}
            </button>
            <button v-if="editId" type="button" class="rounded border px-4 py-2 text-sm text-gray-600 hover:bg-gray-100" @click="nuevo">
                Cancelar
            </button>
        </form>

        <div class="mt-4 overflow-x-auto rounded-lg border bg-white">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b bg-primary-700 text-xs font-medium uppercase text-white">
                    <tr>
                        <th class="px-4 py-3">Nombre</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="cargando">
                        <td colspan="3" class="px-4 py-6 text-center text-gray-500">Cargando…</td>
                    </tr>
                    <tr v-else-if="!items.length">
                        <td colspan="3" class="px-4 py-6 text-center text-gray-500">Sin cargos registrados.</td>
                    </tr>
                    <tr v-for="item in items" :key="item.id" class="border-b last:border-0 hover:bg-gray-50">
                        <td class="px-4 py-3">{{ item.nombre }}</td>
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
                            <button type="button" class="ml-3 text-gray-600 hover:underline" @click="alternar(item)">
                                {{ item.activo ? 'Desactivar' : 'Activar' }}
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
