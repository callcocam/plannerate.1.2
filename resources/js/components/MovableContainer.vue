<template>
    <div class="movable-container" ref="containerRef">
        <!-- Todo o componente movable-content torna-se arrastável -->
        <div
            class="movable-content flex"
            :style="contentStyle"
            :class="{ 'is-dragging': isDragging }"
            @mousedown="onComponentMouseDown"
            @touchstart="onComponentTouchStart"
        >
            <!-- A alça de arrasto ainda é mantida para indicação visual -->

            <!-- Slot para o conteúdo original (seu SectionList) -->
            <div class="relative flex max-w-full border-b4 border-gray-500 dark:border-gray-500 items-center justify-center">
                <div
                    title="Arraste para mover"
                    class="drag-handle flex items-center justify-center bg-primary text-primary-foreground shadow-md hover:bg-primary/90 dark:bg-gray-600 dark:text-gray-100 dark:hover:bg-gray-500 dark:shadow-gray-900/30"
                    :class="{ 'bg-primary/90': isDragging, 'dark:bg-gray-500': isDragging }"
                >
                    <span class="sr-only">Mover Gôndola</span>
                    <Move class="h-5 w-5" />
                </div>
                <div class="content-slot mb-5">
                    <slot></slot>
                </div>
                <!-- Botão para centralizar também acoplado ao conteúdo -->
                <button
                    class="center-button flex items-center justify-center rounded-full border border-border bg-card text-muted-foreground shadow-sm transition-all duration-200 hover:bg-accent hover:text-accent-foreground dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600 dark:hover:text-gray-200"
                    @click.stop="resetPosition"
                    title="Centralizar Gôndola"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        width="16"
                        height="16"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <polyline points="15 3 21 3 21 9"></polyline>
                        <polyline points="9 21 3 21 3 15"></polyline>
                        <line x1="21" y1="3" x2="14" y2="10"></line>
                        <line x1="3" y1="21" x2="10" y2="14"></line>
                    </svg>
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Move } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    // ID opcional para salvar a posição
    storageId: {
        type: String,
        default: 'default-content',
    }, 
});

// Referências ao DOM e estado
const containerRef = ref(null);
const position = ref({ x: 0, y: 0 });
const isDragging = ref(false);

// Estado do arrasto
const dragState = ref({
    startX: 0,
    startY: 0,
    startPosX: 0,
    startPosY: 0,
    active: false,
});

// Estilo computado para o conteúdo
const contentStyle = computed(() => ({
    transform: `translate(${position.value.x}px, ${position.value.y}px)`,
}));

// Função para verificar se o clique foi dentro do content-slot
function isInsideContentSlot(event) {
    const contentSlot = document.querySelector('.content-slot');
    if (!contentSlot) return false;

    // Obtém as coordenadas do clique
    const x = event.clientX || (event.touches ? event.touches[0].clientX : 0);
    const y = event.clientY || (event.touches ? event.touches[0].clientY : 0);

    // Obtém a posição do elemento content-slot
    const rect = contentSlot.getBoundingClientRect();

    // Verifica se o clique foi dentro da área do content-slot
    return x >= rect.left && x <= rect.right && y >= rect.top && y <= rect.bottom;
}

// Manipulador de eventos para mouse down no componente
function onComponentMouseDown(event) {
    // Se o clique foi dentro do content-slot, não inicia o arrasto
    if (isInsideContentSlot(event)) {
        return;
    }

    // Caso contrário, inicia o arrasto
    startDrag(event);
}

// Manipulador de eventos para touch start no componente
function onComponentTouchStart(event) {
    // Se o toque foi dentro do content-slot, não inicia o arrasto
    if (isInsideContentSlot(event)) {
        return;
    }

    // Caso contrário, inicia o arrasto
    startDrag(event);
}

// Iniciar o arrasto
function startDrag(event) {
    // Prevenir comportamento padrão
    event.preventDefault();

    // Capturar posição inicial do mouse/toque
    const clientX = event.touches ? event.touches[0].clientX : event.clientX;
    const clientY = event.touches ? event.touches[0].clientY : event.clientY;

    // Configurar estado de arrasto
    dragState.value = {
        startX: clientX,
        startY: clientY,
        startPosX: position.value.x,
        startPosY: position.value.y,
        active: true,
    };

    isDragging.value = true;

    // Adicionar listeners de eventos
    if (event.touches) {
        document.addEventListener('touchmove', onDrag);
        document.addEventListener('touchend', stopDrag);
    } else {
        document.addEventListener('mousemove', onDrag);
        document.addEventListener('mouseup', stopDrag);
    }
}

