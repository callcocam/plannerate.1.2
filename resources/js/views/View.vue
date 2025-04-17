<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Gondolas from './gondolas/Gondolas.vue'; 
import Products from './partials/sidebar/Products.vue';
import Properties from './partials/sidebar/Properties.vue'; 
import PlannerateHeader from './partials/Header.vue';

const props = defineProps({
    id: {
        type: String,
        required: true
    },
    gondolaId: {
        type: String,
        required: true
    },
    record: {
        type: Object,
        default: () => null,
    },
});

const router = useRouter();
const route = useRoute();

const planogramData = ref<any>(props.record);
const currentGondolaId = ref(props.gondolaId);

// Monitora mudanças na rota para atualizar o ID da gondola
watch(() => route.params.gondolaId, (newGondolaId) => {
    if (newGondolaId && newGondolaId !== currentGondolaId.value) {
        currentGondolaId.value = newGondolaId as string;
    }
}, { immediate: true });

// Garantir que os dados do planograma permaneçam consistentes
const gondolas = computed(() => {
    return planogramData.value?.gondolas || [];
});
</script>

<template>
    <div class="px-10" v-if="planogramData">
        <PlannerateHeader :planogram="planogramData" />
        <div>
            <div class="flex h-full w-full gap-6 overflow-hidden">
                <!-- Barra lateral esquerda com componente Products separado -->
                <Products />
                <!-- Área central rolável (vertical e horizontal) -->
                <div class="flex h-full w-full flex-col gap-6 overflow-x-auto overflow-y-auto"> 
                    <Gondolas :record="planogramData"/>
                </div>
                <Properties />
            </div>
        </div>
    </div>
</template>
