<template>
    <div class="px-10">
        <Header v-if="record" :planogram="record" />
        <div   >
            <div class="flex h-full w-full gap-6 overflow-hidden">
                <!-- Barra lateral esquerda com componente Products separado -->
                <Products v-if="gondolas?.length" />
                <!-- Área central rolável (vertical e horizontal) -->
                <div class="flex h-full w-full flex-col gap-6 overflow-x-auto overflow-y-auto">
                    <Gondolas v-if="gondolas?.length" />
                    <CreateGondola v-else-if="!gondolas?.length" />
                </div>
                <Properties />
            </div>
        </div> 
    </div>
</template>
<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { apiService } from '../services';
import { useEditorStore } from '../store/editor';
import Gondolas from './gondolas/Gondolas.vue';
import CreateGondola from './parials/CreateGondola.vue';
import Header from './parials/Header.vue'; 
import Properties from './parials/sidebar/Properties.vue';
import Products from './parials/sidebar/Products.vue';

const route = useRoute();
const router = useRouter();
const id = ref<string>(route.params.id as string);
const isLoading = ref<boolean>(false);

const editorStore = useEditorStore();

const record = ref<any>(null); // Substitua 'any' pelo tipo correto, se possível
const gondolas = computed(() => editorStore.gondolas); 

const get = async () => {
    const response = await apiService.get('plannerate/'.concat(id.value));
    record.value = response.data; 
    editorStore.setGondolas(response.data.gondolas);
    editorStore.setGondolaId(route.params.gondolaId as string); // Atualiza o ID da gôndola no store
};

// watch(
//     () => route.params,
//     async (newId) => {
//         if (!route.params.gondolaId) {
//             // Se não houver gondolaId na rota, redireciona para a primeira gôndola
//             if (gondolas.value.length > 0) {
//                 const firstGondola = gondolas.value[0];
//                 if (firstGondola) {
//                     // Atualiza o ID da gôndola no store
//                     editorStore.setGondolaId(firstGondola.id);
//                     await router.push({
//                         name: 'gondola.view',
//                         params: {
//                             id: id.value,
//                              gondolaId: firstGondola.id 
//                             },
//                     });
//                 }
//             }
//         }
//     },
// );

onMounted(async () => {
    isLoading.value = true;
    await get();
    isLoading.value = false;
});
</script>
