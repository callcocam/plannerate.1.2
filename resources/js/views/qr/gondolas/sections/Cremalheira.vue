<template>
    <div :style="gramalheiraStyle" class="group relative bg-gray-800 dark:bg-gray-600 z-10 border border-gray-600 dark:border-gray-500" data-cremalheira="true" :data-cremalheira-index="index">
        <!-- Furos na gramalheira -->
        <div v-for="(hole, index) in holes" :key="index" class="absolute bg-gray-300 dark:bg-gray-400 border border-gray-400 dark:border-gray-500" :style="{
            width: `${hole.width * scaleFactor}px`,
            height: `${hole.height * scaleFactor}px`,
            top: `${hole.position * scaleFactor}px`,
            left: `50%`,
            transform: `translateX(-50%)`,
        }"> </div>
        <!-- Base section (without holes) at the bottom -->
        <div class="absolute bottom-0 left-0 w-full bg-gray-800 dark:bg-gray-600 border-t border-gray-600 dark:border-gray-500" :style="{
            height: `${baseHeight * props.scaleFactor}px`,
        }"></div>
    </div>

    <!-- Modal de confirmação -->
    <ConfirmModal :isOpen="showDeleteConfirm" @update:isOpen="showDeleteConfirm = $event" title="Excluir seção"
        message="Tem certeza que deseja excluir esta seção? Esta ação não pode ser desfeita."
        confirmButtonText="Excluir" cancelButtonText="Cancelar" :isDangerous="true" @confirm="confirmDelete"
        @cancel="cancelDelete" />
</template>
<script setup lang="ts">
import { computed, ref } from 'vue';

const props = defineProps({
    section: {
        type: Object,
        required: true,
    },
    scaleFactor: {
        type: Number,
        required: true,
    },
    isLastSection: {
        type: Boolean,
        default: false,
    },
    isFirstSection: {
        type: Boolean,
        default: false,
    },
    index: {
        type: Number,
        default: 0,
    },
});

const emit = defineEmits(['edit-section', 'delete-section']);
 
const baseHeight = computed(() => props.section.base_height || 17);

// Estado para controlar a visibilidade do modal de confirmação
const showDeleteConfirm = ref(false);


function confirmDelete(): void {
    // Emitir evento de exclusão apenas quando confirmado
    emit('delete-section', props.section);
}

function cancelDelete(): void {
    // Apenas fechar o modal
    showDeleteConfirm.value = false;
}

const gramalheiraStyle = computed(() => {
    return {
        width: `${props.section.cremalheira_width * props.scaleFactor}px`,
        height: `${props.section.height * props.scaleFactor}px`,
    };
});

// Função para calcular buracos localmente (mesmo algoritmo do backend)
const calculateHoles = (sectionData: any) => {
    const { height, hole_height, hole_width, hole_spacing, base_height } = sectionData;
    
    // Calcular altura disponível para furos (excluindo a base na parte inferior)
    const availableHeight = height - base_height;
    
    // Calcular quantos furos cabem
    const totalSpaceNeeded = hole_height + hole_spacing;
    const holeCount = Math.floor(availableHeight / totalSpaceNeeded);
    
    // Calcular o espaço restante para distribuir uniformemente
    const remainingSpace = availableHeight - holeCount * hole_height - (holeCount - 1) * hole_spacing;
    const marginTop = remainingSpace / 2; // Começar do topo com margem
    
    const holes = [];
    for (let i = 0; i < holeCount; i++) {
        const holePosition = marginTop + i * (hole_height + hole_spacing);
        holes.push({
            width: hole_width,
            height: hole_height,
            spacing: hole_spacing,
            position: holePosition,
        });
    }
    
    return holes;
};

// Computed para usar buracos recalculados localmente durante edição
const holes = computed(() => {
    // Sempre recalcular com base nos valores atuais da seção
    const sectionData = {
        height: props.section.height,
        hole_height: props.section.hole_height,
        hole_width: props.section.hole_width,
        hole_spacing: props.section.hole_spacing,
        base_height: props.section.base_height,
    };
    
    return calculateHoles(sectionData);
});

</script>
