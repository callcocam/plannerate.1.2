<template>
    <!-- Área central rolável (vertical e horizontal) -->
    <div class="flex h-full w-full flex-col gap-6 overflow-x-auto overflow-y-auto border border-gray-200 rounded-md dark:border-gray-700">
        <NavigationMenu>
            <NavigationMenuList>
                <NavigationMenuItem class="flex items-center" v-for="gondola in gondolas" :key="gondola.id">
                    <NavigationMenuLink as-child>
                        <router-link
                            :to="getLink(gondola, id)"
                            class="flex items-center gap-2 rounded-md p-2 text-sm font-medium text-gray-900 hover:bg-gray-100 dark:text-gray-100 dark:hover:bg-gray-700"
                            active-class="bg-gray-100 dark:bg-gray-700"
                            :class="{
                                'border border-green-500/20': gondola.linked_map_gondola_id,
                                'border border-red-500/20': !gondola.linked_map_gondola_id
                            }"
                        >
                            {{ gondola.name }} 
                        </router-link>
                    </NavigationMenuLink>
                </NavigationMenuItem>
            </NavigationMenuList>
        </NavigationMenu>
        <router-view :key="route.fullPath.concat('-gondolas')" />
    </div>
</template>
<script setup lang="ts">
import { computed, ref } from 'vue';
import { useRoute } from 'vue-router'; 
import { NavigationMenu, NavigationMenuItem, NavigationMenuLink, NavigationMenuList } from './../../components/ui/navigation-menu';
const props = defineProps({
    record: {
        type: Object,
        default: () => null,
    },
});
const route = useRoute();
const id = ref<string>(route.params.id as string);
 

const gondolas = computed(() => {
    return props.record.gondolas || [];
});

const getLink = (gondola: any, id: any) => ({
    name: 'gondola.view',
    params: { id, gondolaId: gondola.id },
}); 
</script>
