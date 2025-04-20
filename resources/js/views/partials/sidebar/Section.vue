<template>
    <div class="h-full w-full overflow-y-auto border-l border-gray-200 bg-white p-6">
        <div class="space-y-6" v-if="selectedSection">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-medium">Propriedades da Seção</h2>
                <Button v-if="!isEditing" @click="startEditing" variant="outline" size="sm">
                    <PencilIcon class="mr-2 h-4 w-4" />
                    Editar
                </Button>
            </div>

            <!-- Modo de visualização -->
            <div v-if="!isEditing" class="space-y-4">
                <div>
                    <h3 class="text-sm font-medium text-gray-500">Informações Básicas</h3>
                    <div class="mt-2 space-y-3">
                        <div>
                            <span class="text-xs text-gray-500">Nome</span>
                            <p class="text-sm">{{ selectedSection.name }}</p>
                        </div>
                        <div>
                            <span class="text-xs text-gray-500">ID</span>
                            <p class="font-mono text-sm">{{ selectedSection.id }}</p>
                        </div>
                        <div>
                            <span class="text-xs text-gray-500">Status</span>
                            <div class="mt-1 flex items-center">
                                <span :class="selectedSection.status.color" class="inline-block rounded-full px-2 py-1 text-xs">
                                    {{ selectedSection.status.label }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <Separator />

                <div>
                    <h3 class="text-sm font-medium text-gray-500">Dimensões</h3>
                    <div class="mt-2 grid grid-cols-2 gap-3">
                        <div>
                            <span class="text-xs text-gray-500">Largura</span>
                            <p class="text-sm">{{ selectedSection.width }}cm</p>
                        </div>
                        <div>
                            <span class="text-xs text-gray-500">Altura</span>
                            <p class="text-sm">{{ selectedSection.height }}cm</p>
                        </div>
                        <div>
                            <span class="text-xs text-gray-500">Base (L)</span>
                            <p class="text-sm">{{ selectedSection.base_width }}cm</p>
                        </div>
                        <div>
                            <span class="text-xs text-gray-500">Base (A)</span>
                            <p class="text-sm">{{ selectedSection.base_height }}cm</p>
                        </div>
                        <div>
                            <span class="text-xs text-gray-500">Base (P)</span>
                            <p class="text-sm">{{ selectedSection.base_depth }}cm</p>
                        </div>
                    </div>
                </div>

                <Separator />

                <div>
                    <h3 class="text-sm font-medium text-gray-500">Cremalheira</h3>
                    <div class="mt-2 grid grid-cols-2 gap-3">
                        <div>
                            <span class="text-xs text-gray-500">Largura</span>
                            <p class="text-sm">{{ selectedSection.cremalheira_width }}cm</p>
                        </div>
                        <div>
                            <span class="text-xs text-gray-500">Buracos</span>
                            <p class="text-sm">{{ selectedSection.settings.holes.length }}</p>
                        </div>
                        <div>
                            <span class="text-xs text-gray-500">Tam. Buraco</span>
                            <p class="text-sm">{{ selectedSection.hole_width }}x{{ selectedSection.hole_height }}cm</p>
                        </div>
                        <div>
                            <span class="text-xs text-gray-500">Espaçamento</span>
                            <p class="text-sm">{{ selectedSection.hole_spacing }}cm</p>
                        </div>
                    </div>
                </div>

                <Separator />

                <div>
                    <h3 class="text-sm font-medium text-gray-500">Prateleiras</h3>
                    <div class="mt-2">
                        <div>
                            <span class="text-xs text-gray-500">Quantidade</span>
                            <p class="text-sm">{{ selectedSection.num_shelves }}</p>
                        </div>
                        <div class="mt-3">
                            <span class="text-xs text-gray-500">Lista de Prateleiras</span>
                            <div class="mt-1 space-y-2">
                                <div v-for="shelf in selectedSection.shelves" :key="shelf.id" class="rounded-md bg-gray-50 px-3 py-2 text-xs">
                                    <div class="flex justify-between">
                                        <span class="font-medium">ID: {{ shelf.id.substring(0, 8) }}...</span>
                                        <span>Pos: {{ shelf.shelf_position }}cm</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modo de edição -->
            <form v-else @submit.prevent="saveChanges" class="space-y-4">
                <div>
                    <h3 class="text-sm font-medium text-gray-500">Informações Básicas</h3>
                    <div class="mt-2 space-y-3">
                        <div class="space-y-1">
                            <Label for="name">Nome</Label>
                            <Input id="name" v-model="formData.name" />
                        </div>
                    </div>
                </div>

                <Separator />

                <div>
                    <h3 class="text-sm font-medium text-gray-500">Dimensões</h3>
                    <div class="mt-2 grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <Label for="width">Largura (cm)</Label>
                            <Input id="width" v-model.number="formData.width" type="number" />
                        </div>
                        <div class="space-y-1">
                            <Label for="height">Altura (cm)</Label>
                            <Input id="height" v-model.number="formData.height" type="number" />
                        </div>
                        <div class="space-y-1">
                            <Label for="base_width">Base (L) (cm)</Label>
                            <Input id="base_width" v-model.number="formData.base_width" type="number" />
                        </div>
                        <div class="space-y-1">
                            <Label for="base_height">Base (A) (cm)</Label>
                            <Input id="base_height" v-model.number="formData.base_height" type="number" />
                        </div>
                        <div class="space-y-1">
                            <Label for="base_depth">Base (P) (cm)</Label>
                            <Input id="base_depth" v-model.number="formData.base_depth" type="number" />
                        </div>
                    </div>
                </div>

                <Separator />

                <div>
                    <h3 class="text-sm font-medium text-gray-500">Cremalheira</h3>
                    <div class="mt-2 grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <Label for="cremalheira_width">Largura (cm)</Label>
                            <Input id="cremalheira_width" v-model.number="formData.cremalheira_width" type="number" />
                        </div>
                        <div class="space-y-1">
                            <Label for="hole_width">Largura Buraco (cm)</Label>
                            <Input id="hole_width" v-model.number="formData.hole_width" type="number" />
                        </div>
                        <div class="space-y-1">
                            <Label for="hole_height">Altura Buraco (cm)</Label>
                            <Input id="hole_height" v-model.number="formData.hole_height" type="number" />
                        </div>
                        <div class="space-y-1">
                            <Label for="hole_spacing">Espaçamento (cm)</Label>
                            <Input id="hole_spacing" v-model.number="formData.hole_spacing" type="number" />
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end space-x-2 pt-4">
                    <Button @click="cancelEditing" variant="outline">Cancelar</Button>
                    <Button type="submit">Salvar Alterações</Button>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useEditorStore } from '../../../store/editor';

