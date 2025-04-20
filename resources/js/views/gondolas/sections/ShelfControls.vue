<template>
    <div class="shelf-controls" @mouseenter="isHovering = true" @mouseleave="isHovering = false">
        <!-- Área central para movimento vertical da prateleira -->
        <div class="absolute inset-0 z-10 flex h-full w-full cursor-ns-resize items-center justify-center"
            @mousedown="handleVerticalDragStart"></div>
        <transition name="fade">
            <!-- Botão para mover horizontalmente para a esquerda -->
            <div v-show="isHovering"
                class="absolute left-0 top-0 z-20 flex h-full w-5 cursor-e-resize items-center justify-center bg-blue-500 hover:bg-blue-600"
                @mousedown="(e) => handleHorizontalDragStart(e, 'left')">
                <ChevronLeftIcon class="h-4 w-4 text-white" />
            </div>
        </transition>

        <transition name="fade">
            <!-- Botão para mover horizontalmente para a direita -->
            <div v-show="isHovering"
                class="absolute right-0 top-0 z-20 flex h-full w-5 cursor-e-resize items-center justify-center bg-blue-500 hover:bg-blue-600"
                @mousedown="(e) => handleHorizontalDragStart(e, 'right')">
                <ChevronRightIcon class="h-4 w-4 text-white" />
            </div>
        </transition>
    </div>
</template>

<script setup lang="ts">
import { ChevronLeftIcon, ChevronRightIcon } from 'lucide-vue-next';
import {  onMounted, ref, computed } from 'vue';
import { useEditorStore } from '@plannerate/store/editor';
import { Shelf } from '@plannerate/types/shelves'; 
/**
 * Props do componente
 */
const props = defineProps<{
    shelf: Shelf ;
    scaleFactor: number;
    sectionWidth: number;
    sectionHeight: number;
    baseHeight: number;
    shelfElement?: HTMLElement | null; // Referência ao elemento da prateleira
    sectionsContainer: HTMLElement | null; // Referência ao container das seções
    holeWidth: number;
}>();

// Emits para comunicar com componentes pai
const emit = defineEmits(['transfer-section']);

// Store para interagir com o estado global das gôndolas 
const editorStore = useEditorStore();

// Estado para controlar a visibilidade dos botões
const isHovering = ref(false);

// Valores computados para melhorar performance
const currentSectionId = computed(() => props.shelf.section_id);
const shelfId = computed(() => props.shelf.id);
const correctGondolaId = ref('');

// Estado para controlar o modo de manipulação (arrasto vs clique)
const isDragModeActive = ref(false);

// Estados para controle da manipulação
const isDragging = ref(false);
const dragType = ref<'vertical' | 'horizontal' | null>(null);
const dragDirection = ref<'left' | 'right' | null>(null);
const currentVisualXOffset = ref(0);

// Valores iniciais para cálculos de movimento
const initialMouseX = ref(0);
const initialMouseY = ref(0);
const initialShelfX = ref(0);
const initialShelfY = ref(0);
const initialShelfRect = ref<DOMRect | null>(null);

// Estado para rastrear a seção alvo potencial
const potentialTargetSectionId = ref<string | null>(null);
// Estado para feedback visual na seção alvo
const targetSectionElement = ref<HTMLElement | null>(null);

// Configurações de limites de movimento
const MOVEMENT_THRESHOLD = 5;
const hasExceededThreshold = ref(false);

// Parâmetros para otimização
const THROTTLE_DELAY = 16; // Para 60fps (~16.7ms por frame)
const RAF_THROTTLE = ref(false);
const lastRafTime = ref(0);
const relativeY = ref(false);

// Estado para acompanhar retângulos de seção (para cálculos de colisão)
const sectionRects = ref<Map<string, { element: HTMLElement, rect: DOMRect }>>(new Map());

/**
 * Cache das seções no início do arrasto para evitar múltiplas consultas
 */
const cacheTargetSections = () => {
    sectionRects.value.clear();
    if (!props.sectionsContainer) return;

    const sections = props.sectionsContainer.querySelectorAll('[data-section-id]');
    sections.forEach((section) => {
        const sectionEl = section as HTMLElement;
        const sectionId = sectionEl.dataset.sectionId;
        if (!sectionId || sectionId === currentSectionId.value) return;

        sectionRects.value.set(sectionId, {
            element: sectionEl,
            rect: sectionEl.getBoundingClientRect()
        });
    });
};

/**
 * Inicia o arrasto vertical
 */
const handleVerticalDragStart = (e: MouseEvent) => {
    if (isDragModeActive.value) return;

    isDragging.value = true;
    dragType.value = 'vertical';
    hasExceededThreshold.value = false;

    initialMouseY.value = e.clientY;
    initialShelfY.value = props.shelf.shelf_position || 0;

    // Cache o shelfRect para evitar recálculos
    if (props.shelfElement) {
        initialShelfRect.value = props.shelfElement.getBoundingClientRect();
    }

    // Adicionar listeners usando capture para melhorar a resposta
    document.addEventListener('mousemove', handleMouseMove, { passive: true });
    document.addEventListener('mouseup', handleMouseUp);

    e.preventDefault();
};

