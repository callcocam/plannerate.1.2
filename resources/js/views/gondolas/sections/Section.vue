<template>
    <ContextMenu>
        <ContextMenuTrigger>
            <div
                class="bg-gray-800"
                :style="sectionStyle"
                :data-section-id="section.id"
                @dragover.prevent="handleSectionDragOver"
                @drop.prevent="handleSectionDrop"
                @dragleave="handleSectionDragLeave"
                ref="sectionRef"
            >
                <!-- Conteúdo da Seção (Prateleiras) -->
                <ShelfComponent
                    v-for="shelf in section.shelves"
                    :key="shelf.id"
                    :shelf="shelf"
                    :gondola-id="props.gondolaId"
                    :scale-factor="scaleFactor"
                    :section-width="props.section.width"
                    :section-height="props.section.height"
                    :base-height="baseHeight"
                    :sections-container="sectionsContainer"
                    :section-index="sectionIndex"
                    @drop-product="handleProductDropOnShelf"
                    @drop-layer-copy="handleLayerCopy"
                    @drag-shelf="handleShelfDragStart"
                />
            </div>
        </ContextMenuTrigger>
        <ContextMenuContent class="w-64">
            <ContextMenuRadioGroup model-value="modulos">
                <ContextMenuLabel inset> Modulos </ContextMenuLabel>
                <ContextMenuSeparator />
                <ContextMenuItem inset @click="editSection()">
                    Editar
                    <ContextMenuShortcut>⌘E</ContextMenuShortcut>
                </ContextMenuItem>
                <ContextMenuItem inset @click="(e: MouseEvent) => addShelf(e)">
                    Adicionar prateleira
                    <ContextMenuShortcut>⌘A</ContextMenuShortcut>
                </ContextMenuItem>
                <ContextMenuItem inset @click="inverterModule()">
                    Inverter ordem
                    <ContextMenuShortcut>⌘I</ContextMenuShortcut>
                </ContextMenuItem>
                <ContextMenuSeparator />
                <ContextMenuSub>
                    <ContextMenuSubTrigger inset> Alinhamento </ContextMenuSubTrigger>
                    <ContextMenuSubContent class="w-48">
                        <ContextMenuItem inset @click="justifyModule('left')">
                            à esquerda
                            <ContextMenuShortcut>⌘⇧L</ContextMenuShortcut>
                        </ContextMenuItem>
                        <ContextMenuItem inset @click="justifyModule('justify')">
                            ao centro
                            <ContextMenuShortcut>⌘⇧C</ContextMenuShortcut>
                        </ContextMenuItem>
                        <ContextMenuItem inset @click="justifyModule('right')">
                            à direita
                            <ContextMenuShortcut>⌘⇧R</ContextMenuShortcut>
                        </ContextMenuItem>
                    </ContextMenuSubContent>
                </ContextMenuSub>
                <ContextMenuSeparator />
                <ContextMenuItem inset disabled>
                    Excluir
                    <ContextMenuShortcut>⌘D</ContextMenuShortcut>
                </ContextMenuItem>
            </ContextMenuRadioGroup>
        </ContextMenuContent>
    </ContextMenu>
</template>

<script setup lang="ts">
import { computed, defineEmits, defineProps, onMounted, onUnmounted, ref } from 'vue'; 
import { useProductStore } from '@plannerate/store/product';
import { useSectionStore } from '@plannerate/store/section';
import { useShelvesStore } from '@plannerate/store/shelves';
import { useEditorStore } from '@plannerate/store/editor';
import { type Shelf as ShelfType } from '@plannerate/types/shelves';
import { Section } from '@plannerate/types/sections';
import { Layer, Product, Segment } from '@plannerate/types/segment';
import ShelfComponent from './Shelf.vue';
import { useToast } from '@plannerate/components/ui/toast';

// Definir Props
const props = defineProps<{
    gondolaId: string | undefined;
    section: Section;
    scaleFactor: number;
    selectedCategory: any;
    sectionsContainer: HTMLElement | null;
    sectionIndex: number;
}>();

// Definir Emits
const emit = defineEmits(['update:segments']);

// Stores 
const productStore = useProductStore();
const shelvesStore = useShelvesStore();
const sectionStore = useSectionStore();
const editorStore = useEditorStore();

// Services
const { toast } = useToast(); 

