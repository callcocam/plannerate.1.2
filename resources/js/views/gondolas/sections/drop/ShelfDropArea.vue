<template>
    <div class="shelf-drop-area relative w-full h-full" @dragover.prevent="handleDragOver"
        @dragleave.prevent="handleDragLeave" @dragenter.prevent="handleDragEnter" @drop.prevent="handleDrop"
        ref="dropAreaRef">
        <!-- Área de conteúdo normal (slot) -->
        <slot :is-dragging-over="isDraggingOver"></slot>

        <!-- Indicador de drop (visível apenas durante drag-over) -->
        <div v-if="isDraggingOver" class="drop-indicator absolute inset-0 z-10 pointer-events-none">
            <!-- Borda indicadora -->
            <div class="absolute inset-0 border-2 border-dashed border-white border-opacity-60"></div>

            <!-- Mensagem de drop -->
            <div class="absolute inset-0 flex items-center justify-center">
                <div class="drop-message   text-gray-800 dark:text-gray-200 text-sm font-bold px-3 py-2 rounded-md ">
                    {{ dropMessage }}
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';

const props = defineProps<{
    // Tipo de itens que podem ser soltos nesta área
    acceptTypes?: string[];
    // Mensagem padrão para exibir durante o drag over
    defaultMessage?: string;
}>();

const emit = defineEmits<{
    // Emite quando um item é solto com os dados e informações de posição
    (e: 'drop', data: any, position: { x: number, y: number, relativeX: number, relativeY: number }): void;
    // Emite quando o estado de drag muda (para permitir atualizações no componente pai)
    (e: 'drag-state-change', isDragging: boolean, dragType: string | null): void;
}>();

// Referência ao elemento principal
const dropAreaRef = ref<HTMLElement | null>(null);

// Estado de arrastar
const isDraggingOver = ref(false);
const dragType = ref<string | null>(null);
const dragPosition = ref({ x: 0, y: 0, relativeX: 0, relativeY: 0 });

// Contagem para resolver problemas de eventos de drag leave/enter em elementos filhos
const dragEnterCount = ref(0);

// Mensagem de soltar baseada no tipo e posição
const dropMessage = computed(() => {
    if (!dragType.value) return props.defaultMessage || 'Soltar aqui';

    const position = Math.round(dragPosition.value.relativeY * 100);

    if (dragType.value === 'product') {
        return `Soltar produto (${position}%)`;
    } else if (dragType.value === 'layer') {
        return 'Mover camada para esta prateleira';
    } else if (dragType.value === 'layer-copy') {
        return 'Soltar cópia da camada aqui';
    }

    return props.defaultMessage || 'Soltar aqui';
});

// Verifica se o item arrastado é aceitável
const checkAcceptableType = (dataTransfer: DataTransfer): string | null => {
    // Se não há tipos aceitáveis definidos, aceita qualquer um
    console.log('checkAcceptableType', dataTransfer.types);
    if (!props.acceptTypes || props.acceptTypes.length === 0) {
        // Checa tipos específicos suportados
        if (dataTransfer.types.includes('text/product')) return 'product';
        if (dataTransfer.types.includes('text/layer')) return 'layer';
        if (dataTransfer.types.includes('text/layer-copy')) return 'layer-copy';
        return null;
    }

    // Caso contrário, verifica se algum dos tipos aceitáveis está presente
    for (const type of props.acceptTypes) {
        if (dataTransfer.types.includes(type)) {
            // Mapeia o tipo MIME para um tipo mais amigável
            if (type === 'text/product') return 'product';
            if (type === 'text/layer') return 'layer';
            if (type === 'text/layer-copy') return 'layer-copy';
            return type;
        }
    }

    return null;
};

// --- Handlers de Eventos ---