/**
 * Inicia o arrasto horizontal com otimizações
 */
const handleHorizontalDragStart = (e: MouseEvent, direction: 'left' | 'right') => {
    if (isDragging.value) return;

    isDragging.value = true;
    dragType.value = 'horizontal';
    dragDirection.value = direction;
    hasExceededThreshold.value = false;
    initialMouseX.value = e.clientX;
    initialShelfX.value = props.shelf.shelf_x_position || 0;
    currentVisualXOffset.value = 0;

    // Cache o shelfRect para evitar recálculos
    if (props.shelfElement) {
        initialShelfRect.value = props.shelfElement.getBoundingClientRect();
    }

    // Pre-cache todas as seções alvo para colisão
    cacheTargetSections();

    // Gerenciamento de Listeners
    document.addEventListener('mousemove', handleMouseMove, { passive: true });
    document.addEventListener('mouseup', handleMouseUp);

    e.preventDefault();
    e.stopPropagation();
};

/**
 * Handler otimizado para movimento do mouse usando requestAnimationFrame
 */
const handleMouseMove = (e: MouseEvent) => {
    if (!isDragging.value) return;

    // Verificação do threshold (executa sempre)
    if (!hasExceededThreshold.value) {
        const deltaX = Math.abs(e.clientX - initialMouseX.value);
        const deltaY = Math.abs(e.clientY - initialMouseY.value);
        if ((dragType.value === 'vertical' && deltaY >= MOVEMENT_THRESHOLD) ||
            (dragType.value === 'horizontal' && deltaX >= MOVEMENT_THRESHOLD)) {
            hasExceededThreshold.value = true;
        }
    }
    if (!hasExceededThreshold.value) return;

    // Armazena os valores atuais do mouse para uso no próximo frame
    const mouseX = e.clientX;
    const mouseY = e.clientY;

    // Throttle usando requestAnimationFrame para sincronizar com a taxa de atualização da tela
    if (!RAF_THROTTLE.value) {
        RAF_THROTTLE.value = true;
        requestAnimationFrame(() => {
            const now = performance.now();
            // Limita a taxa de atualização para evitar operações excessivas
            if (now - lastRafTime.value > THROTTLE_DELAY) {
                lastRafTime.value = now;

                if (dragType.value === 'vertical') {
                    handleVerticalMoveRaf(mouseY);
                } else if (dragType.value === 'horizontal') {
                    handleHorizontalMoveRaf(mouseX);
                }
            }

            RAF_THROTTLE.value = false;
        });
    }
};

/**
 * Lida com o movimento vertical otimizado via RAF
 */
const handleVerticalMoveRaf = (clientY: number) => {
    if (!props.shelfElement) return;
    const containerRect = props.shelfElement.parentElement?.parentElement?.getBoundingClientRect();
    if (!containerRect) return;

    const deltaY = clientY - initialMouseY.value;
    const newPosition = initialShelfY.value + (deltaY / props.scaleFactor);
    // Limites de movimento
    const maxYPosition = props.sectionHeight * props.scaleFactor - props.baseHeight - props.shelf.shelf_height;
    relativeY.value = clientY - containerRect.top >= maxYPosition;
    const clampedPosition = Math.max(0, Math.min(newPosition, maxYPosition));
    if (relativeY.value) return;
    // Atualiza apenas a visualização; persistência só acontece no mouse up
    // Use transform para melhor performance ao invés de top
    props.shelfElement.style.transform = `translateY(${(clampedPosition - initialShelfY.value) * props.scaleFactor}px)`;
};

/**
 * Lida com o movimento horizontal otimizado via RAF
 */
const handleHorizontalMoveRaf = (clientX: number) => {
    if (!props.shelfElement || !initialShelfRect.value) return;

    const deltaX = clientX - initialMouseX.value;
    currentVisualXOffset.value = deltaX;

    // Usa transform para melhor performance
    props.shelfElement.style.transform = `translateX(${deltaX}px)`;

    // Recalcula a posição atual da prateleira baseada no movimento
    const currentRect = new DOMRect(
        initialShelfRect.value.x + deltaX,
        initialShelfRect.value.y,
        initialShelfRect.value.width,
        initialShelfRect.value.height
    );

    // Reset target highlight
    if (targetSectionElement.value) {
        targetSectionElement.value.classList.remove('section-drop-target-highlight');
        targetSectionElement.value = null;
    }

    // Detecção de colisão otimizada usando o cache
    let bestOverlap = 0;
    let bestTargetId = null;
    let bestTargetElement = null;

    // Verifica todas as seções pre-cacheadas
    sectionRects.value.forEach((data, sectionId) => {
        const targetRect = data.rect;

        // Cálculo de sobreposição
        const overlapLeft = Math.max(currentRect.left, targetRect.left);
        const overlapRight = Math.min(currentRect.right, targetRect.right);
        const overlapWidth = Math.max(0, overlapRight - overlapLeft);

        // Se há sobreposição suficiente, considera como potencial alvo
        if (overlapWidth > currentRect.width * 0.1 && overlapWidth > bestOverlap) {
            bestOverlap = overlapWidth;
            bestTargetId = sectionId;
            bestTargetElement = data.element;
        }
    });

    // Atualiza o alvo se encontrou um
    if (bestTargetId && bestTargetElement) {
        potentialTargetSectionId.value = bestTargetId;
        targetSectionElement.value = bestTargetElement;
        (bestTargetElement as HTMLElement).classList.add('section-drop-target-highlight');
    } else {
        potentialTargetSectionId.value = null;
    }

    // Cursor de arrasto
    document.body.style.cursor = 'e-resize';
};

