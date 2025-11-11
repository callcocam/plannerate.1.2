<template>
    <div :style="gramalheiraStyle"
        class="group relative bg-gray-800 dark:bg-gray-600 z-10 border border-gray-600 dark:border-gray-500"
        data-cremalheira="true" :data-cremalheira-index="index">
        <!-- Botões que aparecem apenas no hover, posicionados acima da gramalheira em coluna -->
        <div v-if="!props.isLastSection"
            class="absolute -top-24 left-1/2 flex -translate-x-1/2 transform flex-col space-y-2 opacity-0 transition-opacity duration-200 group-hover:opacity-100 z-[100]">
            <Button size="sm" class="h-6 w-6 p-0 cursor-pointer" variant="secondary"
                @click="$emit('edit-section', section)">
                <PencilIcon class="h-3 w-3" />
            </Button>
            <Button size="sm" class="h-6 w-6 p-0 cursor-pointer" variant="destructive" @click="openDeleteConfirm">
                <TrashIcon class="h-3 w-3" />
            </Button>
            <slot name="actions" />
        </div>
        <!-- Furos na gramalheira -->
        <div v-for="(hole, index) in holes" :key="index"
            class="absolute bg-gray-300 dark:bg-gray-400 border border-gray-400 dark:border-gray-500"
            data-furo-cremalheira="true" :style="{
                width: `${hole.width * scaleFactor}px`,
                height: `${hole.height * scaleFactor}px`,
                top: `${hole.position * scaleFactor}px`,
                left: `50%`,
                transform: `translateX(-50%)`,
            }" @dblclick="addShelfToSection(hole)"> </div>
        <!-- Base section (without holes) at the bottom -->
        <div class="absolute bottom-0 left-0 w-full bg-gray-800 dark:bg-gray-600 border-t border-gray-600 dark:border-gray-500"
            data-base-cremalheira="true" :style="{
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
import { PencilIcon, TrashIcon } from 'lucide-vue-next';
import { useEditorStore } from '@plannerate/store/editor';
import { type Shelf as ShelfType } from '@plannerate/types/shelves';
import { ulid } from 'ulid';

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
const editorStore = useEditorStore()

// Estado para controlar a visibilidade do modal de confirmação
const showDeleteConfirm = ref(false);

// Funções para gerenciar a confirmação de exclusão
function openDeleteConfirm(): void {
    showDeleteConfirm.value = true;
}

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

const addShelfToSection = (hole: any) => {
    const section = props.section
    const shelfHeight = ref(4); // Altura padrão da prateleira ao adicionar
    section.shelves.map((shelf: any) => {
        shelfHeight.value = shelf.shelf_height
    }); 
    editorStore.addShelfToSection(section.gondola_id, section.id, {
        id: ulid(),
        shelf_height: shelfHeight.value,
        shelf_position: hole.position,
        section_id: section.id,
        product_type: 'normal',
    } as ShelfType);

    editorStore.clearLayerSelection(); // Limpa seleção de camadas ao selecionar prateleira    
    editorStore.clearSelectedShelf(); // Limpa seleção de prateleira ao selecionar produto
}
</script>
