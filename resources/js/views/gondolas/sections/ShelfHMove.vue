<template>
    <!-- 
        Área invisível lateral da prateleira que serve como handle para arrastar horizontalmente
        Fica na lateral direita com largura de 20px e cobre toda a altura (h-full)
        O cursor ew-resize indica visualmente que o elemento pode ser arrastado horizontalmente
    -->
    <div
        class="absolute right-0 top-0 z-10 h-full w-5 cursor-ew-resize"
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
 * @property {Number} minWidth - Largura mínima da prateleira em unidades lógicas
 * @property {Number} maxWidth - Largura máxima da prateleira em unidades lógicas
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
    minWidth: {
        type: Number,
        default: 20, // Valor mínimo padrão em unidades lógicas
    },
    maxWidth: {
        type: Number,
        default: null, // Será definido como sectionWidth se não for fornecido
    },
    shelfElement: {
        type: [HTMLElement, null],
        default: null,
    },
});

// Estado para controlar se a prateleira está sendo redimensionada
const isResizing = ref(false);
// Armazena a largura inicial da prateleira no início do arrasto
const initialWidth = ref(0);
// Armazena a posição X inicial do mouse
const initialMouseX = ref(0);

// Store para interagir com o estado global das gôndolas
const gondolaStore = useGondolaStore();

// Computed para acessar o elemento DOM da prateleira
const shelfElement = computed(() => {
    return props.shelfElement;
});

// Computed para a largura máxima permitida
const effectiveMaxWidth = computed(() => {
    return props.maxWidth || props.sectionWidth;
});

/**
 * Manipulador para iniciar o redimensionamento com o mouse
 * Configura os listeners necessários e armazena os valores iniciais
 */
const handleMouseDown = (e: MouseEvent) => {
    // Ativa o estado de redimensionamento
    isResizing.value = true;

    // Armazena a largura inicial da prateleira
    initialWidth.value = props.shelf.shelf_width || props.sectionWidth;

    // Armazena a posição X inicial do mouse
    initialMouseX.value = e.clientX;

    // Adiciona listeners temporários para acompanhar o movimento e soltura do mouse
    document.addEventListener('mousemove', handleMouseMove);
    document.addEventListener('mouseup', handleMouseUp);

    // Previne comportamentos indesejados como seleção de texto
    e.preventDefault();
    e.stopPropagation();

    console.log('Iniciando redimensionamento horizontal da prateleira');
};

/**
 * Handler para o evento dragstart nativo do HTML5
 * Usado principalmente como fallback e para compatibilidade
 */
const handleDragStart = (e: DragEvent) => {
    // Previne o comportamento padrão de drag para evitar conflitos
    e.preventDefault();
    e.stopPropagation();
};

/**
 * Handler para o evento dragend nativo do HTML5
 * Reseta os estados de arrasto
 */
const handleDragEnd = () => {
    isResizing.value = false;
};

/**
 * Lógica principal de redimensionamento que atualiza a largura da prateleira
 * É chamada continuamente durante o movimento do mouse
 */
const handleMouseMove = (e: MouseEvent) => {
    // Verifica se estamos em estado de redimensionamento
    if (!isResizing.value) return;

    // Calcula a diferença de movimento do mouse
    const deltaX = e.clientX - initialMouseX.value;

    // Calcula a nova largura baseada na largura inicial e na diferença de movimento
    let newWidth = initialWidth.value + deltaX / props.scaleFactor;

    // Aplica limites mínimo e máximo à nova largura
    newWidth = Math.max(props.minWidth, Math.min(newWidth, effectiveMaxWidth.value));

    // Arredonda para o inteiro mais próximo para evitar valores fracionários
    newWidth = Math.round(newWidth);

    // Atualiza a largura da prateleira no store
    // O parâmetro false indica que não queremos persistir a alteração no servidor imediatamente
    gondolaStore.updateShelf(
        props.shelf.id,
        {
            shelf_width: newWidth,
        },
        false,
    );
};

/**
 * Finaliza o redimensionamento quando o mouse é solto
 * Persiste a alteração final e remove os event listeners
 */
const handleMouseUp = () => {
    if (isResizing.value) {
        // Persiste a largura final no servidor
        gondolaStore.updateShelf(props.shelf.id, {
            shelf_width: props.shelf.shelf_width,
        });

        console.log('Finalizando redimensionamento: nova largura =', props.shelf.shelf_width);
    }

    // Reseta o estado de redimensionamento
    isResizing.value = false;

    // Remove os event listeners temporários
    document.removeEventListener('mousemove', handleMouseMove);
    document.removeEventListener('mouseup', handleMouseUp);
};
</script>

<style scoped>
/* Efeito de hover para melhor feedback visual */
div:hover {
    background-color: rgba(59, 130, 246, 0.1);
}
</style>
