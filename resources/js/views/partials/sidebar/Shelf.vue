<template>
    <div class="shelves h-full w-full overflow-y-auto border-l border-gray-200 bg-white p-6 dark:bg-gray-700 dark:border-gray-600">
        <TooltipProvider>
            <div class="space-y-6" v-if="selectedShelf">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-medium text-gray-800 dark:text-gray-200">Propriedades da Prateleira</h2>
                    <!-- Botão Editar (Modo Visualização) -->
                    <Button v-if="!isEditing" @click="startEditing" variant="outline" size="icon">
                        <PencilIcon class="h-4 w-4" />
                    </Button>
                    <!-- Botão Cancelar (Modo Edição) -->
                    <Button v-if="isEditing" @click="cancelEditing" variant="outline" size="icon">
                        <XIcon class="h-4 w-4" />
                    </Button>
                </div>
                <!-- Modo de Visualização -->
                <div v-if="!isEditing" class="space-y-4">
                    <div>
                        <h3 class="text-sm font-medium text-gray-500 pt-4">Informações Básicas</h3>
                        <div class="mt-2 space-y-3">
                            <div class="space-y-1">
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Label class="truncate block cursor-help text-xs text-gray-500 dark:text-gray-400">Código</Label>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Código interno ou identificador da prateleira</p>
                                    </TooltipContent>
                                </Tooltip>
                                <p class="text-sm text-gray-800 dark:text-gray-200">{{ selectedShelf.code || '-' }}</p>
                            </div>
                            <div class="space-y-1">
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Label class="truncate block cursor-help text-xs text-gray-500 dark:text-gray-400">Tipo de
                                            Produto</Label>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Classificação do tipo de produto da prateleira</p>
                                    </TooltipContent>
                                </Tooltip>
                                <p class="text-sm capitalize text-gray-800 dark:text-gray-200">{{ selectedShelf.product_type || '-' }}</p>
                            </div>
                            <div class="space-y-1">
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Label class="truncate block cursor-help text-xs text-gray-500 dark:text-gray-400">Status</Label>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Status atual da prateleira (Publicado/Rascunho)</p>
                                    </TooltipContent>
                                </Tooltip>
                                <p class="text-sm capitalize text-gray-800 dark:text-gray-200">{{ selectedShelf.status || '-' }}</p>
                            </div>
                        </div>
                    </div>

                    <Separator />

                    <div>
                        <h3 class="text-sm font-medium text-gray-500">Dimensões</h3>
                        <div class="mt-2 grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Label class="truncate block cursor-help text-xs text-gray-500 dark:text-gray-400">Altura
                                            (cm)</Label>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Altura da prateleira em centímetros</p>
                                    </TooltipContent>
                                </Tooltip>
                                <p class="text-sm text-gray-800 dark:text-gray-200">{{ selectedShelf.shelf_height }}cm</p>
                            </div>
                            <div class="space-y-1">
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Label class="truncate block cursor-help text-xs text-gray-500 dark:text-gray-400">Largura
                                            (cm)</Label>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Largura da prateleira em centímetros</p>
                                    </TooltipContent>
                                </Tooltip>
                                <p class="text-sm text-gray-800 dark:text-gray-200">{{ selectedShelf.shelf_width }}cm</p>
                            </div>
                            <div class="space-y-1">
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Label class="truncate block cursor-help text-xs text-gray-500 dark:text-gray-400">Profundidade
                                            (cm)</Label>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Profundidade da prateleira em centímetros</p>
                                    </TooltipContent>
                                </Tooltip>
                                <p class="text-sm text-gray-800 dark:text-gray-200">{{ selectedShelf.shelf_depth }}cm</p>
                            </div>
                            <div class="space-y-1">
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Label class="truncate block cursor-help text-xs text-gray-500 dark:text-gray-400">Posição Vertical
                                            (cm)</Label>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Posição vertical da prateleira na seção (a partir da base)</p>
                                    </TooltipContent>
                                </Tooltip>
                                <p class="text-sm text-gray-800 dark:text-gray-200">{{ selectedShelf.shelf_position }}cm</p>
                            </div>
                        </div>
                    </div>

                    <Separator />

                    <div>
                        <h3 class="text-sm font-medium text-gray-500">Ordenação</h3>
                        <div class="mt-2 space-y-3">
                            <div class="space-y-1">
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Label class="truncate block cursor-help text-xs text-gray-500 dark:text-gray-400">Ordem na
                                            Seção</Label>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Ordem numérica desta prateleira dentro da seção (não afeta posição visual)
                                        </p>
                                    </TooltipContent>
                                </Tooltip>
                                <p class="text-sm text-gray-800 dark:text-gray-200">{{ selectedShelf.ordering }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modo de Edição -->
                <form v-else @submit.prevent="saveChanges" class="space-y-4">
                    <div>
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 pt-4">Informações Básicas</h3>
                        <div class="mt-2 space-y-3">
                            <div class="space-y-1">
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Label for="code" class="truncate block cursor-help text-gray-500 dark:text-gray-400">Código</Label>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Código interno ou identificador da prateleira</p>
                                    </TooltipContent>
                                </Tooltip>
                                <Input id="code" v-model="formData.code" class="h-8" />
                            </div>
                            <div class="space-y-1">
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Label for="product_type" class="truncate block cursor-help text-gray-500 dark:text-gray-400">Tipo de
                                            Produto</Label>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Classificação do tipo de produto da prateleira</p>
                                    </TooltipContent>
                                </Tooltip>
                                <Select v-model="formData.product_type">
                                    <SelectTrigger class="h-8">
                                        <SelectValue placeholder="Selecionar" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="normal" class="dark:text-gray-200 dark:hover:bg-gray-600">
                                            Normal
                                        </SelectItem>
                                        <SelectItem value="hook" class="dark:text-gray-200 dark:hover:bg-gray-600">
                                            Gancheira
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div class="space-y-1">
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Label for="status" class="truncate block cursor-help text-gray-500 dark:text-gray-400">Status</Label>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Status atual da prateleira (Publicado/Rascunho)</p>
                                    </TooltipContent>
                                </Tooltip>
                                <Select v-model="formData.status">
                                    <SelectTrigger class="h-8">
                                        <SelectValue placeholder="Selecionar" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="published" class="dark:text-gray-200 dark:hover:bg-gray-600">Publicado</SelectItem>
                                        <SelectItem value="draft" class="dark:text-gray-200 dark:hover:bg-gray-600">Rascunho</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                    </div>

                    <Separator />

                    <div>
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Dimensões</h3>
                        <div class="mt-2 grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Label for="shelf_height" class="truncate block cursor-help text-gray-500 dark:text-gray-400">Altura (cm)</Label>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Altura da prateleira em centímetros</p>
                                    </TooltipContent>
                                </Tooltip>
                                <Input id="shelf_height" v-model.number="formData.shelf_height" type="number" min="0"
                                    class="h-8" />
                            </div>
                            <div class="space-y-1">
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Label for="shelf_width" class="truncate block cursor-help text-gray-500 dark:text-gray-400">Largura (cm)</Label>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Largura da prateleira em centímetros</p>
                                    </TooltipContent>
                                </Tooltip>
                                <Input id="shelf_width" v-model.number="formData.shelf_width" type="text" readonly
                                    class="h-8" />
                            </div>
                            <div class="space-y-1">
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Label for="shelf_depth" class="truncate block cursor-help text-gray-500 dark:text-gray-400">Profundidade
                                            (cm)</Label>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Profundidade da prateleira em centímetros</p>
                                    </TooltipContent>
                                </Tooltip>
                                <Input id="shelf_depth" v-model.number="formData.shelf_depth" type="number" min="0"
                                    class="h-8" />
                            </div>
                            <div class="space-y-1">
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Label for="shelf_position" class="truncate block cursor-help text-gray-500 dark:text-gray-400">Posição Vertical
                                            (cm)</Label>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Posição vertical da prateleira na seção (a partir da base)</p>
                                    </TooltipContent>
                                </Tooltip>
                                <Input id="shelf_position" v-model.number="formData.shelf_position" type="number"
                                    min="0" class="h-8" />
                            </div>
                        </div>
                    </div>

                    <Separator />

                    <div>
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Ordenação</h3>
                        <div class="mt-2 space-y-3">
                            <div class="space-y-1">
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Label for="ordering" class="truncate block cursor-help text-gray-500 dark:text-gray-400">Ordem na Seção</Label>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Ordem numérica desta prateleira dentro da seção (não afeta posição visual)
                                        </p>
                                    </TooltipContent>
                                </Tooltip>
                                <Input id="ordering" v-model.number="formData.ordering" type="number" min="0"
                                    class="h-8" />
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end pt-4 w-full">
                        <Button type="submit" variant="default" size="sm" class="w-full">
                            <CheckIcon class="mr-2 h-4 w-4" />
                            <span>Salvar Alterações</span>
                        </Button>
                    </div>
                </form>
            </div>
            <div v-else class="text-center text-gray-500 dark:text-gray-400">
                <p>Selecione uma prateleira para ver suas propriedades.</p>
            </div>
        </TooltipProvider>
    </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';

import { useEditorStore } from '@plannerate/store/editor';
import type { Shelf } from '@plannerate/types/shelves';
import { XIcon, PencilIcon, CheckIcon } from 'lucide-vue-next';


const editorStore = useEditorStore();

const selectedShelf = computed(() => editorStore.getSelectedShelf as Shelf);
const isEditing = computed(() => editorStore.isShelfEditing);

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

const startEditing = () => {
    if (selectedShelf.value) {
        formData.value = { ...selectedShelf.value };
    }
    editorStore.setIsShelfEditing(true);
};

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
