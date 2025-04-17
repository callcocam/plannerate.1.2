<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import Gondolas from './gondolas/Gondolas.vue'; 
import Products from './partials/sidebar/Products.vue';
import Properties from './partials/sidebar/Properties.vue'; 
import PlannerateHeader from './partials/Header.vue';

const props = defineProps({
    record: {
        type: Object,
        default: () => null,
    },
});

const router = useRouter();
 

const record = ref<any>(props.record); // Substitua 'any' pelo tipo correto, se possível

const gondolas = computed(() => {
    return record.value.gondolas || [];
});
onMounted(() => {
    // Verifica se o ID da gôndola está presente na URL
    if (!gondolas.value.length) {
        router.push({
            name: 'plannerate.home',
            params: {
                id: record.value.id,
            },
        });
    }
});
</script>

<template>
    <div class="px-10" v-if="record">
        <PlannerateHeader :planogram="record" />
        <div>
            <div class="flex h-full w-full gap-6 overflow-hidden">
                <!-- Barra lateral esquerda com componente Products separado -->
                <Products />
                <!-- Área central rolável (vertical e horizontal) -->
                <div class="flex h-full w-full flex-col gap-6 overflow-x-auto overflow-y-auto"> 
                    <Gondolas :record="record"/>
                </div>
                <Properties />
            </div>
        </div>
    </div>
</template>
