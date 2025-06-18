<template>
    <div> 
        <div class="space-y-2"> 
            <!-- Mapeamento da Loja -->
            <div v-if="mapData" class="space-y-2">
                <StoreMapList
                    :map-data="mapData"
                    :allow-selection="true"
                    :show-category-filter="false"
                    :selected-gondola-id="selectedMapGondolaId" 
                    @gondola-selected="onMapGondolaSelected"
                    @gondola-clicked="onMapGondolaClicked"
                    />
                <!-- Informações da Gôndola Selecionada -->
                <div v-if="selectedMapGondola" class="bg-green-50 border border-green-200 rounded-lg p-3 dark:bg-green-900/20 dark:border-green-700 absolute">
                    <div class="flex items-center justify-between">
                        <div>
                            <h5 class="text-sm font-medium text-green-900 dark:text-green-100">
                                Gôndola Selecionada no Mapa
                            </h5>
                            <p class="text-xs text-green-700 dark:text-green-300">
                                G{{ getMapGondolaIndex() }} - Categoria {{ selectedMapGondola.category.toUpperCase() }}
                            </p>
                        </div>
                        <button
                            type="button"
                            @click="linkGondolaToMap"
                            class="px-3 py-1 bg-green-600 text-white text-xs rounded hover:bg-green-700 transition-colors"
                        >
                            Vincular Gôndola
                        </button>
                    </div>
                </div>

                <!-- Gôndola Já Vinculada -->
                <div v-if="formData.linkedMapGondolaId" class="bg-blue-50 border border-blue-200 rounded-lg p-3 dark:bg-blue-900/20 dark:border-blue-700">
                    <div class="flex items-center justify-between">
                        <div>
                            <h5 class="text-sm font-medium text-blue-900 dark:text-blue-100">
                                Gôndola Vinculada
                            </h5>
                            <p class="text-xs text-blue-700 dark:text-blue-300">
                                Esta gôndola está vinculada à gôndola {{ getLinkedGondolaName() }} do mapa
                            </p>
                        </div>
                        <button
                            type="button"
                            @click="unlinkGondolaFromMap"
                            class="px-3 py-1 bg-red-600 text-white text-xs rounded hover:bg-red-700 transition-colors"
                        >
                            Desvincular
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts"> 
import { defineProps, ref, computed } from 'vue';
import StoreMapList from '@/components/form/fields/StoreMapList.vue';

// Define Props
const props = defineProps({
    formData: {
        type: Object as () => Record<string, any>,
        required: true,
    } 
});  

// Tentar diferentes estruturas de dados
const mapData = computed(() => {
    const data = props.formData.storeData?.store_map_data;
    if (!data) return null;
    
    return {
        imageUrl: data.imageUrl || data.image_url || data.image_uri || null,
        imageWidth: data.imageWidth || data.image_width || null,
        imageHeight: data.imageHeight || data.image_height || null,
        gondolas: data.gondolas || data.gondolas_data || []
    };
}); 

// Define Emits
const emit = defineEmits<{
    'update:formData': [value: Record<string, any>]
}>();

// Refs
const selectedMapGondolaId = ref<string | null>(props.formData.linkedMapGondolaId || null);
const selectedMapGondola = ref<any>(null);
 

// Computed
const getMapGondolaIndex = () => {
    if (!selectedMapGondola.value || !mapData.value?.gondolas) return 0;
    return mapData.value.gondolas.findIndex((g: any) => g.id === selectedMapGondola.value.id) + 1;
};

const getLinkedGondolaName = () => {
    if (!props.formData.linkedMapGondolaId || !mapData.value?.gondolas) return 'N/A';
    const gondola = mapData.value.gondolas.find((g: any) => g.id === props.formData.linkedMapGondolaId);
    if (!gondola) return 'N/A';
    const index = mapData.value.gondolas.findIndex((g: any) => g.id === gondola.id) + 1;
    return `G${index} - ${gondola.category.toUpperCase()}`;
};

// Methods
const onMapGondolaSelected = (gondolaId: string | null) => {
    selectedMapGondolaId.value = gondolaId;
    if (gondolaId && mapData.value?.gondolas) {
        selectedMapGondola.value = mapData.value.gondolas.find((g: any) => g.id === gondolaId);
    } else {
        selectedMapGondola.value = null;
    }
};

const onMapGondolaClicked = (gondola: any) => { 
    selectedMapGondola.value = gondola;
    selectedMapGondolaId.value = gondola?.id || null;
}; 

const linkGondolaToMap = () => {
    if (selectedMapGondola.value) {
        const updatedFormData = {
            ...props.formData,
            linked_map_gondola_id: selectedMapGondola.value.id,
            linkedMapGondolaCategory: selectedMapGondola.value.category,
        };
        emit('update:formData', updatedFormData);
    }
};

const unlinkGondolaFromMap = () => {
    const updatedFormData = {
        ...props.formData,
        linkedMapGondolaId: null,
        linkedMapGondolaCategory: null,
    };
    emit('update:formData', updatedFormData);
    selectedMapGondolaId.value = null;
    selectedMapGondola.value = null;
};
</script>
