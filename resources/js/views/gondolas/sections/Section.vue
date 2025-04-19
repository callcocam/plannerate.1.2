<template>
    <ContextMenu>
        <ContextMenuTrigger>
            <div class="bg-gray-800" :style="sectionStyle" :data-section-id="section.id"
                @dragover.prevent="handleSectionDragOver" @drop.prevent="handleSectionDrop"
                @dragleave="handleSectionDragLeave" ref="sectionRef">
                <!-- Conteúdo da Seção (Prateleiras) -->
                <slot />
                <ShelfComponent v-for="(shelf, index) in sortedShelves" :key="shelf.id" :shelf="shelf"
                    :gondola="gondola" :sorted-shelves="sortedShelves" :index="index" :section="section"
                    :scale-factor="scaleFactor" :section-width="section.width" :section-height="section.height"
                    :base-height="baseHeight" :sections-container="sectionsContainer" :section-index="sectionIndex"
                    :holes="holes" @drop-product="handleProductDropOnShelf" @drop-segment-copy="handleSegmentCopy"
                    @drop-segment="updateSegment" @drag-shelf="handleShelfDragStart" />
            </div>
        </ContextMenuTrigger>
        <ContextMenuContent class="w-64">
            <ContextMenuRadioGroup model-value="modulos">
                <ContextMenuLabel inset> Modulos </ContextMenuLabel>
                <ContextMenuSeparator />
                <ContextMenuItem inset @click="editSection">
                    Editar
                    <ContextMenuShortcut>⌘E</ContextMenuShortcut>
                </ContextMenuItem>
                <ContextMenuItem inset @click="addShelf">
                    Adicionar prateleira
                    <ContextMenuShortcut>⌘A</ContextMenuShortcut>
                </ContextMenuItem>
                <ContextMenuItem inset @click="inverterModule">
                    Inverter ordem
                    <ContextMenuShortcut>⌘I</ContextMenuShortcut>
                </ContextMenuItem>
                <ContextMenuSeparator />
                <ContextMenuLabel inset> Alinhamento </ContextMenuLabel>
                <ContextMenuSeparator />
                <ContextMenuItem inset @click="() => justifyModule('justify')"
                    :disabled="section.alignment === 'justify'">
                    Justificado
                    <ContextMenuShortcut>⌘⇧J</ContextMenuShortcut>
                </ContextMenuItem>
                <ContextMenuItem inset @click="() => justifyModule('left')" :disabled="section.alignment === 'left'">
                    à esquerda
                    <ContextMenuShortcut>⌘⇧L</ContextMenuShortcut>
                </ContextMenuItem>
                <ContextMenuItem inset @click="() => justifyModule('center')"
                    :disabled="section.alignment === 'center'">
                    ao centro
                    <ContextMenuShortcut>⌘⇧C</ContextMenuShortcut>
                </ContextMenuItem>
                <ContextMenuItem inset @click="() => justifyModule('right')" :disabled="section.alignment === 'right'">
                    à direita
                    <ContextMenuShortcut>⌘⇧R</ContextMenuShortcut>
                </ContextMenuItem>
                <ContextMenuItem inset @click="() => justifyModule()"
                    :disabled="!section.alignment || section.alignment === 'justify'">
                    Não alinhar
                    <ContextMenuShortcut>⌘⇧N</ContextMenuShortcut>
                </ContextMenuItem>
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
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { useProductStore } from '@plannerate/store/product';
import { useSectionStore } from '@plannerate/store/section';
import { useShelvesStore } from '@plannerate/store/shelves';
import { useEditorStore } from '@plannerate/store/editor';
import { type Shelf as ShelfType } from '@plannerate/types/shelves';
import { Section } from '@plannerate/types/sections';
import { Layer, Product, Segment } from '@plannerate/types/segment';
import ShelfComponent from './Shelf.vue';
import { useToast } from '@/components/ui/toast';
import { Gondola } from '@plannerate/types/gondola';
import { validateShelfWidth } from '@plannerate/utils/validation';

