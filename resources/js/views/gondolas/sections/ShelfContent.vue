<template>
    <div class=" w-full flex items-center justify-center text-center text-xs text-gray-100 bg-transparent p-5 rounded-md "
        :style="shelfContentStyle" @dragenter.prevent="handleDragEnter" @dragover.prevent="handleDragOver"
        @dragleave="handleDragLeave" @drop.prevent="handleDrop">
        <!-- Quero alinhar o texto no centro da prateleira  -->
        <span class="text-center text-gray-800 dark:text-gray-200 translate-y-1/2 pointer-events-none font-bold" v-if="dragShelfActive"> {{ shelftext }}</span>
    </div>
</template>

<script setup lang="ts">
import { defineEmits, defineProps, ref, watch, computed, CSSProperties } from 'vue'; 
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
}>();
const dragShelfActive = ref(false); // Estado para rastrear se a prateleira está sendo arrastada
const dragEnterCount = ref(0); // Garantir que está definido aqui
const shelftext = ref(`Shelf (Pos: ${props.shelf.shelf_position.toFixed(1)}cm)`); // Texto da prateleira
// Definir Emits
const emit = defineEmits(['drop-product', 'drop-layer', 'drop-layer-copy']); // Para quando um produto é solto na prateleira
 

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
        heightPx = Math.max(0, heightPx - bottomPaddingPx);
        otherStyles = {
            transform: `translateY(-${heightPx}px)`
        }
    } else {
        // Para as demais, aplica padding no topo e embaixo
        topPx += topPaddingPx; // Desce o topo um pouco
        heightPx = Math.max(0, heightPx - topPaddingPx - bottomPaddingPx); // Reduz altura pelos dois paddings
    }


    // // Debug logs
    // console.log(`Shelf ${currentIndex} (Pos ${currentShelf.shelf_position.toFixed(1)}): TopPx=${topPx.toFixed(1)}, HeightPx=${heightPx.toFixed(1)}`);

    return {
        width: '100%',
        height: `${heightPx}px`,
        top: `${topPx}px`,
        left: '0',
        position: 'absolute',
        transition: 'all 0.2s ease',
        zIndex: dragShelfActive.value ? '9999' : '1',
        ...otherStyles,
        // Adicione outros estilos se necessário (background, borda para debug, etc.)
        // Ex: backgroundColor: 'rgba(255, 0, 0, 0.3)',
    };
});

// --- Lógica de Drag and Drop ---

// Verifica se o tipo de dado arrastado é aceitável E se a origem é diferente (se aplicável)
const isDropTargetActive = (dataTransfer: DataTransfer | null): boolean => {
    console.log('--- Checking isDropTargetActive ---'); // Log inicial
    if (!dataTransfer) {
        console.log('No dataTransfer, returning false');
        return false;
    }
    const types = dataTransfer.types;
    console.log('Detected types:', types);

    // Permite produtos novos sempre
    if (types.includes('text/product')) {
        console.log('Type is text/product, returning true');
        return true;
    }

    // Permite cópias sempre
    if (types.includes('text/layer/copy')) {
        console.log('Type is text/layer/copy, returning true');
        return true;
    }

    // Se for mover uma camada, verifica a origem
    if (types.includes('text/layer')) {
        console.log('Type is text/layer, checking origin...');
        try {
            const layerDataString = dataTransfer.getData('text/layer');
            console.log('Layer data string:', layerDataString); // <<< LOG IMPORTANTE
            if (!layerDataString) {
                 console.log('Layer data string is empty, returning false');
                 return false; // Não conseguiu ler os dados
            }

            const layerData = JSON.parse(layerDataString);
            console.log('Parsed layer data:', layerData); // <<< LOG IMPORTANTE
            const originShelfId = layerData?.segment?.shelf_id;
            console.log('Origin Shelf ID:', originShelfId); // <<< LOG IMPORTANTE
            console.log('Current Shelf ID:', props.shelf.id); // <<< LOG IMPORTANTE

            // Se não encontrou ID de origem, permite por segurança (ou pode bloquear, dependendo da regra)
            if (!originShelfId) {
                console.warn('Origin Shelf ID not found, returning true (lenient)');
                return true; // Ou false, se quiser ser mais estrito
            }

            // Só ativa se a origem for DIFERENTE da prateleira atual
            const shouldActivate = originShelfId !== props.shelf.id;
            console.log(`Origin (${originShelfId}) !== Current (${props.shelf.id}) ? ${shouldActivate}. Returning ${shouldActivate}`);
            return shouldActivate;

        } catch (e) {
            console.error('Error parsing text/layer data:', e);
            console.log('Returning false due to parsing error');
            return false; // Erro ao processar, não ativa
        }
    }

    console.log('No accepted types found, returning false');
    return false; // Nenhum tipo aceitável
};

const handleDragEnter = (event: DragEvent) => {
    console.log('--- handleDragEnter called ---'); // Log: Entrou?
    const types = event.dataTransfer?.types;
    console.log('handleDragEnter: Detected types:', types);

    // Usa a nova função de verificação que inclui a checagem de origem
    const canActivate = isDropTargetActive(event.dataTransfer);
    console.log(`handleDragEnter: isDropTargetActive returned ${canActivate}`);
    if (!canActivate) {
        console.log('handleDragEnter: Cannot activate, returning.');
        return;
    }

    event.preventDefault();
    dragEnterCount.value++;
    console.log(`handleDragEnter: Incremented count to ${dragEnterCount.value}`);

    if (dragEnterCount.value === 1) {
        console.log('handleDragEnter: First entry, activating visual state...'); // Log: Vai ativar?
        dragShelfActive.value = true;
        shelftext.value = `Soltar aqui (Pos: ${props.shelf.shelf_position.toFixed(1)}cm)`;
        if (event.currentTarget) {
             console.log('handleDragEnter: Adding drag-over class');
             (event.currentTarget as HTMLElement).classList.add('drag-over');
        } else {
             console.log('handleDragEnter: currentTarget is null?');
        }
        console.log(`handleDragEnter: dragShelfActive set to ${dragShelfActive.value}`);
    } else {
         console.log(`handleDragEnter: Not first entry (count: ${dragEnterCount.value}), not changing visual state.`);
    }
};

