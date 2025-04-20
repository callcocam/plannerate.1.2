<template>
    <div :style="gramalheiraStyle" class="group  bg-slate-900 dark:bg-gray-700 absolute">
        <!-- Botões que aparecem apenas no hover, posicionados acima da gramalheira em coluna -->
        <div
            v-if="!props.isLastSection"
            class="absolute -top-24 left-1/2 flex -translate-x-1/2 transform flex-col space-y-2 opacity-0 transition-opacity duration-200 group-hover:opacity-100 z-[200]"
        >
            <Button size="sm" class="h-6 w-6 p-0" variant="secondary" @click="$emit('edit-section', section)">
                <PencilIcon class="h-3 w-3" />
            </Button>
            <Button size="sm" class="h-6 w-6 p-0" variant="destructive" @click="openDeleteConfirm">
                <TrashIcon class="h-3 w-3" />
            </Button>
            <slot name="actions" />
        </div>
        <!-- Furos na gramalheira -->
        <div
            v-for="(hole, index) in holes"
            :key="index"
            class="absolute bg-slate-200 dark:bg-gray-300"
            :style="{
                width: `${hole.width * scaleFactor}px`,
                height: `${hole.height * scaleFactor}px`,
                top: `${hole.position * scaleFactor}px`,
                left: `${(gramalheiraWidth - hole.width * scaleFactor) / 2}px`,
            }"
        ></div>

        <!-- Base section (without holes) at the bottom -->
        <div
            class="absolute bottom-0 left-0 w-full bg-slate-900 dark:bg-gray-700"
            :style="{
                height: `${baseHeight * props.scaleFactor}px`,
            }"
        ></div>
    </div>

    <!-- Modal de confirmação -->
    <ConfirmModal
        :isOpen="showDeleteConfirm"
        @update:isOpen="showDeleteConfirm = $event"
        title="Excluir seção"
        message="Tem certeza que deseja excluir esta seção? Esta ação não pode ser desfeita."
        confirmButtonText="Excluir"
        cancelButtonText="Cancelar"
        :isDangerous="true"
        @confirm="confirmDelete"
        @cancel="cancelDelete"
    />
</template>
<script setup lang="ts">
import { PencilIcon, TrashIcon } from 'lucide-vue-next'; 
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
});

const emit = defineEmits(['edit-section', 'delete-section']);

const gramalheiraWidth = computed(() => props.section.cremalheira_width * props.scaleFactor);
const baseHeight = computed(() => props.section.base_height || 17);

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
    console.log("gramalheiraStyle",  props.section.height);
    return {
        width: `${props.section.cremalheira_width * props.scaleFactor}px`,
        height: `${props.section.height * props.scaleFactor}px`,
    };
});

const holes = computed(() => props.section.settings.holes);
</script>