// ------- PROPS & EMITS -------
const props = defineProps<{
    gondola: Gondola;
    section: Section;
    scaleFactor: number;
    sectionsContainer: HTMLElement | null;
    sectionIndex: number;
}>();

const emit = defineEmits(['update:segments']);

// ------- DESTRUCTURED PROPS FOR BETTER PERFORMANCE -------
// Previne acesso repetido às props nos computed
const { gondola, section } = props;

// ------- STORES & SERVICES -------
const productStore = useProductStore();
const shelvesStore = useShelvesStore();
const sectionStore = useSectionStore();
const editorStore = useEditorStore();
const { toast } = useToast();

const holes = computed(() => {
    if (!section.settings) return [];
    return section.settings.holes;
});

// Ordena as prateleiras por posição para garantir o cálculo correto
const sortedShelves = computed(() => {
    if (!props.section.shelves || props.section.shelves.length === 0) {
        return [];
    }
    return [...props.section.shelves].sort((a, b) => a.shelf_position - b.shelf_position);
});
// ------- REFS -------
const dropTargetActive = ref(false);
const draggingShelf = ref<ShelfType | null>(null);
const sectionRef = ref<HTMLElement | null>(null);

// ------- COMPUTED -------
const baseHeight = computed(() => {
    const baseHeightCm = section.base_height || 0;
    return baseHeightCm <= 0 ? 0 : baseHeightCm * props.scaleFactor;
});

// Estilo da seção com CSS transformado via computed para melhorar performance
const sectionStyle = computed(() => {
    const isActive = dropTargetActive.value;
    return {
        width: `${section.width * props.scaleFactor}px`,
        height: `${section.height * props.scaleFactor}px`,
        position: 'relative' as const,
        borderWidth: '2px',
        borderStyle: isActive ? 'dashed' : 'solid',
        borderColor: isActive ? 'rgba(59, 130, 246, 0.5)' : 'transparent',
        backgroundColor: isActive ? 'rgba(59, 130, 246, 0.1)' : 'transparent',
        overflow: 'visible' as const,
        transition: 'border-color 0.2s ease-in-out, background-color 0.2s ease-in-out',
        willChange: isActive ? 'border-color, background-color' : 'auto'
    };
});

// ------- MÉTODOS - AÇÕES DE CONTEXTO -------
/**
 * Abre o modal de edição da seção
 */
const editSection = () => {
    sectionStore.setSelectedSection(section);
    sectionStore.startEditing();
};

/**
 * Adiciona uma nova prateleira no local do clique
 * @param event Evento do mouse
 */
const addShelf = (event: MouseEvent) => {
    shelvesStore.handleDoubleClick({
        shelf_position: event.offsetY / props.scaleFactor,
        section_id: section.id,
    });
    event.stopPropagation();
};

/**
 * Define o alinhamento do módulo
 * @param alignment Tipo de alinhamento ('justify', 'left', 'center', 'right') ou null para não alinhar
 */
const justifyModule = (alignment: string | null = null) => {
    if (!gondola.id) {
        toast({
            title: 'Aviso',
            description: 'Não é possível justificar módulo: gondolaId não fornecido.',
            variant: 'default'
        });
        return;
    }
    editorStore.setSectionAlignment(gondola.id, section.id, alignment);
};

/**
 * Inverte a ordem das prateleiras no módulo
 */
const inverterModule = () => {
    if (!gondola.id) {
        toast({
            title: 'Aviso',
            description: 'Não é possível inverter prateleiras: gondolaId não fornecido.',
            variant: 'default'
        });
        return;
    }
    editorStore.invertShelvesInSection(gondola.id, section.id);
};

// ------- MÉTODOS - HELPERS -------
/**
 * Cria um novo segmento a partir de um produto
 * @param product Produto para criar o segmento
 * @param shelf Prateleira onde o segmento será adicionado
 * @param layerQuantity Quantidade de camadas
 * @returns Novo objeto Segment
 */
