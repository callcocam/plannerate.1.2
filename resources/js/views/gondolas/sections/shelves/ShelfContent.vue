<template>
    <div class=" w-full flex items-center justify-center text-center text-xs text-gray-100 bg-transparent p-5 rounded-md "
        :style="shelfContentStyle" @dragenter.prevent="handleDragEnter" @dragover.prevent="handleDragOver"
        @dragleave="handleDragLeave" @drop.prevent="handleDrop" ref="shelfContentRef">
        <!-- Quero alinhar o texto no centro da prateleira  -->
        <span class="text-center text-gray-800 dark:text-gray-200 pointer-events-none font-bold absolute inset-0 flex items-center justify-center"
            v-if="dragShelfActive"> {{ shelftext }}</span>
        
        <!-- Overlay para quando um segment está sendo arrastado - REMOVIDO, agora está no Shelf.vue -->
        <!-- Texto para quando um segment está sendo arrastado - REMOVIDO, agora está no Shelf.vue -->
    </div>
</template>

<script setup lang="ts">
import {  ref, watch, computed, CSSProperties } from 'vue';
import { type Shelf } from '@plannerate/types/shelves';
import { Section } from '@/types/sections';
import type { Product, Layer } from '@plannerate/types/segment'; 
// Definir Props
const props = defineProps<{
    shelf: Shelf;
    scaleFactor: number;
    sortedShelves: Shelf[];
    index: number;
    section: Section;
    segmentDragging?: boolean;
    draggingSegment?: any;
}>();
const dragShelfActive = ref(false); // Estado para rastrear se a prateleira está sendo arrastada
const dragEnterCount = ref(0); // Garantir que está definido aqui
const shelftext = ref(`Shelf (Pos: ${props.shelf.shelf_position.toFixed(1)}cm)`); // Texto da prateleira
// segmentDragText removido - agora está no Shelf.vue

// Estado local para detectar quando um segment está sendo arrastado sobre esta prateleira
const segmentDragOverActive = ref(false);
const segmentDragOverCount = ref(0);

// Debug: log quando segmentDragging muda
watch(() => props.segmentDragging, (newValue) => {
    console.log('ShelfContent: segmentDragging mudou para', newValue, 'shelf:', props.shelf.id);
});
const shelfContentRef = ref<HTMLElement | null>(null);
// Definir Emits
const emit = defineEmits(['drop-product', 'drop-products-multiple', 'drop-segment', 'drop-segment-copy']); // Para quando um produto é solto na prateleira
// const editorStore = useEditorStore();
// import { type Shelf as ShelfType } from '@plannerate/types/shelves';

watch(dragShelfActive, (newValue) => {
    if (newValue) {
        // Adicionar lógica para quando a prateleira está sendo arrastada
        shelftext.value = `Arrastando Prateleira (Pos: ${props.shelf.shelf_position.toFixed(1)}cm)`;
    } else {
        // Adicionar lógica para quando a prateleira não está mais sendo arrastada
        shelftext.value = `Shelf (Pos: ${props.shelf.shelf_position.toFixed(1)}cm)`;
    }
});


