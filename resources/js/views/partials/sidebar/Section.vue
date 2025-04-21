<template>
    <div id="section-properties-sidebar" class="h-full w-full overflow-y-auto border-l border-gray-200 bg-white p-6">
        <TooltipProvider>
            <div class="space-y-6" v-if="selectedSection">
                <div class="flex items-center justify-between w-full ">
                    <h2 class="text-lg font-medium flex-1">Propriedades da Seção</h2>
                    <div class="flex items-center space-x-2 justify-between">                        
                         <Button  type="button"
                         v-if="isEditing"
                          @click="cancelEditing" variant="outline" size="icon">
                        <ArrowLeftIcon class="h-4 w-4" /> 
                        </Button> 

                            <Button  type="button" v-if="!isEditing" @click="startEditing" variant="outline" size="icon">
                            <PencilIcon class="h-4 w-4" /> 
                        </Button>
                    </div>
                </div>

                <!-- Modo de visualização -->
                <div v-if="!isEditing" class="space-y-4">
                    <div>
                        <h3 class="text-sm font-medium text-gray-500">Informações Básicas</h3>
                        <div class="mt-2 space-y-3">
                            <div>
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Label for="name" class="truncate block cursor-help">Nome</Label>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Nome da Seção</p>
                                    </TooltipContent>
                                </Tooltip>
                                <p class="text-sm">{{ selectedSection.name }}</p>
                            </div>
                            <div>
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Label for="id" class="truncate block cursor-help">ID</Label>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Identificador único da seção</p>
                                    </TooltipContent>
                                </Tooltip>
                                <p class="font-mono text-sm">{{ selectedSection.id }}</p>
                            </div>
                            <div>
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Label for="status" class="truncate block cursor-help">Status</Label>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Estado atual da seção</p>
                                    </TooltipContent>
                                </Tooltip>
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
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Label for="width" class="truncate block cursor-help">Largura (cm)</Label>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Largura total da seção em centímetros</p>
                                    </TooltipContent>
                                </Tooltip>
                                <p class="text-sm">{{ selectedSection.width }}cm</p>
                            </div>
                            <div>
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Label for="height" class="truncate block cursor-help">Altura (cm)</Label>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Altura total da seção em centímetros</p>
                                    </TooltipContent>
                                </Tooltip>
                                <p class="text-sm">{{ selectedSection.height }}cm</p>
                            </div>
                            <div>
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Label for="base_width" class="truncate block cursor-help">Base (L) (cm)</Label>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Largura da base da seção em centímetros</p>
                                    </TooltipContent>
                                </Tooltip>
                                <p class="text-sm">{{ selectedSection.base_width }}cm</p>
                            </div>
                            <div>
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Label for="base_height" class="truncate block cursor-help">Base (A) (cm)</Label>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Altura da base da seção em centímetros</p>
                                    </TooltipContent>
                                </Tooltip>
                                <p class="text-sm">{{ selectedSection.base_height }}cm</p>
                            </div>
                            <div>
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Label for="base_depth" class="truncate block cursor-help">Base (P) (cm)</Label>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Profundidade da base da seção em centímetros</p>
                                    </TooltipContent>
                                </Tooltip>
                                <p class="text-sm">{{ selectedSection.base_depth }}cm</p>
                            </div>
                        </div>
                    </div>

                    <Separator />

                    <div>
                        <h3 class="text-sm font-medium text-gray-500">Cremalheira</h3>
                        <div class="mt-2 grid grid-cols-2 gap-3">
                            <div>
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Label for="cremalheira_width" class="truncate block cursor-help">Largura Cremalheira (cm)</Label>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Largura da cremalheira em centímetros</p>
                                    </TooltipContent>
                                </Tooltip>
                                <p class="text-sm">{{ selectedSection.cremalheira_width }}cm</p>
                            </div>
                            <div>
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Label for="holes" class="truncate block cursor-help">Buracos</Label>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Quantidade de buracos na cremalheira</p>
                                    </TooltipContent>
                                </Tooltip>
                                <p class="text-sm">{{ selectedSection.settings.holes.length }}</p>
                            </div>
                            <div>
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Label for="hole_width" class="truncate block cursor-help">Largura Buraco (cm)</Label>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Largura de cada buraco da cremalheira em centímetros</p>
                                    </TooltipContent>
                                </Tooltip>
                                <p class="text-sm">{{ selectedSection.hole_width }}x{{ selectedSection.hole_height }}cm</p>
                            </div>
                            <div>
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Label for="hole_spacing" class="truncate block cursor-help">Espaçamento Buracos (cm)</Label>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Espaçamento vertical entre os buracos da cremalheira em centímetros</p>
                                    </TooltipContent>
                                </Tooltip>
                                <p class="text-sm">{{ selectedSection.hole_spacing }}cm</p>
                            </div>
                        </div>
                    </div>

                    <Separator />

                    <div>
                        <h3 class="text-sm font-medium text-gray-500">Prateleiras</h3>
                        <div class="mt-2">
                            <div>
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Label for="num_shelves" class="truncate block cursor-help">Quantidade</Label>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Quantidade total de prateleiras na seção</p>
                                    </TooltipContent>
                                </Tooltip>
                                <p class="text-sm">{{ selectedSection.num_shelves }}</p>
                            </div>
                            <div class="mt-3">
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Label for="shelves" class="truncate block cursor-help">Lista de Prateleiras</Label>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Detalhes de cada prateleira na seção</p>
                                    </TooltipContent>
                                </Tooltip>
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
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Label for="name" class="truncate block cursor-help">Nome</Label>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Nome da Seção</p>
                                    </TooltipContent>
                                </Tooltip>
                                <Input id="name" v-model="formData.name" class="h-8" />
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
                                        <Label for="width" class="truncate block cursor-help">Largura (cm)</Label>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Largura total da seção em centímetros</p>
                                    </TooltipContent>
                                </Tooltip>
                                <Input id="width" v-model.number="formData.width" type="number" class="h-8" />
                            </div>
                            <div class="space-y-1">
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Label for="height" class="truncate block cursor-help">Altura (cm)</Label>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Altura total da seção em centímetros</p>
                                    </TooltipContent>
                                </Tooltip>
                                <Input id="height" v-model.number="formData.height" type="number" class="h-8" />
                            </div>
                            <div class="space-y-1">
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Label for="base_width" class="truncate block cursor-help">Base (L) (cm)</Label>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Largura da base da seção em centímetros</p>
                                    </TooltipContent>
                                </Tooltip>
                                <Input id="base_width" v-model.number="formData.base_width" type="number" class="h-8" />
                            </div>
                            <div class="space-y-1">
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Label for="base_height" class="truncate block cursor-help">Base (A) (cm)</Label>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Altura da base da seção em centímetros</p>
                                    </TooltipContent>
                                </Tooltip>
                                <Input id="base_height" v-model.number="formData.base_height" type="number" class="h-8" />
                            </div>
                            <div class="space-y-1">
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Label for="base_depth" class="truncate block cursor-help">Base (P) (cm)</Label>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Profundidade da base da seção em centímetros</p>
                                    </TooltipContent>
                                </Tooltip>
                                <Input id="base_depth" v-model.number="formData.base_depth" type="number" class="h-8" />
                            </div>
                        </div>
                    </div>

                    <Separator />

                    <div>
                        <h3 class="text-sm font-medium text-gray-500">Cremalheira</h3>
                        <div class="mt-2 grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Label for="cremalheira_width" class="truncate block cursor-help">Largura Cremalheira (cm)</Label>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Largura da cremalheira em centímetros</p>
                                    </TooltipContent>
                                </Tooltip>
                                <Input id="cremalheira_width" v-model.number="formData.cremalheira_width" type="number" class="h-8" />
                            </div>
                            <div class="space-y-1">
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Label for="hole_width" class="truncate block cursor-help">Largura Buraco (cm)</Label>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Largura de cada buraco da cremalheira em centímetros</p>
                                    </TooltipContent>
                                </Tooltip>
                                <Input id="hole_width" v-model.number="formData.hole_width" type="number" class="h-8" />
                            </div>
                            <div class="space-y-1">
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Label for="hole_height" class="truncate block cursor-help">Altura Buraco (cm)</Label>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Altura de cada buraco da cremalheira em centímetros</p>
                                    </TooltipContent>
                                </Tooltip>
                                <Input id="hole_height" v-model.number="formData.hole_height" type="number" class="h-8" />
                            </div>
                            <div class="space-y-1">
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Label for="hole_spacing" class="truncate block cursor-help">Espaçamento Buracos (cm)</Label>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Espaçamento vertical entre os buracos da cremalheira em centímetros</p>
                                    </TooltipContent>
                                </Tooltip>
                                <Input id="hole_spacing" v-model.number="formData.hole_spacing" type="number" class="h-8" />
                            </div>
                        </div>
                    </div>
                    <div v-if="selectedSection.shelves.length > 1">
                        <Button type="button"  @click="invertShelves" variant="outline" size="sm" :disabled="!canInvertShelves" class="w-full">
                             <ArrowUpDownIcon class="mr-2 h-4 w-4" />
                             Inverter
                         </Button>
                    </div>  

                    <div class="flex items-center justify-between pt-4 gap-2">
                        
                        <Button type="button" @click="cancelEditing" variant="outline" size="sm">
                            <XIcon class="mr-2 h-4 w-4" />
                            Cancelar
                        </Button>
                        <Button type="submit" size="sm">
                            <CheckIcon class="mr-2 h-4 w-4" />
                            Salvar Alterações
                        </Button>
                    </div>
                </form>
            </div>
        </TooltipProvider>
    </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useEditorStore } from '../../../store/editor';