const createSegmentFromProduct = (product: Product, shelf: ShelfType, layerQuantity: number): Segment => {
    const timestamp = Date.now();
    const segmentId = `segment-${timestamp}-${shelf.segments?.length || 0}`;
    const layerId = `layer-${timestamp}-${product.id}`;

    return {
        id: segmentId,
        user_id: null,
        tenant_id: '',
        width: parseInt(section.width.toString()),
        ordering: (shelf.segments?.length || 0) + 1,
        quantity: 1,
        shelf_id: shelf.id,
        spacing: 0,
        position: 0,
        alignment: '',
        settings: null,
        status: 'published',
        tabindex: (shelf.segments?.length || 0) + 1,
        layer: {
            id: layerId,
            product_id: product.id,
            product: product,
            quantity: layerQuantity || 1,
            status: 'published',
            height: product.height,
            segment_id: segmentId,
            tabindex: 0,
        }
    };
};

// ------- MÉTODOS - DRAG & DROP PRATELEIRAS -------
/**
 * Inicia o arrasto de uma prateleira
 * @param shelf Prateleira sendo arrastada
 */
const handleShelfDragStart = (shelf: ShelfType) => {
    draggingShelf.value = shelf;
};

/**
 * Gerencia o evento dragover na seção
 * @param event Evento de arrasto
 */
const handleSectionDragOver = (event: DragEvent) => {
    if (!event.dataTransfer) return;

    // Verifica se o que está sendo arrastado é uma prateleira
    const isShelf = event.dataTransfer.types.includes('text/shelf');

    if (isShelf) {
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
        dropTargetActive.value = true;
    }
};

/**
 * Gerencia a saída do cursor da área de drop
 */
const handleSectionDragLeave = () => {
    dropTargetActive.value = false;
};

/**
 * Gerencia o drop de uma prateleira na seção
 * @param event Evento de drop
 */
const handleSectionDrop = async (event: DragEvent) => {
    if (!event.dataTransfer) return;
    const shelfData = event.dataTransfer.getData('text/shelf');

    if (!shelfData) {
        dropTargetActive.value = false;
        return;
    }

    try {
        const shelf = JSON.parse(shelfData) as ShelfType;
        const mouseY = event.offsetY;
        const newPosition = mouseY / props.scaleFactor;
        const shelfHeight = draggingShelf.value?.shelf_height || 0;

        // Verifica se a posição é válida
        if (newPosition >= 0 && newPosition <= section.height - shelfHeight) {
            if (!gondola.id) {
                toast({
                    title: 'Erro Interno',
                    description: 'Contexto da gôndola não encontrado.',
                    variant: 'destructive'
                });
                draggingShelf.value = null;
                dropTargetActive.value = false;
                return;
            }

            // Atualiza a posição da prateleira
            editorStore.setShelfPosition(gondola.id, section.id, shelf.id, {
                shelf_position: newPosition,
                shelf_x_position: -4
            });
        } else {
            toast({
                title: 'Aviso',
                description: 'Posição de prateleira inválida',
                variant: 'default',
            });
        }
    } catch (e) {
        console.error('Erro ao processar dados da prateleira no drop:', e);
        toast({
            title: 'Erro',
            description: 'Falha ao mover prateleira.',
            variant: 'destructive'
        });
    } finally {
        draggingShelf.value = null;
        dropTargetActive.value = false;
    }
};

// ------- MÉTODOS - DRAG & DROP PRODUTOS/CAMADAS -------
/**
 * Gerencia o drop de um produto em uma prateleira
 * @param product Produto sendo dropado
 * @param shelf Prateleira alvo
 * @param dropPosition Posição do drop (pode ser usado para calcular a posição X inicial)
 */