const shelfContentStyle = computed((): CSSProperties => {
    const currentShelf = props.shelf;
    const currentIndex = props.index;
    const sortedShelves = props.sortedShelves;
    const scaleFactor = props.scaleFactor;
    const sectionHeight = props.section.height;

    // --- Definir Padding Visual (em pixels) ---
    const verticalPaddingPx = 8; // Ex: 4px total (2px topo, 2px baixo)
    const topPaddingPx = verticalPaddingPx / 2;
    const bottomPaddingPx = verticalPaddingPx / 2;
    const minTopHeightPx = 120; // Altura mínima para a primeira prateleira

    // --- Calcular Posição e Altura em CM ---
    let topPositionCm: number;
    let rawHeightCm: number; // Altura bruta do espaço

    if (currentIndex === 0) {
        topPositionCm = 0;
        rawHeightCm = Math.max(0, currentShelf.shelf_position);
    } else {
        const previousShelf = sortedShelves[currentIndex - 1];
        topPositionCm = Math.max(0, previousShelf.shelf_position);
        rawHeightCm = Math.max(0, currentShelf.shelf_position - previousShelf.shelf_position);
    }

    // Garante que não ultrapasse a altura da seção
    if (topPositionCm + rawHeightCm > sectionHeight) {
        rawHeightCm = Math.max(0, sectionHeight - topPositionCm);
    }

    // --- Converter para Pixels ---
    let topPx = topPositionCm * scaleFactor + props.shelf.shelf_height * scaleFactor;
    let heightPx = rawHeightCm * scaleFactor - props.shelf.shelf_height * scaleFactor;

    // --- Aplicar Ajustes de Padding e Altura Mínima ---
    let otherStyles = {}
    // 1. Altura mínima para a primeira prateleira
    if (currentIndex === 0) {
        heightPx = Math.max(minTopHeightPx, heightPx);
        // Para a primeira, o padding inferior é aplicado, mas o topo começa em 0
        heightPx = Math.max(props.shelf.shelf_position, heightPx - bottomPaddingPx);
        otherStyles = {
            transform: `translateY(-${heightPx}px)`
        }
        topPx = props.shelf.shelf_position * scaleFactor;
    } else {
        // Para as demais, aplica padding no topo e embaixo
        topPx += topPaddingPx; // Desce o topo um pouco
        heightPx = Math.max(0, heightPx - topPaddingPx - bottomPaddingPx); // Reduz altura pelos dois paddings
    }


    // // Debug logs
    // console.log(`Shelf ${currentIndex} (Pos ${currentShelf.shelf_position.toFixed(1)}): TopPx=${topPx.toFixed(1)}, HeightPx=${heightPx.toFixed(1)}`);

    // Garantir altura mínima quando segment está sendo arrastado
    const finalHeightPx = props.segmentDragging ? Math.max(heightPx, 50) : heightPx;

    return {
        width: '100%',
        height: `${finalHeightPx}px`,
        top: `${topPx}px`,
        left: '0',
        position: 'absolute',
        zIndex: dragShelfActive.value ? 9999 : 0, // Z-index alto durante drag
        ...otherStyles,
        // Adicione outros estilos se necessário (background, borda para debug, etc.)
        // backgroundColor: 'rgba(255, 0, 0, 0.3)',
    };
});

// --- Lógica de Drag and Drop ---

// Renomeada: Verifica apenas se o TIPO de dado arrastado é aceitável
const isAcceptedDataType = (dataTransfer: DataTransfer | null): boolean => {
    if (!dataTransfer) return false;
    const types = dataTransfer.types;
    // console.log('isAcceptedDataType: Detected types:', types);
    return types.includes('text/product') || 
           types.includes('text/products-multiple') || 
           types.includes('text/segment') || 
           types.includes('text/segment/copy');
};

// Verifica especificamente se é um segment sendo arrastado
const isSegmentBeingDragged = (dataTransfer: DataTransfer | null): boolean => {
    if (!dataTransfer) return false;
    const types = dataTransfer.types;
    return types.includes('text/segment') || types.includes('text/segment/copy');
};

const handleDragEnter = (event: DragEvent) => {
    // console.log('--- handleDragEnter called ---');
    // Verifica apenas o TIPO
    if (!isAcceptedDataType(event.dataTransfer)) {
        return;
    }

    event.preventDefault();
    dragEnterCount.value++;
    // console.log(`handleDragEnter: Incremented count to ${dragEnterCount.value}`);

    // Verifica se é um segment sendo arrastado
    if (isSegmentBeingDragged(event.dataTransfer)) {
        segmentDragOverCount.value++;
        if (!segmentDragOverActive.value) {
                // Performance: Removed console.log to prevent spam during drag operations
            segmentDragOverActive.value = true;
        }
    }

    // Ativa o visual se puder (baseado no tipo) e ainda não estiver ativo
    if (!dragShelfActive.value) {
        // console.log('handleDragEnter: Activating visual state...');
        dragShelfActive.value = true;
        shelftext.value = `Soltar aqui (Pos: ${props.shelf.shelf_position.toFixed(1)}cm)`;
        if (event.currentTarget) {
            (event.currentTarget as HTMLElement).classList.add('drag-over');
        }
    }
};

