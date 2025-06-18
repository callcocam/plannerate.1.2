<template>
    <div>
        <div class="space-y-2 ">
            <!-- Mapeamento da Loja -->
            <div v-if="mapData" class="space-y-2">
                <StoreMapList :map-data="mapData" :allow-selection="true" :show-category-filter="false"
                    :selected-gondola-id="selectedMapGondolaId" :gondolas-linked-maps="gondolasLinkedMaps"
                    @gondola-selected="onMapGondolaSelected" @gondola-clicked="onMapGondolaClicked" />
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

const storeMapData = computed(() => {
    return props.formData.storeData?.store_map_data || null;
});
const gondolasLinkedMaps = computed(() => {
    return props.formData.gondolasLinkedMaps || [];
});
// Tentar diferentes estruturas de dados
const mapData = computed(() => {
    const data = storeMapData.value; 
    if (!data) {
        return null;
    }

    const result = {
        imageUrl: data.imageUrl || data.image_url || data.image_uri || null,
        imageWidth: data.imageWidth || data.image_width || null,
        imageHeight: data.imageHeight || data.image_height || null,
        gondolas: data.gondolas || data.gondolas_data || [],
        gondolasLinkedMaps: gondolasLinkedMaps.value || []
    };
    
    return result;
});

// Define Emits
const emit = defineEmits<{
    'update:form': [value: Record<string, any>]
}>();

// Refs
const selectedMapGondolaId = ref<string | null>(props.formData.linkedMapGondolaId || null);
const selectedMapGondola = ref<any>(null);


// Methods
const onMapGondolaSelected = (gondolaId: string | null) => {
    selectedMapGondolaId.value = gondolaId;
    if (gondolaId && storeMapData.value?.gondolas) {
        selectedMapGondola.value = storeMapData.value.gondolas.find((g: any) => g.id === gondolaId);
        linkGondolaToMap();
    } else {
        selectedMapGondola.value = null;
        unlinkGondolaFromMap();
    }
};

const onMapGondolaClicked = (gondola: any) => {
    selectedMapGondola.value = gondola;
    selectedMapGondolaId.value = gondola?.id || null;
    linkGondolaToMap();
};

const linkGondolaToMap = () => {
    if (selectedMapGondola.value) {
        const updatedFormData = {
            ...props.formData,
            linkedMapGondolaId: selectedMapGondola.value.id,
            linkedMapGondolaCategory: selectedMapGondola.value.category,
        };
        emit('update:form', updatedFormData);
    }
};

const unlinkGondolaFromMap = () => {
    const updatedFormData = {
        ...props.formData,
        linkedMapGondolaId: null,
        linkedMapGondolaCategory: null,
    };
    emit('update:form', updatedFormData);
    selectedMapGondolaId.value = null;
    selectedMapGondola.value = null;
};
</script>