// --- Estado para controle de drag and drop ---
const dropTargetActive = ref(false);
const draggingShelf = ref<ShelfType | null>(null);
const sectionRef = ref<HTMLElement | null>(null);

// --- Computeds para Estilos ---
const baseHeight = computed(() => {
    const baseHeightCm = props.section.base_height || 0;
    if (baseHeightCm <= 0) return 0;
    return baseHeightCm * props.scaleFactor;
});

const sectionStyle = computed(() => {
    return {
        width: `${props.section.width * props.scaleFactor}px`,
        height: `${props.section.height * props.scaleFactor}px`,
        position: 'relative' as const,
        borderWidth: '2px',
        borderStyle: dropTargetActive.value ? 'dashed' : 'solid',
        borderColor: dropTargetActive.value ? 'rgba(59, 130, 246, 0.5)' : 'transparent',
        backgroundColor: dropTargetActive.value ? 'rgba(59, 130, 246, 0.1)' : 'transparent',
        overflow: 'visible' as const,
        transition: 'border-color 0.2s ease-in-out, background-color 0.2s ease-in-out',
    };
});

const editSection = () => {
    // Emitir evento para abrir o modal de edição da seção
    sectionStore.setSelectedSection(props.section);
    sectionStore.startEditing();
};

const addShelf = async (event: MouseEvent) => {
    shelvesStore.handleDoubleClick({
        shelf_position: event.offsetY / props.scaleFactor,
        section_id: props.section.id,
    });
    event.stopPropagation();
};

const justifyModule = (alignment: string) => {
    if (!props.gondolaId) {
        console.warn('Não é possível justificar módulo: gondolaId não fornecido.');
        return;
    }
    editorStore.setSectionAlignment(props.gondolaId, props.section.id, alignment);
};

const inverterModule = () => {
    if (!props.gondolaId) {
        console.warn('Não é possível inverter prateleiras: gondolaId não fornecido.');
        return;
    }
    editorStore.invertShelvesInSection(props.gondolaId, props.section.id);
};

// --- Helpers ---
const createSegmentFromProduct = (product: Product, shelf: ShelfType, layerQuantity: number): Segment => {
    const segmentId = `segment-${Date.now()}-${shelf.segments?.length}`;
    const layerId = `layer-${Date.now()}-${product.id}`;
    
    return { 
        id: segmentId, 
        user_id: null,
        tenant_id: '',
        width: parseInt(props.section.width.toString()),
        ordering: (shelf.segments?.length || 0) + 1,
        quantity: 1,
        shelf_id: shelf.id,
        spacing: 0,
        position: 0,
        alignment: '',
        settings: null,
        status: 'published',
        layer: {
            id: layerId,
            product_id: product.id,
            product: product,
            quantity: layerQuantity || 1,
            status: 'published',
            height: product.height,
            segment_id: segmentId,
        }
    };
};

// --- Lógica de Drag and Drop das Prateleiras ---
const handleShelfDragStart = (shelf: ShelfType) => {
    draggingShelf.value = shelf;
    console.log('Iniciando arrasto da prateleira:', shelf.id);
};

const handleSectionDragOver = (event: DragEvent) => {
    if (!event.dataTransfer) return;
    const isShelf = event.dataTransfer.types.includes('text/shelf');

    if (isShelf) {
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
        dropTargetActive.value = true;
    }
};

const handleSectionDragLeave = () => {
    dropTargetActive.value = false;
};

const handleSectionDrop = async (event: DragEvent) => {
    if (!event.dataTransfer) return;
    const shelfData = event.dataTransfer.getData('text/shelf');

    if (shelfData) {
        try {
            const shelf = JSON.parse(shelfData) as ShelfType;
            const mouseY = event.offsetY;
            const newPosition = mouseY / props.scaleFactor;
            const shelfHeight = draggingShelf.value?.shelf_height || 0;

            if (newPosition >= 0 && newPosition <= props.section.height - shelfHeight) {
                if (!props.gondolaId) {
                    console.warn('handleSectionDrop: gondolaId não fornecido.');
                    toast({ title: 'Erro Interno', description: 'Contexto da gôndola não encontrado.', variant: 'destructive' });
                    draggingShelf.value = null;
                    dropTargetActive.value = false;
                    return;
                }
                
                editorStore.setShelfPosition(props.gondolaId, props.section.id, shelf.id, newPosition);

            } else {
                toast({
                    title: 'Aviso',
                    description: 'Posição de prateleira inválida',
                    variant: 'default',
                });
            }
        } catch (e) {
            console.error('Erro ao processar dados da prateleira no drop:', e);
             toast({ title: 'Erro', description: 'Falha ao mover prateleira.', variant: 'destructive' });
        }
        draggingShelf.value = null;
        dropTargetActive.value = false;
    }
};