const handleDragOver = (event: DragEvent) => {
    // Verifica apenas o TIPO
    if (!isAcceptedDataType(event.dataTransfer)) {
        if (event.dataTransfer) event.dataTransfer.dropEffect = 'none';
        // Desativa visual se estava ativo por engano
        if (dragShelfActive.value) {
            // console.log('handleDragOver: Type not accepted, but was active. Deactivating visual state.');
            dragShelfActive.value = false;
            dragEnterCount.value = 0;
            if (event.currentTarget) {
                (event.currentTarget as HTMLElement).classList.remove('drag-over');
            }
        }
        return;
    }

    // Se pode aceitar (baseado no tipo)
    event.preventDefault();

    // Garante visual ativo
    if (!dragShelfActive.value) {
        // console.log('handleDragOver: Activating visual state (was inactive)...');
        dragShelfActive.value = true;
        shelftext.value = `Soltar aqui (Pos: ${props.shelf.shelf_position.toFixed(1)}cm)`;
        if (event.currentTarget) {
            (event.currentTarget as HTMLElement).classList.add('drag-over');
        }
    }

    // Define dropEffect
    if (event.dataTransfer) {
        let effect: DataTransfer["dropEffect"] = 'move';
        if (event.dataTransfer.types.includes('text/segment/copy') || 
            event.dataTransfer.types.includes('text/product') ||
            event.dataTransfer.types.includes('text/products-multiple')) {
            effect = 'copy';
        }
        event.dataTransfer.dropEffect = effect;
    }
};

const handleDragLeave = (event: DragEvent) => {
    // console.log('--- handleDragLeave called ---');
    if (dragEnterCount.value > 0) {
        dragEnterCount.value--;
        // console.log(`handleDragLeave: Decremented count to ${dragEnterCount.value}`);
        if (dragEnterCount.value === 0) {
            // console.log('handleDragLeave: Count is 0, DEACTIVATING visual state...');
            if (dragShelfActive.value) {
                dragShelfActive.value = false;
                if (event.currentTarget) {
                    // console.log('handleDragLeave: Removing drag-over class');
                    (event.currentTarget as HTMLElement).classList.remove('drag-over');
                }
            }
            
            // Resetar estado do segment drag
            if (segmentDragOverActive.value) {
                segmentDragOverCount.value = 0;
                segmentDragOverActive.value = false;
                // Performance: Removed console.log to prevent spam during drag operations
            }
        }
    } /* else {
        // console.log('handleDragLeave: Called but count is already 0.');
    } */
};

const { rafDebounce } = usePerformance();

