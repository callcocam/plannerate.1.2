<template>
    <div class="h-full w-full overflow-y-auto border-l border-gray-200 bg-white p-6">
        <div class="space-y-6">
            <!-- Modo de edição -->
            <form @submit.prevent="saveChanges" class="space-y-4">
                <div>
                    <h3 class="text-sm font-medium text-gray-500">Informações Básicas</h3>
                    <div class="mt-2 space-y-3">
                        <div class="space-y-1">
                            <Label for="code">Código</Label>
                            <Input id="code" v-model="formData.code" />
                        </div>
                        <div class="space-y-1">
                            <Label for="product_type">Tipo de Produto</Label>
                            <Select v-model="formData.product_type">
                                <SelectTrigger>
                                    <SelectValue placeholder="Selecionar" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="normal">Normal</SelectItem>
                                    <SelectItem value="special">Especial</SelectItem>
                                    <SelectItem value="custom">Personalizado</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="space-y-1">
                            <Label for="status">Status</Label>
                            <Select v-model="formData.status">
                                <SelectContent>
                                    <SelectItem value="published">Publicado</SelectItem>
                                    <SelectItem value="draft">Rascunho</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                </div>

                <Separator />

                <div>
                    <h3 class="text-sm font-medium text-gray-500">Dimensões</h3>
                    <div class="mt-2 grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <Label for="shelf_width">Largura (cm)</Label>
                            <Input id="shelf_width" v-model.number="formData.shelf_width" type="number" min="0" />
                        </div>
                        <div class="space-y-1">
                            <Label for="shelf_height">Altura (cm)</Label>
                            <Input id="shelf_height" v-model.number="formData.shelf_height" type="number" min="0" />
                        </div>
                        <div class="space-y-1">
                            <Label for="shelf_depth">Profundidade (cm)</Label>
                            <Input id="shelf_depth" v-model.number="formData.shelf_depth" type="number" min="0" />
                        </div>
                        <div class="space-y-1">
                            <Label for="shelf_position">Posição (cm)</Label>
                            <Input id="shelf_position" v-model.number="formData.shelf_position" type="number" min="0" />
                        </div>
                        <div class="space-y-1">
                            <Label for="spacing">Espaçamento (cm)</Label>
                            <Input id="spacing" v-model.number="formData.spacing" type="number" min="0" />
                        </div>
                    </div>
                </div>

                <Separator />

                <div>
                    <h3 class="text-sm font-medium text-gray-500">Seção</h3>
                    <div class="mt-2 space-y-3">
                        <div class="space-y-1">
                            <Label for="ordering">Ordem</Label>
                            <Input id="ordering" v-model.number="formData.ordering" type="number" min="0" />
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end space-x-2 pt-4">
                    <Button @click="cancelEditing" variant="outline">Cancelar</Button>
                    <Button type="submit" variant="default">Salvar Alterações</Button>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';

import { useShelvesStore } from '../../../store/shelves';
import { Shelf } from '../../../types/shelves';

const shelvesStore = useShelvesStore();

const selectedShelf = computed(() => shelvesStore.getSelectedShelf);
const isEditing = computed(() => shelvesStore.isEditingShelf);

// Inicializa o formulário com valores padrão
const formData = ref<Partial<Shelf>>({
    code: '',
    product_type: 'normal',
    status: 'published',
    shelf_width: 0,
    shelf_height: 0,
    shelf_depth: 0,
    shelf_position: 0,
    spacing: 0,
    ordering: 0,
});

// Atualiza o formulário quando a prateleira selecionada muda
watch(
    selectedShelf,
    (newShelf) => {
        if (newShelf) {
            formData.value = {
                code: newShelf.code,
                product_type: newShelf.product_type,
                status: newShelf.status,
                shelf_width: newShelf.shelf_width,
                shelf_height: newShelf.shelf_height,
                shelf_depth: newShelf.shelf_depth,
                shelf_position: newShelf.shelf_position,
                spacing: newShelf.spacing,
                ordering: newShelf.ordering,
            };
        }
    },
    { immediate: true },
);

// Métodos para gerenciar a edição
const startEditing = () => {
    shelvesStore.startEditing();
};

const cancelEditing = () => {
    shelvesStore.finishEditing();

    // Reset do formulário para os valores originais
    if (selectedShelf.value) {
        formData.value = {
            code: selectedShelf.value.code,
            product_type: selectedShelf.value.product_type,
            status: selectedShelf.value.status,
            shelf_width: selectedShelf.value.shelf_width,
            shelf_height: selectedShelf.value.shelf_height,
            shelf_depth: selectedShelf.value.shelf_depth,
            shelf_position: selectedShelf.value.shelf_position,
            spacing: selectedShelf.value.spacing,
            ordering: selectedShelf.value.ordering,
        };
    }
};

const saveChanges = () => {
    shelvesStore.updateShelf(formData.value);
    shelvesStore.finishEditing();

    // Aqui você poderia adicionar uma chamada para API para persistir as alterações
    // Por exemplo:
    // apiService.updateShelf(selectedShelf.value.id, formData.value);
};

// Funções auxiliares
const formatDate = (dateString: string) => {
    if (!dateString) return '';

    const date = new Date(dateString);
    return new Intl.DateTimeFormat('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
};

const getStatusClass = (status: string) => {
    switch (status) {
        case 'published':
            return 'bg-green-100 text-green-800';
        case 'draft':
            return 'bg-yellow-100 text-yellow-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
};
</script>