/**
 * Finaliza o arrasto com uma única chamada à store
 */
const handleMouseUp = (e?: MouseEvent) => {
    // Encontra o ID da gôndola correta
    let gondolaId: string | null = correctGondolaId.value || null;
    if (!gondolaId && editorStore.currentState?.gondolas) {
        for (const gondola of editorStore.currentState.gondolas) {
            if (gondola.sections.some(section => section.id === currentSectionId.value)) {
                gondolaId = gondola.id;
                break;
            }
        }
    }

    // Reset visual
    if (targetSectionElement.value) {
        targetSectionElement.value.classList.remove('section-drop-target-highlight');
    }

    if (props.shelfElement) {
        props.shelfElement.style.transform = '';
    }

    currentVisualXOffset.value = 0;
    targetSectionElement.value = null;

    // Ação Final - Apenas se o arrasto foi significativo
    if (isDragging.value && hasExceededThreshold.value && gondolaId) {
        if (dragType.value === 'horizontal') {
            const targetSectionId = potentialTargetSectionId.value;
            if (targetSectionId && targetSectionId !== currentSectionId.value) {
                // Transferência entre seções
                editorStore.transferShelfBetweenSections(
                    gondolaId,
                    currentSectionId.value,
                    targetSectionId,
                    shelfId.value
                );
            } else if (props.shelf.shelf_x_position !== 0) {
                // Reset da posição X se não houver transferência
                const holeWidth = props.holeWidth || 0;
                editorStore.updateShelfData(
                    gondolaId,
                    currentSectionId.value,
                    shelfId.value,
                    { shelf_x_position: holeWidth * props.scaleFactor }
                );
            }
        } else if (dragType.value === 'vertical' && e) {
            // Calcula a posição final apenas uma vez
            const finalDeltaY = e.clientY - initialMouseY.value;
            const newPositionCm = initialShelfY.value + (finalDeltaY / props.scaleFactor);

            // Limites
            const maxYPosition = props.sectionHeight * props.scaleFactor - props.baseHeight - props.shelf.shelf_height;
            const clampedPositionCm = Math.max(0, Math.min(newPositionCm, maxYPosition));

            // Atualiza apenas se houver mudança significativa
            if (Math.abs(clampedPositionCm - initialShelfY.value) > 0.01 && !relativeY.value) {
                const holeWidth = props.holeWidth || 0;
                console.log("holeWidth", holeWidth);
                editorStore.setShelfPosition(
                    gondolaId,
                    currentSectionId.value,
                    shelfId.value,
                    {
                        shelf_position: clampedPositionCm,
                        shelf_x_position: holeWidth * props.scaleFactor
                    }
                );
            }
        }
    }

    // Limpa o estado de arrasto
    isDragging.value = false;
    dragType.value = null;
    dragDirection.value = null;
    hasExceededThreshold.value = false;
    potentialTargetSectionId.value = null;
    initialShelfRect.value = null;
    document.body.style.cursor = '';

    // Limpa o cache de seções
    sectionRects.value.clear();

    // Remove listeners
    document.removeEventListener('mousemove', handleMouseMove);
    document.removeEventListener('mouseup', handleMouseUp);
};

onMounted(() => {
    // Encontra a gôndola correta no carregamento
    if (editorStore.currentState?.gondolas) {
        for (const gondola of editorStore.currentState.gondolas) {
            if (gondola.sections.some(section => section.id === currentSectionId.value)) {
                correctGondolaId.value = gondola.id;
                break;
            }
        }
    }
});
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

/* Estilo para destacar a seção alvo durante o arraste - adicionado will-change para otimização */
:global(.section-drop-target-highlight) {
    outline: 2px dashed blue;
    outline-offset: -2px;
    background-color: rgba(0, 0, 255, 0.05);
    transition:
        outline 0.1s ease-in-out,
        background-color 0.1s ease-in-out;
    will-change: outline, background-color;
}
</style>