const handleDrop = rafDebounce(async (event: DragEvent) => {
    event.preventDefault();
    const currentTargetElement = event.currentTarget as HTMLElement | null;

    // Função de reset movida para o início para melhor clareza
    const resetVisualState = () => {
        dragEnterCount.value = 0;
        if (dragShelfActive.value) {
            dragShelfActive.value = false;
        }
        if (currentTargetElement) {
            currentTargetElement.classList.remove('drag-over');
        }
        
        // Resetar estado do segment drag
        segmentDragOverCount.value = 0;
        if (segmentDragOverActive.value) {
            segmentDragOverActive.value = false;
            // Performance: Removed console.log to prevent spam during drag operations
        }
    };

    // Verifica tipo inicial, mas a lógica principal está no try
    if (!isAcceptedDataType(event.dataTransfer) || !event.dataTransfer) {
        resetVisualState();
        return;
    }

    try {
        const types = event.dataTransfer.types;
        const position = { x: event.offsetX, y: event.offsetY };

        if (types.includes('text/products-multiple')) {
            // Processar múltiplos produtos com async JSON parse
            const productsData = event.dataTransfer.getData('text/products-multiple');
            if (!productsData) { console.error('handleDrop: productsData is empty!'); return; }
            
            // Use async JSON parsing to prevent UI blocking
            await new Promise(resolve => {
                try {
                    const products = JSON.parse(productsData) as Product[];
                    emit('drop-products-multiple', products, props.shelf, position);
                    resolve(null);
                } catch (err) {
                    console.error('Error parsing products data:', err);
                    resolve(null);
                }
            });

        } else if (types.includes('text/product')) {
            // Processar produto único com async JSON parse
            const productData = event.dataTransfer.getData('text/product');
            if (!productData) { console.error('handleDrop: productData is empty!'); return; }
            
            // Use async JSON parsing to prevent UI blocking
            await new Promise(resolve => {
                try {
                    const product = JSON.parse(productData) as Product;
                    emit('drop-product', product, props.shelf, position);
                    resolve(null);
                } catch (err) {
                    console.error('Error parsing product data:', err);
                    resolve(null);
                }
            });

        } else if (types.includes('text/segment')) {
            const segmentDataString = event.dataTransfer.getData('text/segment');
            if (!segmentDataString) { console.error('handleDrop: segmentData is empty!'); return; }
            
            // Use async JSON parsing to prevent UI blocking
            await new Promise(resolve => {
                try {
                    const segmentData = JSON.parse(segmentDataString) as Layer & { segment?: { shelf_id?: string } };
                    const originShelfId = segmentData?.segment?.shelf_id;

                    // *** VERIFICAÇÃO DE ORIGEM MOVIDA PARA CÁ ***
                    if (originShelfId && originShelfId !== props.shelf.id) {
                        emit('drop-segment', segmentData, props.shelf, position);
                    } else if (!originShelfId) {
                        console.warn('handleDrop (segment): Origin Shelf ID not found in data. Allowing drop.');
                        emit('drop-segment', segmentData, props.shelf, position); // Comportamento leniente: permite se não achar origem
                    }
                    resolve(null);
                } catch (err) {
                    console.error('Error parsing segment data:', err);
                    resolve(null);
                }
            });
            
            // Use async JSON parsing to prevent UI blocking
            await new Promise(resolve => {
                try {
                    const segmentData = JSON.parse(segmentDataString) as Layer & { segment?: { shelf_id?: string } };
                    const originShelfId = segmentData?.segment?.shelf_id;

                    // *** VERIFICAÇÃO DE ORIGEM MOVIDA PARA CÁ ***
                    if (originShelfId && originShelfId !== props.shelf.id) {
                        emit('drop-segment', segmentData, props.shelf, position);
                    } else if (!originShelfId) {
                        console.warn('handleDrop (segment): Origin Shelf ID not found in data. Allowing drop.');
                        emit('drop-segment', segmentData, props.shelf, position); // Comportamento leniente: permite se não achar origem
                    }
                    resolve(null);
                } catch (err) {
                    console.error('Error parsing segment data:', err);
                    resolve(null);
                }
            });

        } else if (types.includes('text/segment/copy')) {
            const segmentDataCopy = event.dataTransfer.getData('text/segment/copy');
            if (!segmentDataCopy) { console.error('handleDrop: segmentDataCopy is empty!'); return; }
            
            // Use async JSON parsing to prevent UI blocking
            await new Promise(resolve => {
                try {
                    const segment = JSON.parse(segmentDataCopy) as Layer;
                    emit('drop-segment-copy', segment, props.shelf, position);
                    resolve(null);
                } catch (err) {
                    console.error('Error parsing segment copy data:', err);
                    resolve(null);
                }
            });

        } else {
            // console.log('handleDrop: No relevant data type found on drop.');
        }

    } catch (e) {
        console.error("handleDrop: Error processing dropped data:", e);
    } finally {
        resetVisualState(); // Garante reset no final
    }
});

// const handleDoubleClick = (event: MouseEvent) => {
//     event.stopPropagation();
//     const section = props.section;
//     console.log('handleDoubleClick: ', event.clientX, event.clientY);
//     editorStore.addShelfToSection(section.gondola_id, section.id, {
//         id: `temp-shelf-${Date.now()}`,
//         shelf_height: 4,
//         shelf_position: props.shelf.shelf_position - 10,
//         section_id: section.id,
//         product_type: 'normal',
//     } as ShelfType);
// }

// onMounted(() => {
//     if (shelfContentRef.value) {
//         shelfContentRef.value.addEventListener('dblclick', handleDoubleClick);
//     }
// });



</script>

<style scoped>
.drag-over {
    background-color: rgba(2, 16, 39, 0.1);
    border-color: rgba(13, 65, 150, 0.5);
    border-width: 2px;
    border-style: dashed;
    border-radius: 4px;
    /* Adicionar sombra se necessário */
    box-shadow: 0 0 10px rgba(59, 130, 246, 0.5);
    /* Adicionar transição suave */
    transition:
        border-color 0.2s ease-in-out,
        background-color 0.2s ease-in-out;
    /* Aumentar a area de drop */
    /* Adicionar um efeito de escala */
    cursor: grab;
    z-index: 9999 !important; /* Sempre por cima durante drag */
}

/* Estilos do overlay do segment removidos - agora estão no Shelf.vue */

/* Debug para o ShelfContent */
.debug-shelf-content {
    background-color: rgba(0, 255, 0, 0.3) !important;
    border: 2px solid green !important;
}

/* Estilos do overlay do segment removidos - agora estão no Shelf.vue */
</style>
