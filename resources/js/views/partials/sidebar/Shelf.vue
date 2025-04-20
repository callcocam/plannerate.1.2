<template>
    <div class="shelves h-full w-full overflow-y-auto border-l border-gray-200 bg-white p-6">
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
                                <SelectTrigger>
                                    <SelectValue placeholder="Selecionar" />
                                </SelectTrigger>
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
import { computed, Ref, ref, watch } from 'vue';

import { useEditorStore } from '../../../store/editor';
import { Shelf } from '../../../types/shelves';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';

const editorStore = useEditorStore();

const selectedShelf = computed(() => editorStore.getSelectedShelf as Shelf);

const formData = ref<Partial<Shelf>>({});

watch(
    selectedShelf,
    (newShelf) => {
        if (newShelf) {
            formData.value = { ...newShelf };
        } else {
            formData.value = {};
        }
    },
    { immediate: true, deep: true },
);

const cancelEditing = () => {
    if (selectedShelf.value) {
        formData.value = { ...selectedShelf.value };
    }
    editorStore.setIsShelfEditing(false);
};

const saveChanges = () => {
    if (!selectedShelf.value || !formData.value) return;

    const sectionId = selectedShelf.value.section_id;
    const shelfId = selectedShelf.value.id;
    let correctGondolaId: string | null = null;

    if (!editorStore.currentState || !editorStore.currentState.gondolas) {
        console.error('Erro ao salvar: Estado do editor ou gôndolas não encontrados.');
        return;
    }

    for (const gondola of editorStore.currentState.gondolas) {
        if (gondola.sections.some(section => section.id === sectionId)) {
            correctGondolaId = gondola.id;
            break;
        }
    }

    if (!correctGondolaId) {
        console.error(`Erro ao salvar: Gôndola contendo a seção ${sectionId} não encontrada no estado do editor.`);
        return;
    }
    if (!sectionId) {
        console.error('Erro ao salvar: ID da Seção não encontrado na prateleira selecionada.');
        return;
    }

    try {
        editorStore.updateShelfData(correctGondolaId, sectionId, shelfId, formData.value);
        console.log('Alterações da prateleira enviadas para o editorStore.');
        editorStore.setIsShelfEditing(false);
    } catch (error) {
        console.error('Erro ao salvar as alterações da prateleira via editorStore:', error);
    }
};
</script>
