<template>
    <div class="shelf-controls" @mouseenter="isHovering = true" @mouseleave="isHovering = false">
        <!-- Área central para movimento vertical da prateleira -->
        <div class="absolute inset-0 z-10 flex h-full w-full cursor-ns-resize items-center justify-center" @mousedown="handleVerticalDragStart"></div>
        <transition name="fade">
            <!-- Botão para mover horizontalmente para a esquerda -->
            <div
                v-show="isHovering"
                class="absolute left-0 top-0 z-20 flex h-full w-5 cursor-e-resize items-center justify-center bg-blue-500 hover:bg-blue-600"
                @mousedown="(e) => handleHorizontalDragStart(e, 'left')"
            >
                <ChevronLeftIcon class="h-4 w-4 text-white" />
            </div>
        </transition>

        <transition name="fade">
            <!-- Botão para mover horizontalmente para a direita -->
            <div
                v-show="isHovering"
                class="absolute right-0 top-0 z-20 flex h-full w-5 cursor-e-resize items-center justify-center bg-blue-500 hover:bg-blue-600"
                @mousedown="(e) => handleHorizontalDragStart(e, 'right')"
            >
                <ChevronRightIcon class="h-4 w-4 text-white" />
            </div>
        </transition>
    </div>
</template>

<script setup lang="ts">
import { ChevronLeftIcon, ChevronRightIcon } from 'lucide-vue-next';
import { nextTick, ref } from 'vue';
import { useGondolaStore } from '../../../store/gondola';
import { Shelf } from './types';
import { useShelvesStore } from '../../../store/shelves';

/**
 * Props do componente
 */
const props = defineProps<{
    shelf: Shelf;
    scaleFactor: number;
    sectionWidth: number;
    sectionHeight: number;
    baseHeight: number;
    shelfElement?: HTMLElement | null; // Referência ao elemento da prateleira
    sectionsContainer: HTMLElement | null; // Referência ao container das seções
}>();

// Emits para comunicar com componentes pai
const emit = defineEmits(['transfer-section']);

// Store para interagir com o estado global das gôndolas
const gondolaStore = useGondolaStore();
const shelvesStore = useShelvesStore();

// Estado para controlar a visibilidade dos botões
const isHovering = ref(false);

// Estado para controlar o modo de manipulação (arrasto vs clique)
const isDragModeActive = ref(false);

// Estados para controle da manipulação
const isDragging = ref(false);
const dragType = ref<'vertical' | 'horizontal' | null>(null);
const dragDirection = ref<'left' | 'right' | null>(null);

// Valores iniciais para cálculos de movimento
const initialMouseX = ref(0);
const initialMouseY = ref(0);
const initialShelfX = ref(0);
const initialShelfY = ref(0);

// Estado para rastrear a seção alvo potencial
const potentialTargetSectionId = ref<string | null>(null);
// Estado para feedback visual na seção alvo
const targetSectionElement = ref<HTMLElement | null>(null);

// Configurações de limites de movimento
const MOVEMENT_THRESHOLD = 5; // Pixels mínimos para considerar um movimento real
const SHELF_SNAP_GRID = 20; // Valor em cm para o "snap to grid" das prateleiras (múltiplos de 20cm)
const SHELF_SNAP_TOLERANCE = 3; // Tolerância em cm para considerar uma posição como válida (reduzida)
const hasExceededThreshold = ref(false);

/**
 * Inicia o arrasto vertical
 */
const handleVerticalDragStart = (e: MouseEvent) => {
    if (isDragModeActive.value) return; // Não permitir arrasto vertical no modo de arrasto horizontal

    isDragging.value = true;
    dragType.value = 'vertical';
    hasExceededThreshold.value = false;

    initialMouseY.value = e.clientY;
    initialShelfY.value = props.shelf.shelf_position || 0;

    console.log('Iniciando arrasto vertical', initialMouseY.value, initialShelfY.value);

    document.addEventListener('mousemove', handleMouseMove);
    document.addEventListener('mouseup', handleMouseUp);

    e.preventDefault();
};

/**
 * Inicia o arrasto horizontal
 */
const handleHorizontalDragStart = (e: MouseEvent, direction: 'left' | 'right') => {
    isDragging.value = true;
    dragType.value = 'horizontal';
    dragDirection.value = direction;
    hasExceededThreshold.value = false;

    initialMouseX.value = e.clientX;
    initialShelfX.value = props.shelf.shelf_x_position || 0;

    document.addEventListener('mousemove', handleMouseMove);
    document.addEventListener('mouseup', handleMouseUp);

    e.preventDefault();
    e.stopPropagation();
};

/**
 * Handler para o movimento do mouse durante arrasto
 */
const handleMouseMove = (e: MouseEvent) => {
    if (!isDragging.value) return; 
    // Verifica se o movimento excedeu o limiar mínimo
    if (!hasExceededThreshold.value) {
        if (dragType.value === 'vertical') {
            const deltaY = Math.abs(e.clientY - initialMouseY.value);
            if (deltaY < MOVEMENT_THRESHOLD) return;
        } else if (dragType.value === 'horizontal') {
            const deltaX = Math.abs(e.clientX - initialMouseX.value);
            if (deltaX < MOVEMENT_THRESHOLD) return;
        }
        // Se chegou aqui, o movimento excedeu o limiar
        hasExceededThreshold.value = true;
    }

    if (dragType.value === 'vertical') {
        handleVerticalMove(e);
    } else if (dragType.value === 'horizontal') {
        handleHorizontalMove(e);
    }
};

/**
 * Lida com o movimento vertical da prateleira
 */
