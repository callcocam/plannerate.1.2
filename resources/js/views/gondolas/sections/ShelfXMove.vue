<template>
    <!-- 
        Área invisível sobre a prateleira que serve como handle para arrastar
        Cobre toda a área da prateleira (inset-0) e fica acima dos outros elementos (z-10)
        O cursor-move indica visualmente que o elemento pode ser arrastado
    -->
    <div
        class="absolute inset-0 z-10 flex h-full w-full cursor-move items-center justify-center"
        @mousedown="handleMouseDown"
        @dragstart="handleDragStart"
        @dragend="handleDragEnd"
    ></div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { useGondolaStore } from '../../../store/gondola';

/**
 * Props do componente
 * @property {Object} shelf - Objeto da prateleira que será arrastada
 * @property {Number} scaleFactor - Fator de escala usado para converter entre unidades lógicas e pixels
 * @property {Number} sectionWidth - Largura da seção em unidades lógicas
 * @property {Number} sectionHeight - Altura da seção em unidades lógicas
 * @property {Number} baseHeight - Altura da base da seção em unidades lógicas
 * @property {HTMLElement|null} shelfElement - Referência ao elemento DOM da prateleira
 */
const props = defineProps({
    shelf: {
        type: Object,
        required: true,
    },
    scaleFactor: {
        type: Number,
        required: true,
    },
    sectionWidth: {
        type: Number,
        required: true,
    },
    sectionHeight: {
        type: Number,
        required: true,
    },
    baseHeight: {
        type: Number,
        required: true,
    },
    shelfElement: {
        type: [HTMLElement, null],
        default: null,
    },
});

// Estado para controlar se a prateleira está sendo arrastada
const isDragging = ref(false);
// Estado para identificar se a posição da prateleira foi modificada
const isDraggingShelf = ref(false);
// Armazena a seção de origem durante o arrasto
const dragStartSection = ref<string | null>(null);

// Store para interagir com o estado global das gôndolas
const gondolaStore = useGondolaStore();

// Computed para acessar o elemento DOM da prateleira
const shelfElement = computed(() => {
    return props.shelfElement;
});

/**
 * Manipulador para iniciar o arrasto com o mouse
 * Configura os listeners necessários e previne comportamentos padrão
 */
const handleMouseDown = (e: MouseEvent) => {
    // Ativa o estado de arrasto
    isDragging.value = true;
    console.log('Iniciando arrasto da prateleira');

    // Adiciona listeners temporários para acompanhar o movimento e soltura do mouse
    document.addEventListener('mousemove', handleMouseMove);
    document.addEventListener('mouseup', handleMouseUp);

    // Previne comportamentos indesejados como seleção de texto
    e.preventDefault();
};

/**
 * Handler para o evento dragstart nativo do HTML5
 * Usado principalmente como fallback e para compatibilidade
 */
const handleDragStart = (e: DragEvent) => {
    isDragging.value = true;
};

/**
 * Handler para o evento dragend nativo do HTML5
 * Reseta os estados de arrasto
 */
const handleDragEnd = () => {
    isDragging.value = false;
    dragStartSection.value = null;
};

/**
 * Lógica principal de arrasto que atualiza a posição da prateleira
 * É chamada continuamente durante o movimento do mouse
 */
const handleMouseMove = (e: MouseEvent) => {
    // Verifica se estamos em estado de arrasto e se temos referência ao elemento
    if (!isDragging.value || !shelfElement.value) return;

    // Obtém o retângulo do container pai para cálculos de posição relativa
    const containerRect = shelfElement.value.parentElement?.getBoundingClientRect();
    if (!containerRect) return;

    // Calcula a posição Y relativa ao container
    const relativeY = e.clientY - containerRect.top;

    // Limites de arrasto - não permitir arrastar para fora do container
    if (relativeY < 0 || relativeY > containerRect.height) return;

    // Verificação adicional para não ultrapassar o limite inferior considerando a altura da prateleira
    const maxYPosition = props.sectionHeight * props.scaleFactor - props.baseHeight - props.shelf.shelf_height;
    if (relativeY >= maxYPosition) {
        return;
    }

    // Atualiza a posição da prateleira no store
    // O parâmetro false indica que não queremos persistir a alteração no servidor imediatamente
    gondolaStore.updateShelf(
        props.shelf.id,
        {
            shelf_position: relativeY / props.scaleFactor,
        },
        false,
    );

    // Marca que a prateleira foi efetivamente movida
    isDraggingShelf.value = true;
};

/**
 * Finaliza o arrasto quando o mouse é solto
 * Persiste a alteração final e remove os event listeners
 */
const handleMouseUp = () => {
    // Se houve arrasto efetivo, persiste a posição final
    if (isDragging.value && isDraggingShelf.value) {
        gondolaStore.updateShelf(props.shelf.id, {
            shelf_position: props.shelf.shelf_position,
        });
    }

    // Reseta os estados de arrasto
    isDragging.value = false;
    isDraggingShelf.value = false;

    // Remove os event listeners temporários
    document.removeEventListener('mousemove', handleMouseMove);
    document.removeEventListener('mouseup', handleMouseUp);
};
</script>