const handleDragOver = (event: DragEvent) => {
    // console.log('--- handleDragOver called ---'); // Manter comentado por enquanto
    // const types = event.dataTransfer?.types;
    // console.log('handleDragOver: Detected types:', types);

    const canAccept = isDropTargetActive(event.dataTransfer);
    // console.log(`handleDragOver: isDropTargetActive returned ${canAccept}`);
    if (!canAccept) {
        // console.log('handleDragOver: Cannot accept, setting dropEffect to none and returning.');
        if(event.dataTransfer) event.dataTransfer.dropEffect = 'none';
        return;
    }

    // console.log('handleDragOver: Can accept, calling preventDefault().');
    event.preventDefault();

    if (event.dataTransfer) {
        // Determinar o efeito correto
        let effect: DataTransfer["dropEffect"] = 'move'; // Padrão é mover
        if (event.dataTransfer.types.includes('text/layer/copy')) {
            effect = 'copy';
        } else if (event.dataTransfer.types.includes('text/product')) {
            effect = 'copy'; // <<-- NOVO: Usar 'copy' para produtos novos
        }
        // console.log(`handleDragOver: Setting dropEffect to ${effect}`);
        event.dataTransfer.dropEffect = effect;
    }
};

const handleDragLeave = (event: DragEvent) => {
    // A lógica do contador já garante que só desativa ao sair completamente
    if (dragEnterCount.value > 0) {
         dragEnterCount.value--;

         if (dragEnterCount.value === 0) {
             dragShelfActive.value = false;
             if (event.currentTarget) {
                 (event.currentTarget as HTMLElement).classList.remove('drag-over');
             }
         }
    }
};

const handleDrop = (event: DragEvent) => {
    console.log('--- handleDrop called ---'); // Log: Entrou no handleDrop
    event.preventDefault();

    // Verifica novamente no drop por segurança
    const isActiveTarget = isDropTargetActive(event.dataTransfer);
    console.log(`handleDrop: isDropTargetActive returned ${isActiveTarget}`);
    if (!isActiveTarget || !event.dataTransfer) {
         console.log('handleDrop: Not an active target or no dataTransfer, resetting and returning.');
         dragEnterCount.value = 0;
         dragShelfActive.value = false;
         if (event.currentTarget) {
            (event.currentTarget as HTMLElement).classList.remove('drag-over');
         }
        return;
    }

    try {
        console.log('handleDrop: Inside try block');
        const types = event.dataTransfer.types;
        console.log('handleDrop: Data types on drop:', types);

        const position = { x: event.offsetX, y: event.offsetY };

        if (types.includes('text/product')) {
            const productData = event.dataTransfer.getData('text/product');
            console.log('handleDrop: Got productData:', productData);
            if (!productData) {
                 console.error('handleDrop: productData is empty!');
                 return; // Sai se não houver dados
            }
            console.log('handleDrop: Attempting to parse productData...');
            const product = JSON.parse(productData) as Product;
            console.log('handleDrop: Parsed product:', product);
            console.log('handleDrop: Emitting drop-product...');
            emit('drop-product', product, props.shelf, position);
            console.log('handleDrop: drop-product emitted.');

        } else if (types.includes('text/layer')) {
            const layerData = event.dataTransfer.getData('text/layer');
            console.log('handleDrop: Got layerData:', layerData);
            if (!layerData) {
                 console.error('handleDrop: layerData is empty!');
                 return;
            }
            console.log('handleDrop: Attempting to parse layerData...');
            const layer = JSON.parse(layerData) as Layer;
            console.log('handleDrop: Parsed layer:', layer);
            console.log('handleDrop: Emitting drop-layer...');
            emit('drop-layer', layer, props.shelf, position);
            console.log('handleDrop: drop-layer emitted.');

        } else if (types.includes('text/layer/copy')) {
            const layerDataCopy = event.dataTransfer.getData('text/layer/copy');
            console.log('handleDrop: Got layerDataCopy:', layerDataCopy);
             if (!layerDataCopy) {
                 console.error('handleDrop: layerDataCopy is empty!');
                 return;
            }
            console.log('handleDrop: Attempting to parse layerDataCopy...');
            const layer = JSON.parse(layerDataCopy) as Layer;
            console.log('handleDrop: Parsed layer copy:', layer);
            console.log('handleDrop: Emitting drop-layer-copy...');
            emit('drop-layer-copy', layer, props.shelf, position);
            console.log('handleDrop: drop-layer-copy emitted.');
        } else {
            console.log('handleDrop: No relevant data type found on drop.');
        }

    } catch (e) {
        console.error("handleDrop: Error processing dropped data:", e);
    } finally {
         console.log('handleDrop: Entering finally block, resetting state.');
         dragEnterCount.value = 0;
         dragShelfActive.value = false;
         if (event.currentTarget) {
             (event.currentTarget as HTMLElement).classList.remove('drag-over');
         }
    }
};

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
    z-index: 9999;
}
</style>