import { PencilIcon } from 'lucide-vue-next';
import { Section } from '../../../types/sections';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';

    const editorStore = useEditorStore();

const selectedSection = computed(() => editorStore.getSelectedSection as Section);
const isEditing = computed(() => editorStore.isSectionSelected());

// Inicializa o formulário com valores padrão
const formData = ref<Partial<Section>>({
    name: '',
    width: 0,
    height: 0,
    base_width: 0,
    base_height: 0,
    base_depth: 0,
    cremalheira_width: 0,
    hole_width: 0,
    hole_height: 0,
    hole_spacing: 0,
});

// Atualiza o formulário quando a seção selecionada muda
watch(
    selectedSection,
    (newSection: Section) => {
        if (newSection) {
            formData.value = {
                name: newSection.name,
                width: newSection.width,
                height: newSection.height,
                base_width: newSection.base_width,
                base_height: newSection.base_height,
                base_depth: newSection.base_depth,
                cremalheira_width: newSection.cremalheira_width,
                hole_width: newSection.hole_width,
                hole_height: newSection.hole_height,
                hole_spacing: newSection.hole_spacing,
            };
        }
    },
    { immediate: true },
);

// Métodos para gerenciar a edição
const startEditing = () => {
    editorStore.setIsSectionEditing(true);
};

const cancelEditing = () => {
    editorStore.setIsSectionEditing(false);

    // Reset do formulário para os valores originais
    if (selectedSection.value) {
        formData.value = {
            name: selectedSection.value.name,
            width: selectedSection.value.width,
            height: selectedSection.value.height,
            base_width: selectedSection.value.base_width,
            base_height: selectedSection.value.base_height,
            base_depth: selectedSection.value.base_depth,
            cremalheira_width: selectedSection.value.cremalheira_width,
            hole_width: selectedSection.value.hole_width,
            hole_height: selectedSection.value.hole_height,
            hole_spacing: selectedSection.value.hole_spacing,
        };
    }
};

const saveChanges = async () => {
    if (!selectedSection.value) return;

    // ---> Lógica para encontrar o GondolaId correto <---
    const sectionId = selectedSection.value.id;
    let correctGondolaId: string | null = null;

    if (!editorStore.currentState || !editorStore.currentState.gondolas) {
        console.error('Erro ao salvar: Estado do editor ou gôndolas não encontrados.');
        // Adicionar feedback para o usuário
        return;
    }

    for (const gondola of editorStore.currentState.gondolas) {
        if (gondola.sections.some(section => section.id === sectionId)) {
            correctGondolaId = gondola.id;
            break; // Encontrou a gôndola correta
        }
    }
    // ---> Fim da lógica <---

    // Obter o ID da gôndola ativa do editorStore
    // const gondolaId = editorStore.currentGondolaId; // <-- Remover uso do getter genérico

    if (!correctGondolaId) { // Usa a variável encontrada
        console.error(`Erro ao salvar: Gôndola contendo a seção ${sectionId} não encontrada no estado do editor.`);
        // Poderia mostrar um toast/notificação aqui
        return;
    }

    try {
        // Chama a action no editorStore para atualizar os dados da seção com o ID correto da gôndola
        editorStore.updateSectionData(correctGondolaId, sectionId, formData.value);
        console.log('Alterações da seção enviadas para o editorStore.');
        // sectionStore.updateSection(selectedSection.value.id, formData.value); // <-- Remover ou comentar a chamada antiga
        editorStore.setIsSectionEditing(false); // Finaliza o modo de edição no sectionStore local
    } catch (error) {
        console.error('Erro ao salvar as alterações da seção:', error);
        // Adicionar tratamento de erro, como exibir uma notificação para o usuário
    }
};
</script>