const handleProductDropOnShelf = async (product: Product, shelf: ShelfType) => {
    if (!gondola.id) {
        toast({
            title: 'Erro Interno',
            description: 'Contexto da gôndola não encontrado.',
            variant: 'destructive'
        });
        return;
    }

    // Criar camada temporária para validação
    // Usar spacing padrão 0, pois spacing vem da Layer, não do Product.
    const tempLayer: Layer = {
        id: `temp-layer-${Date.now()}`,
        product_id: product.id,
        product: product,
        quantity: 1, // Validando para quantidade 1
        status: 'temp',
        height: product.height,
        segment_id: 'temp',
        spacing: 0, // <-- Definir spacing padrão 0 aqui
        tabindex: 0,
    };

    // *** Validação ***
    const validation = validateShelfWidth(
        shelf,
        section.width,
        null,
        0,
        tempLayer
    );

    if (!validation.isValid) {
        toast({
            title: "Limite de Largura Excedido",
            description: `Adicionar este produto excederia a largura da seção (${section.width}cm). Largura resultante: ${validation.totalWidth.toFixed(1)}cm`,
            variant: "destructive",
        });
        return;
    }
    // *** Fim Validação ***

    // Prossegue se válido
    const newSegment = createSegmentFromProduct(product, shelf, 1);
    // TODO: Calcular newSegment.position baseado em dropPosition se necessário
    // TODO: Permitir definir o SPACING da nova layer/segmento aqui? 
    //      (newSegment atualmente não define spacing na layer criada)

    try {
        editorStore.addSegmentToShelf(gondola.id, section.id, shelf.id, newSegment);
    } catch (error) {
        console.error('Erro ao adicionar produto/segmento ao editorStore:', error);
        const errorDesc = (error instanceof Error) ? error.message : 'Falha ao atualizar o estado do editor.';
        toast({
            title: 'Erro Interno',
            description: errorDesc,
            variant: 'destructive'
        });
    }
};

/**
 * Gerencia a cópia de uma camada para uma prateleira
 * @param segment Segmento sendo copiado
 * @param shelf Prateleira alvo
 */
const handleSegmentCopy = async (segment: Segment, shelf: ShelfType) => {
    if (!gondola.id) {
        toast({
            title: 'Erro Interno',
            description: 'Contexto da gôndola não encontrado.',
            variant: 'destructive'
        });
        return;
    }

    // *** Validação ***
    const validation = validateShelfWidth(
        shelf,
        section.width,
        null,
        0,
        segment.layer
    );

    if (!validation.isValid) {
        toast({
            title: "Limite de Largura Excedido",
            description: `Copiar este produto/segmento excederia a largura da seção (${section.width}cm). Largura resultante: ${validation.totalWidth.toFixed(1)}cm`,
            variant: "destructive",
        });
        return;
    }
    // *** Fim Validação ***

    // Prossegue se válido
    const newSegment = createSegmentFromProduct(segment.layer.product, shelf, segment.layer.quantity);
    try {
        editorStore.addSegmentToShelf(gondola.id, section.id, shelf.id, newSegment);
    } catch (error) {
        console.error('Erro ao copiar camada/segmento para o editorStore:', error);
        const errorDesc = (error instanceof Error) ? error.message : 'Falha ao atualizar o estado do editor.';
        toast({
            title: 'Erro Interno',
            description: errorDesc,
            variant: 'destructive'
        });
    }
};

/**
 * Atualiza uma camada movendo-a para outra prateleira
 * @param Segment Camada sendo movida
 * @param targetShelf Prateleira alvo
 */