import { PencilIcon, ArrowUpDownIcon, XIcon, ArrowLeftIcon, CheckIcon } from 'lucide-vue-next';
import { Section } from '@plannerate/types/sections'; 
import { useToast } from '@/components/ui/toast'; 

const editorStore = useEditorStore();
const { toast } = useToast();

const selectedSection = computed(() => editorStore.getSelectedSection as Section);
const editorGondola = computed(() => editorStore.getCurrentGondola);
const isEditing = computed(() => editorStore.isSectionEditing);

// Calcula se é possível inverter as prateleiras
const canInvertShelves = computed(() => {
    return selectedSection.value && selectedSection.value.shelves && selectedSection.value.shelves.length >= 2;
});

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
    alignment: 'inherit',
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
                alignment: newSection.alignment ?? 'inherit',
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
            alignment: selectedSection.value.alignment ?? 'inherit',
        };
    }
};

const invertShelves = () => {
    if (!selectedSection.value || !editorGondola.value?.id) {
        console.error('Erro ao inverter: Seção ou Gôndola não selecionada.');
        toast({
            title: 'Erro',
            description: 'Seção ou Gôndola não encontrada para inverter prateleiras.',
            variant: 'destructive',
        });
        return;
    }
    if (!canInvertShelves.value) {
        toast({
            title: 'Aviso',
            description: 'Não é possível inverter: A seção precisa ter pelo menos duas prateleiras.',
            variant: 'default',
        });
        return;
    }

    const gondolaId = editorGondola.value.id;
    const sectionId = selectedSection.value.id;

    try {
        editorStore.invertShelvesInSection(gondolaId, sectionId);
        toast({
            title: 'Sucesso',
            description: 'Ordem das prateleiras invertida.',
        });
    } catch (error) { 
        toast({
            title: 'Erro',
            description: 'Falha ao inverter a ordem das prateleiras.',
            variant: 'destructive',
        });
    }
};

const saveChanges = async () => {
    if (!selectedSection.value) return;

    const sectionId = selectedSection.value.id;
    let correctGondolaId: string | null = editorGondola.value?.id || null;

    if (!correctGondolaId) {
        console.error(`Erro ao salvar: Gôndola contendo a seção ${sectionId} não encontrada no estado do editor.`);
        return;
    }

    // Prepara os dados a serem enviados, tratando o valor 'inherit'
    const dataToSave: Partial<Section> = { ...formData.value };
    if (dataToSave.alignment === 'inherit') {
        dataToSave.alignment = undefined; // ou null, dependendo de como você quer representar "sem alinhamento"
    }

    try {
        editorStore.updateSectionData(correctGondolaId, sectionId, dataToSave); // Usa dataToSave
        console.log('Alterações da seção enviadas para o editorStore.');
        editorStore.setIsSectionEditing(false); 
    } catch (error) {
        console.error('Erro ao salvar as alterações da seção:', error);
    }
};
</script>