const handleDragEnter = (event: DragEvent) => {
    if (!event.dataTransfer) return;

    // Incrementa contador de entrada
    dragEnterCount.value++;

    // Se já está no estado de hover, não precisa fazer nada
    if (isDraggingOver.value) return;

    // Verifica o tipo sendo arrastado
    const acceptedType = checkAcceptableType(event.dataTransfer);

    if (acceptedType) {
        isDraggingOver.value = true;
        dragType.value = acceptedType;

        // Emite evento de mudança de estado
        emit('drag-state-change', true, acceptedType);
    }
};

const handleDragOver = (event: DragEvent) => {
    if (!event.dataTransfer || !isDraggingOver.value) return;

    // Atualiza a posição para cálculos percentuais
    const target = event.currentTarget as HTMLElement;

    dragPosition.value = {
        x: event.offsetX,
        y: event.offsetY,
        relativeX: event.offsetX / target.offsetWidth,
        relativeY: event.offsetY / target.offsetHeight
    };

    // Define o efeito da operação
    if (dragType.value === 'layer-copy') {
        event.dataTransfer.dropEffect = 'copy';
    } else {
        event.dataTransfer.dropEffect = 'move';
    }
};

const handleDragLeave = (event: DragEvent) => {
    // Decrementa contador de entrada
    dragEnterCount.value--;

    // Só considera saída quando o contador chegar a zero
    if (dragEnterCount.value <= 0) {
        dragEnterCount.value = 0; // Garantir que nunca seja negativo
        isDraggingOver.value = false;

        // Emite evento de mudança de estado
        emit('drag-state-change', false, null);
    }
};

// Função para limpar estado de drag (usada em unmounted e como fallback)
const clearDragState = () => {
    if (isDraggingOver.value) {
        isDraggingOver.value = false;
        dragEnterCount.value = 0;
        dragType.value = null;
        emit('drag-state-change', false, null);
    }
};

// Handler global para detectar quando o drag termina em qualquer lugar
const handleGlobalDragEnd = (event: DragEvent) => {
    clearDragState();
};

const handleDrop = (event: DragEvent) => {
    if (!event.dataTransfer) return;

    try {
        // Calcula as informações de posição finais
        const target = event.currentTarget as HTMLElement;
        const position = {
            x: event.offsetX,
            y: event.offsetY,
            relativeX: event.offsetX / target.offsetWidth,
            relativeY: event.offsetY / target.offsetHeight
        };

        // Extrai dados baseado no tipo detectado
        let data = null;

        if (dragType.value === 'product') {
            const productData = event.dataTransfer.getData('text/product');
            if (productData) data = JSON.parse(productData);
        } else if (dragType.value === 'layer') {
            const layerData = event.dataTransfer.getData('text/layer');
            if (layerData) data = JSON.parse(layerData);
        } else if (dragType.value === 'layer-copy') {
            const layerCopyData = event.dataTransfer.getData('text/layer/copy');
            if (layerCopyData) data = JSON.parse(layerCopyData);
        }

        // Emite evento de drop com dados e posição
        if (data) {
            emit('drop', { type: dragType.value, data }, position);
        }
    } catch (e) {
        console.error('Erro ao processar dados do drop:', e);
    } finally {
        // Limpa estado
        isDraggingOver.value = false;
        dragEnterCount.value = 0;
        dragType.value = null;

        // Emite evento de mudança de estado
        emit('drag-state-change', false, null);
    }
};

// Lifecycle hooks
onMounted(() => {
    // Adiciona listener global para eventos de drag end
    document.addEventListener('dragend', handleGlobalDragEnd);
});

onUnmounted(() => {
    // Remove listener global e limpa estado
    document.removeEventListener('dragend', handleGlobalDragEnd);
    clearDragState();
});
</script>

<style scoped>
.shelf-drop-area {
    transition: all 0.2s ease;
}

.drop-indicator {
    pointer-events: none;
    /* Importante: não interfere nos eventos de mouse */
    transition: opacity 0.2s ease;
}

.drop-message {
    animation: float 1s infinite alternate ease-in-out;
    white-space: nowrap;
}

@keyframes float {
    0% {
        transform: translateY(0);
    }

    100% {
        transform: translateY(-5px);
    }
}
</style>