const handleVerticalMove = (e: MouseEvent) => {
    if (!props.shelfElement) return;

    const containerRect = props.shelfElement.parentElement?.getBoundingClientRect();
    if (!containerRect) return;

    const relativeY = e.clientY - containerRect.top;

    if (relativeY < 0 || relativeY > containerRect.height) return;

    const maxYPosition = props.sectionHeight * props.scaleFactor - props.baseHeight - props.shelf.shelf_height;
    if (relativeY >= maxYPosition) return; 
    // Atualiza a posição visual imediatamente (sem persistir)
    shelvesStore.updateShelf(
        props.shelf.id,
        {
            shelf_position: relativeY / props.scaleFactor,
        },
        false,
    );

    // Não é necessário verificar alvos válidos durante o movimento
    // A validação só ocorrerá quando o usuário soltar o mouse
};

/**
 * Lida com o movimento horizontal, detectando sobreposição com outras seções.
 */
const handleHorizontalMove = (e: MouseEvent) => {
    if (!props.sectionsContainer || !props.shelfElement) return;

    const currentSectionElement = props.shelfElement.parentElement;
    if (!currentSectionElement) return;

    const deltaX = e.clientX - initialMouseX.value;
    let newRelativeX = initialShelfX.value + deltaX;

    // Atualiza a posição visual imediatamente (sem persistir)
    shelvesStore.updateShelf(props.shelf.id, { shelf_x_position: newRelativeX }, false);

    // Usar nextTick para garantir que o DOM atualizou com a nova posição visual
    nextTick(() => {
        if (!props.shelfElement) return; // Verifica novamente dentro do nextTick
        const shelfRect = props.shelfElement.getBoundingClientRect();
        let foundTarget = false;
        potentialTargetSectionId.value = null; // Reset antes de checar

        // Remover classe de highlight anterior
        if (targetSectionElement.value) {
            targetSectionElement.value.classList.remove('section-drop-target-highlight');
            targetSectionElement.value = null;
        }

        const sectionElements = props.sectionsContainer?.querySelectorAll('[data-section-id]');

        sectionElements?.forEach((element) => {
            const sectionEl = element as HTMLElement;
            const sectionId = sectionEl.dataset.sectionId;

            // Pula a seção atual
            if (!sectionId || sectionId === props.shelf.section_id) return;

            const targetSectionRect = sectionEl.getBoundingClientRect();

            // Cálculo de sobreposição
            const overlapLeft = Math.max(shelfRect.left, targetSectionRect.left);
            const overlapRight = Math.min(shelfRect.right, targetSectionRect.right);
            const overlapWidth = overlapRight - overlapLeft;

            // Verifica se mais da metade da prateleira está sobre a outra seção
            if (overlapWidth > shelfRect.width * 0.1) {
                potentialTargetSectionId.value = sectionId;
                targetSectionElement.value = sectionEl; // Guarda o elemento para highlight
                sectionEl.classList.add('section-drop-target-highlight'); // Adiciona classe para feedback
                foundTarget = true;
            }
        });

        // Se não encontrou alvo, garante que o ID e o highlight sejam removidos
        if (!foundTarget) {
            potentialTargetSectionId.value = null;
            if (targetSectionElement.value) {
                //@ts-ignore
                targetSectionElement.value.classList.remove('section-drop-target-highlight');
                targetSectionElement.value = null;
            }
        }
    });

    document.body.style.cursor = 'e-resize';
};

/**
 * Finaliza o arrasto e processa transferências entre seções se necessário.
 */
const handleMouseUp = () => {
    // Remover highlight visual ao soltar
    if (targetSectionElement.value) {
        targetSectionElement.value.classList.remove('section-drop-target-highlight');
        targetSectionElement.value = null;
    }

    // Só processa o final do movimento se o limiar foi excedido
    if (isDragging.value && hasExceededThreshold.value) {
        if (dragType.value === 'horizontal') {
            const targetSectionId = potentialTargetSectionId.value;
            const currentShelfData = props.shelf;
            if (targetSectionId && targetSectionId !== currentShelfData.section_id) {
                // ALWAYS set position to 0 relative to the new section
                const newRelativeX = 0;

                // Chamar a nova ação do store para transferência
                shelvesStore.transferShelf(currentShelfData.id, String(currentShelfData.section_id), targetSectionId, newRelativeX);
            } else {
                // Soltou na mesma seção ou fora de uma zona de transferência válida
                shelvesStore.updateShelf(currentShelfData.id, { shelf_x_position: 0 });
            }
        }
    }

    // Resetar estados e cursores
    isDragging.value = false;
    dragType.value = null;
    dragDirection.value = null;
    hasExceededThreshold.value = false;
    potentialTargetSectionId.value = null; // Resetar target ID
    document.body.style.cursor = '';

    // Remover event listeners
    document.removeEventListener('mousemove', handleMouseMove);
    document.removeEventListener('mouseup', handleMouseUp);
};
</script>

<style scoped>
/* Botões de navegação */
.absolute.left-0 {
    border-top-right-radius: 4px;
    border-bottom-right-radius: 4px;
}

.absolute.right-0 {
    border-top-left-radius: 4px;
    border-bottom-left-radius: 4px;
}

/* Animação de fade para os botões */
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

/* Estilo para destacar a seção alvo durante o arraste */
:global(.section-drop-target-highlight) {
    outline: 2px dashed blue;
    outline-offset: -2px;
    /* Para ficar dentro da borda */
    background-color: rgba(0, 0, 255, 0.05);
    transition:
        outline 0.1s ease-in-out,
        background-color 0.1s ease-in-out;
}
</style>
