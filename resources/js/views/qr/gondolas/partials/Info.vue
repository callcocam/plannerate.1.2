// Info.vue - Componente Principal (Versão QR Code - Apenas Escala)
<script setup lang="ts">
import { Minus, Plus } from 'lucide-vue-next';
import { computed } from 'vue';
import { useEditorStore } from '@plannerate/store/editor';
import type { Gondola } from '@plannerate/types/gondola';

const props = defineProps({
    gondola: {
        type: Object as () => Gondola | undefined,
        required: false,
    },
    readonly: {
        type: Boolean,
        default: true,
    },
});

const editorStore = useEditorStore();
const scaleFactor = computed(() => editorStore.currentScaleFactor);

const updateScale = (newScale: number) => {
    const clampedScale = Math.max(2, Math.min(10, newScale));
    editorStore.setScaleFactor(clampedScale);
};
</script>

<template>
    <!-- Header QR Code - Apenas Escala -->
    <div class="sticky top-0 z-50 border-b border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="p-2">
            <div class="flex items-center justify-between gap-2">
                <!-- Nome da Gôndola -->
                <div class="flex items-center gap-4">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                        {{ gondola?.name || 'Gôndola' }}
                    </h2>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        📱 Visualização via QR Code
                    </div>
                </div>
                
                <!-- Apenas Controle de Escala -->
                <div class="flex items-center space-x-2">
                    <label class="text-sm text-gray-600 dark:text-gray-400">Escala:</label>
                    <div class="flex items-center space-x-2">
                        <Button type="button" variant="outline" size="sm" :disabled="scaleFactor <= 2"
                            @click="updateScale(scaleFactor - 1)" title="Diminuir escala">
                            <Minus class="h-4 w-4" />
                        </Button>
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ scaleFactor }}x
                        </span>
                        <Button type="button" variant="outline" size="sm" :disabled="scaleFactor >= 10"
                            @click="updateScale(scaleFactor + 1)" title="Aumentar escala">
                            <Plus class="h-4 w-4" />
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
