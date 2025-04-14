<template>
    <div class="mb-6 border-b pb-4 dark:border-gray-700" v-if="planogram">
        <!-- Modal para adicionar gôndola -->

        <div class="flex items-center justify-between">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <h2 class="text-2xl font-bold tracking-tight dark:text-gray-100">{{ planogram.name }}</h2>
                    <Badge :variant="getStatusVariant(planogram.status)">
                        {{ planogram.status }}
                    </Badge>
                </div>
                <p class="text-sm text-muted-foreground dark:text-gray-400">
                    ID: {{ planogram.id }} | Criado em: {{ formatDate(planogram.created_at) }}
                </p>
            </div>

            <div class="flex items-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    @click="openAddGondolaModal"
                    class="dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                >
                    <PlusCircleIcon class="mr-2 h-4 w-4" />
                    Adicionar Gôndola
                </Button>
                <Button variant="outline" size="sm" class="dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">
                    <PencilIcon class="mr-2 h-4 w-4" />
                    Editar
                </Button>
                <Button size="sm" class="dark:hover:bg-primary-800">
                    <SaveIcon class="mr-2 h-4 w-4" />
                    Salvar
                </Button>
            </div>
        </div>

        <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2">
            <Card class="dark:border-gray-700 dark:bg-gray-800">
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm font-medium dark:text-gray-200">Tenant</CardTitle>
                </CardHeader>
                <CardContent v-if="planogram.store">
                    <div class="text-lg font-semibold dark:text-gray-100">{{ planogram.store.name }}</div>
                    <div class="text-sm text-muted-foreground dark:text-gray-400">{{ planogram.store.email }}</div>
                </CardContent>
            </Card>

            <Card class="dark:border-gray-700 dark:bg-gray-800">
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm font-medium dark:text-gray-200">Detalhes</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="space-y-1">
                        <div class="flex justify-between">
                            <span class="text-sm text-muted-foreground dark:text-gray-400">Slug:</span>
                            <span class="text-sm font-medium dark:text-gray-300">{{ planogram.slug }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-muted-foreground dark:text-gray-400">Atualizado:</span>
                            <span class="text-sm font-medium dark:text-gray-300">{{ formatDate(planogram.updated_at) }}</span>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>

<script setup>
import { PencilIcon, PlusCircleIcon, SaveIcon } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router'; 

const router = useRouter();
const route = useRoute();

// Props para receber os dados do planograma do Inertia
const props = defineProps({
    planogram: {
        type: Object,
        required: true,
    },
}); 

const gondolaId = computed(() => route.params.id);

// Emitir eventos para o componente pai
const emit = defineEmits(['close', 'gondola-added']);

// Estado para controlar a visibilidade dos modais
const showGondolaModal = ref(false);

// Função para abrir o modal de adicionar gôndola
const openAddGondolaModal = () => {
    const query = {
        ...route.query,
    };
    console.log('query', route.params);
    router.push({
        name: 'plannerate.gondola.create',
        params: { id: gondolaId.value },
        query,
    });
};

// Função para formatar datas
const formatDate = (dateString) => {
    if (!dateString) return 'N/A';

    const date = new Date(dateString);
    return date.toLocaleDateString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

// Determina a variante de cor do badge com base no status
const getStatusVariant = (status) => {
    switch (status?.toLowerCase()) {
        case 'published':
            return 'success';
        case 'draft':
            return 'secondary';
        default:
            return 'default';
    }
};
</script>