const updateSegment = (segment: Segment, targetShelf: ShelfType) => {
    const segmentToMove = segment;
    if (!segmentToMove) {
        console.error('updateLayer: Objeto segment não encontrado na layer.');
        return;
    }

    const segmentId = segmentToMove.id;
    const oldShelfId = segmentToMove.shelf_id;
    const newShelfId = targetShelf.id;
    const newSectionId = targetShelf.section_id;

    // Encontrar oldSectionId ... (lógica existente)
    let oldSectionId = targetShelf.section_id;
    if (editorStore.currentState?.gondolas) {
        for (const g of editorStore.currentState.gondolas) {
            if (g.id === gondola.id) {
                for (const s of g.sections) {
                    if (s.shelves?.some(sh => sh.id === oldShelfId)) {
                        oldSectionId = s.id;
                        break;
                    }
                }
                break;
            }
        }
    }
    // Encontrar seção de destino para obter largura
    const destinationSection = editorStore.currentState?.gondolas
        .find(g => g.id === gondola.id)?.sections
        .find(s => s.id === newSectionId);

    if (!destinationSection) {
        console.error('updateLayer: Seção de destino não encontrada no editorStore.');
        toast({ title: 'Erro Interno', description: 'Seção de destino não encontrada.', variant: 'destructive' });
        return;
    }

    if (oldShelfId === newShelfId) return; // Evita auto-transferência

    // *** Validação (na prateleira DESTINO) ***
    const validation = validateShelfWidth(
        targetShelf,
        destinationSection.width,
        null,
        0,
        segmentToMove.layer
    );

    if (!validation.isValid) {
        toast({
            title: "Limite de Largura Excedido",
            description: `Mover este segmento excederia a largura da seção destino (${destinationSection.width}cm). Largura resultante: ${validation.totalWidth.toFixed(1)}cm`,
            variant: "destructive",
        });
        return;
    }
    // *** Fim Validação ***

    if (!gondola.id || !oldSectionId || !oldShelfId || !newSectionId || !newShelfId || !segmentId) {
        console.error('updateLayer: IDs faltando para realizar a transferência.',
            { gondolaId: gondola.id, oldSectionId, oldShelfId, newSectionId, newShelfId, segmentId }
        );
        toast({
            title: 'Erro Interno',
            description: 'Dados insuficientes para mover o segmento.',
            variant: 'destructive'
        });
        return;
    }

    editorStore.transferSegmentBetweenShelves(
        gondola.id,
        oldSectionId,
        oldShelfId,
        newSectionId,
        newShelfId,
        segmentId
    );
};

// ------- MÉTODOS - EVENT HANDLERS GLOBAIS -------
/**
 * Gerencia teclas pressionadas globalmente
 * @param event Evento de teclado
 */
const handleKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Escape') {
        productStore.clearSelection();
    }
};

/**
 * Gerencia cliques fora dos elementos selecionáveis
 * @param event Evento de clique
 */
const handleClickOutside = (event: MouseEvent) => {
    const clickedElement = event.target as HTMLElement;

    // Ignora cliques em elementos específicos
    if (clickedElement.closest('.border-destructive')) return;
    if (clickedElement.dataset.state) return;
    if (clickedElement.closest('.no-remove-properties')) return;

    // Limpa seleções com base no elemento clicado
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

/**
 * Gerencia duplo clique para adicionar prateleira
 * @param event Evento de duplo clique
 */
const handleDoubleClick = (event: MouseEvent) => {
    event.stopPropagation();
    shelvesStore.handleDoubleClick({
        shelf_position: event.offsetY / props.scaleFactor,
        section_id: section.id,
    });
};

// ------- LIFECYCLE HOOKS -------
onMounted(() => {
    // Adiciona event listeners globais
    window.addEventListener('keydown', handleKeydown, { passive: true });
    document.addEventListener('click', handleClickOutside, true);

    // Adiciona evento de duplo clique ao elemento da seção
    if (sectionRef.value) {
        sectionRef.value.addEventListener('dblclick', handleDoubleClick);
    }
});

onUnmounted(() => {
    // Remove event listeners globais
    window.removeEventListener('keydown', handleKeydown);
    document.removeEventListener('click', handleClickOutside, true);

    // Remove evento de duplo clique ao elemento da seção
    if (sectionRef.value) {
        sectionRef.value.removeEventListener('dblclick', handleDoubleClick);
    }
});
</script>

<style scoped>
.section-container>.absolute.bottom-0 {
    z-index: -1;
}

.section-drag-over {
    background-color: rgba(59, 130, 246, 0.05);
    border: 2px dashed rgba(59, 130, 246, 0.5);
}
</style>