// Processar o movimento durante o arrasto
function onDrag(event) {
    if (!dragState.value.active) return;

    event.preventDefault();

    const clientX = event.touches ? event.touches[0].clientX : event.clientX;
    const clientY = event.touches ? event.touches[0].clientY : event.clientY;

    // Calcular deslocamento
    const dx = clientX - dragState.value.startX;
    const dy = clientY - dragState.value.startY;

    // Atualizar posição
    position.value = {
        x: dragState.value.startPosX + dx,
        y: dragState.value.startPosY + dy,
    };
}

// Finalizar o arrasto
function stopDrag() {
    isDragging.value = false;
    dragState.value.active = false;

    // Remover listeners de eventos
    document.removeEventListener('mousemove', onDrag);
    document.removeEventListener('mouseup', stopDrag);
    document.removeEventListener('touchmove', onDrag);
    document.removeEventListener('touchend', stopDrag);

    // Salvar posição
    savePosition();
}

// Centralizar o conteúdo
function resetPosition() {
    position.value = { x: 0, y: 0 };
    savePosition();
}

// Salvar posição no localStorage
function savePosition() {
    try {
        localStorage.setItem(`movable-content-${props.storageId}`, JSON.stringify(position.value));
    } catch (error) {
        console.error('Erro ao salvar posição:', error);
    }
}

// Carregar posição salva
function loadPosition() {
    try {
        const savedPos = localStorage.getItem(`movable-content-${props.storageId}`);
        if (savedPos) {
            position.value = JSON.parse(savedPos);
        }
    } catch (error) {
        console.error('Erro ao carregar posição:', error);
    }
}

// Verificar se o conteúdo está visível após redimensionamento
function checkVisibility() {
    if (!containerRef.value) return;

    const container = containerRef.value.getBoundingClientRect();
    const content = containerRef.value.querySelector('.movable-content')?.getBoundingClientRect();

    if (!content) return;

    // Se estiver totalmente fora da área visível, centralizar
    if (content.right < container.left || content.left > container.right || content.bottom < container.top || content.top > container.bottom) {
        resetPosition();
    }
}

// Ciclo de vida do componente
onMounted(() => {
    loadPosition();
    window.addEventListener('resize', checkVisibility);
});

onBeforeUnmount(() => {
    document.removeEventListener('mousemove', onDrag);
    document.removeEventListener('mouseup', stopDrag);
    document.removeEventListener('touchmove', onDrag);
    document.removeEventListener('touchend', stopDrag);
    window.removeEventListener('resize', checkVisibility);
});
</script>

<style scoped>
.movable-container {
    position: relative;
    height: 100vh; /* Altura total da tela */
    overflow: visible;
    width: 100%;
}

.movable-content {
    position: relative;
    transition: transform 0.1s ease;
    will-change: transform;
    cursor: move; /* Cursor de movimento em toda a área */
}

.movable-content.is-dragging {
    transition: none;
}

.content-slot {
    position: relative;
    cursor: default; /* Mantém o cursor padrão para o conteúdo interno */
    z-index: 5; /* Garante que o conteúdo fique "acima" para interação */
    display: flex;
    justify-content: center;
    align-items: center;
}

/* Estilo para a alça de arrasto */
.drag-handle {
    position: absolute;
    top: 50%;
    left: 0;
    transform: translateX(-100%);
    padding: 8px;
    border-radius: 9999px;
    cursor: move;
    z-index: 100;
    user-select: none;
    touch-action: none;
    transition: all 0.2s ease;
    width: 36px;
    height: 36px;
}

/* Estilo para o botão de centralizar */
.center-button {
    position: absolute;
    top: 15%;
    right: 20px;
    transform: translateY(-100%); 
    width: 36px;
    height: 36px;
    z-index: 100;
    cursor: pointer; /* Garante cursor de ponteiro para o botão */
}

/* Responsividade para telas menores */
@media (max-width: 640px) {
    .drag-handle {
        width: 32px;
        height: 32px;
        padding: 6px;
    }

    .center-button {
        width: 32px;
        height: 32px;
    }
}

/* Estilos para o modo escuro via CSS */
@media (prefers-color-scheme: dark) {
   
}
</style>