// --- Lógica de Eventos para Produtos ---
const handleProductDropOnShelf = async (product: Product, shelf: ShelfType, dropPosition: any) => {
    // Cria o novo segmento com ID temporário
    const newSegment = createSegmentFromProduct(product, shelf, 1);

    // Verificar se gondolaId está disponível
    if (!props.gondolaId) {
        console.warn('handleProductDropOnShelf: gondolaId não fornecido.');
        toast({ title: 'Erro Interno', description: 'Contexto da gôndola não encontrado.', variant: 'destructive' });
        return;
    }

    try {
        // Chama a action do editorStore para adicionar o segmento ao estado
        editorStore.addSegmentToShelf(props.gondolaId, props.section.id, shelf.id, newSegment); 

    } catch (error) { // Captura erros da action do editorStore?
        console.error('Erro ao adicionar produto/segmento ao editorStore:', error);
        const errorDesc = (error instanceof Error) ? error.message : 'Falha ao atualizar o estado do editor.';
        toast({ title: 'Erro Interno', description: errorDesc, variant: 'destructive' });
    }
};

const handleLayerCopy = async (layer: Layer, shelf: ShelfType, dropPosition: any) => {
    // Cria o novo segmento baseado na layer copiada
    const newSegment = createSegmentFromProduct(layer.product, shelf, layer.quantity);

    // Verificar se gondolaId está disponível
    if (!props.gondolaId) {
        console.warn('handleLayerCopy: gondolaId não fornecido.');
        toast({ title: 'Erro Interno', description: 'Contexto da gôndola não encontrado.', variant: 'destructive' });
        return;
    }

    try {
        // Chama a action do editorStore para adicionar o segmento copiado ao estado
        editorStore.addSegmentToShelf(props.gondolaId, props.section.id, shelf.id, newSegment); 

    } catch (error) { // Captura erros da action do editorStore?
        console.error('Erro ao copiar camada/segmento para o editorStore:', error);
        const errorDesc = (error instanceof Error) ? error.message : 'Falha ao atualizar o estado do editor.';
        toast({ title: 'Erro Interno', description: errorDesc, variant: 'destructive' });
    }
};

// --- Event Handlers for Global Listeners ---
const handleKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Escape') {
        productStore.clearSelection();
    }
};

const handleClickOutside = (event: MouseEvent) => {
    const clickedElement = event.target as HTMLElement;
    if (clickedElement.closest('.border-destructive')) return;
    if (clickedElement.dataset.state) return;
    if (clickedElement.closest('.no-remove-properties')) return;

    if (!clickedElement.closest('.layer')) {
        productStore.clearSelection();
    }

    if (!clickedElement.closest('.shelves')) {
        shelvesStore.clearSelection();
        shelvesStore.clearSelectedShelfIds();
    }

    if (!clickedElement.closest('.sections')) {
        sectionStore.setSelectedSection(null);
        sectionStore.clearSelectedSectionIds();
    }
};

const handleDoubleClick = async (event: MouseEvent) => {
    event.stopPropagation();
    shelvesStore.handleDoubleClick({
        shelf_position: event.offsetY / props.scaleFactor,
        section_id: props.section.id,
    });
};

// --- Lifecycle Hooks for Listeners ---
onMounted(() => {
    window.addEventListener('keydown', handleKeydown);
    document.addEventListener('click', handleClickOutside, true);
    if (sectionRef.value) {
        sectionRef.value.addEventListener('dblclick', handleDoubleClick);
    }
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown);
    document.removeEventListener('click', handleClickOutside, true);
    if (sectionRef.value) {
        sectionRef.value.removeEventListener('dblclick', handleDoubleClick);
    }
});
</script>

<style scoped>
.section-container > .absolute.bottom-0 {
    z-index: -1;
}

.section-drag-over {
    background-color: rgba(59, 130, 246, 0.05);
    border: 2px dashed rgba(59, 130, 246, 0.5);
}
</style>
