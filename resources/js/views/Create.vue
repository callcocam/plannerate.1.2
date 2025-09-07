<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50 dark:from-slate-900 dark:to-slate-800">
        <!-- Header Section -->
        <div class="sticky top-0 z-10 bg-white/80 dark:bg-slate-900/80 backdrop-blur-lg border-b border-gray-200 dark:border-gray-700">
            <Header v-if="record" :planogram="record" />
        </div>

        <!-- Main Content -->
        <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="max-w-7xl mx-auto">
                <!-- Page Title and Description -->
                <div class="text-center mb-8">
                    <h1 class="text-3xl sm:text-4xl font-bold text-gray-900 dark:text-white mb-4">
                        {{ record?.name || 'Novo Planograma' }}
                    </h1>
                    <p class="text-lg text-gray-600 dark:text-gray-300 max-w-2xl mx-auto">
                        Configure e organize suas gôndolas para criar o layout perfeito da sua loja
                    </p>
                </div>

                <!-- Empty State -->
                <Card v-if="gondolas.length === 0" class="relative overflow-hidden border-dashed border-2 border-gray-300 dark:border-gray-600">
                    <!-- Background Pattern -->
                    <div class="absolute inset-0 opacity-5">
                        <div class="absolute inset-0 bg-gradient-to-r from-blue-400 to-purple-500"></div>
                        <svg class="absolute bottom-0 left-0 mb-8" viewBox="0 0 375 283" fill="none" style="transform: scale(1.5); opacity: 0.1;">
                            <rect x="159" y="175" width="152" height="152" rx="8" transform="rotate(-45 159 175)" fill="currentColor"/>
                            <rect x="398" y="20" width="134" height="134" rx="8" transform="rotate(45 398 20)" fill="currentColor"/>
                        </svg>
                    </div>

                    <CardContent class="relative px-8 py-16 sm:px-16 sm:py-20 text-center">
                        <!-- Icon with Animation -->
                        <div class="relative mx-auto mb-8 w-32 h-32">
                            <div class="absolute inset-0 rounded-full bg-gradient-to-r from-blue-500 to-purple-600 opacity-20 animate-pulse"></div>
                            <div class="relative flex items-center justify-center w-full h-full rounded-full bg-gradient-to-r from-blue-500 to-purple-600 shadow-lg">
                                <ShoppingBagIcon class="w-16 h-16 text-white" />
                                <div class="absolute -top-2 -right-2 w-10 h-10 bg-emerald-500 rounded-full flex items-center justify-center shadow-lg animate-bounce">
                                    <PlusIcon class="w-6 h-6 text-white" />
                                </div>
                            </div>
                        </div>

                        <!-- Content -->
                        <h3 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white mb-4">
                            Bem-vindo ao seu novo planograma!
                        </h3>
                        <p class="text-lg text-gray-600 dark:text-gray-300 max-w-2xl mx-auto mb-8 leading-relaxed">
                            As gôndolas são o coração do seu planograma. Elas organizam seus produtos de forma estratégica 
                            para maximizar vendas e melhorar a experiência do cliente.
                        </p>

                        <!-- Features Grid -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10 max-w-4xl mx-auto">
                            <Card class="border-0 shadow-sm">
                                <CardContent class="flex flex-col items-center p-6">
                                    <div class="w-12 h-12 bg-blue-500 rounded-lg flex items-center justify-center mb-4">
                                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"></path>
                                        </svg>
                                    </div>
                                    <h4 class="font-semibold text-gray-900 dark:text-white mb-2">Layout Flexível</h4>
                                    <p class="text-sm text-gray-600 dark:text-gray-300 text-center">Organize produtos de forma intuitiva</p>
                                </CardContent>
                            </Card>
                            <Card class="border-0 shadow-sm">
                                <CardContent class="flex flex-col items-center p-6">
                                    <div class="w-12 h-12 bg-purple-500 rounded-lg flex items-center justify-center mb-4">
                                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                        </svg>
                                    </div>
                                    <h4 class="font-semibold text-gray-900 dark:text-white mb-2">Analytics</h4>
                                    <p class="text-sm text-gray-600 dark:text-gray-300 text-center">Otimize performance de vendas</p>
                                </CardContent>
                            </Card>
                            <Card class="border-0 shadow-sm">
                                <CardContent class="flex flex-col items-center p-6">
                                    <div class="w-12 h-12 bg-emerald-500 rounded-lg flex items-center justify-center mb-4">
                                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                        </svg>
                                    </div>
                                    <h4 class="font-semibold text-gray-900 dark:text-white mb-2">Fácil de Usar</h4>
                                    <p class="text-sm text-gray-600 dark:text-gray-300 text-center">Interface intuitiva e moderna</p>
                                </CardContent>
                            </Card>
                        </div>

                        <!-- CTA Button -->
                        <Button 
                            @click="openAddGondolaModal" 
                            size="lg"
                            class="relative inline-flex items-center px-8 py-4 text-lg font-semibold text-white bg-gradient-to-r from-blue-600 to-purple-600 border border-transparent rounded-xl hover:from-blue-700 hover:to-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 transition-all duration-200"
                        >
                            <PlusIcon class="w-6 h-6 mr-3" />
                            Criar Primeira Gôndola
                            <svg class="w-5 h-5 ml-2 -mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                            </svg>
                        </Button>
                    </CardContent>
                </Card>

                <!-- Gondolas Grid -->
                <div v-else class="space-y-6">
                    <!-- Stats Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                        <Card>
                            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle class="text-sm font-medium text-muted-foreground">
                                    Total Gôndolas
                                </CardTitle>
                                <ShoppingBagIcon class="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div class="text-2xl font-bold">{{ gondolas.length }}</div>
                                <p class="text-xs text-muted-foreground">
                                    Gôndolas configuradas
                                </p>
                            </CardContent>
                        </Card>
                        
                        <Card>
                            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle class="text-sm font-medium text-muted-foreground">
                                    Total Seções
                                </CardTitle>
                                <svg class="h-4 w-4 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                </svg>
                            </CardHeader>
                            <CardContent>
                                <div class="text-2xl font-bold">{{ totalSections }}</div>
                                <p class="text-xs text-muted-foreground">
                                    Seções em uso
                                </p>
                            </CardContent>
                        </Card>
                        
                        <Card>
                            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle class="text-sm font-medium text-muted-foreground">
                                    Total Produtos
                                </CardTitle>
                                <svg class="h-4 w-4 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                </svg>
                            </CardHeader>
                            <CardContent>
                                <div class="text-2xl font-bold">{{ totalProducts }}</div>
                                <p class="text-xs text-muted-foreground">
                                    Produtos posicionados
                                </p>
                            </CardContent>
                        </Card>
                        
                        <Card>
                            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle class="text-sm font-medium text-muted-foreground">
                                    Progresso
                                </CardTitle>
                                <svg class="h-4 w-4 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </CardHeader>
                            <CardContent>
                                <div class="text-2xl font-bold">{{ Math.round(completionPercentage) }}%</div>
                                <p class="text-xs text-muted-foreground">
                                    Planograma completo
                                </p>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Actions Bar -->
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                        <div>
                            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Suas Gôndolas</h2>
                            <p class="text-gray-600 dark:text-gray-400">Gerencie e organize suas gôndolas</p>
                        </div>
                        <Button 
                            @click="openAddGondolaModal"
                            class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg shadow-sm hover:shadow-md transition-all duration-200"
                        >
                            <PlusIcon class="w-4 h-4 mr-2" />
                            Nova Gôndola
                        </Button>
                    </div>

                    <!-- Gondolas Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <Card
                            v-for="gondola in gondolas" 
                            :key="gondola.id"
                            class="group hover:shadow-lg transition-all duration-200 cursor-pointer"
                        >
                            <CardHeader class="pb-3">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <CardTitle class="text-lg font-semibold group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">
                                            {{ gondola.name }}
                                        </CardTitle>
                                        <CardDescription class="mt-1 line-clamp-2">
                                            {{ gondola.description || 'Nenhuma descrição disponível' }}
                                        </CardDescription>
                                    </div>
                                    <div class="opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                                        <!-- <Button variant="ghost" size="sm" class="h-8 w-8 p-0">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                                            </svg>
                                        </Button> -->
                                    </div>
                                </div>
                            </CardHeader>
                            
                            <CardContent>
                                <!-- Stats -->
                                <div class="flex items-center gap-4 mb-4">
                                    <div class="flex items-center text-sm text-muted-foreground">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                        </svg>
                                        {{ gondola.sections?.length || 0 }} seções
                                    </div>
                                    <div class="flex items-center text-sm text-muted-foreground">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                        </svg>
                                        {{ gondola.products?.length || 0 }} produtos
                                    </div>
                                </div>

                                <!-- Progress Bar -->
                                <div class="space-y-2">
                                    <div class="flex justify-between text-sm">
                                        <span class="text-muted-foreground">Progresso</span>
                                        <span class="font-medium">75%</span>
                                    </div>
                                    <div class="w-full bg-secondary rounded-full h-2">
                                        <div class="bg-gradient-to-r from-blue-500 to-purple-500 h-2 rounded-full transition-all duration-300" style="width: 75%"></div>
                                    </div>
                                </div>

                                <!-- Action Button -->
                                <Button 
                                    @click="redirectToGondola(gondola.id)" 
                                    variant="outline"
                                    size="sm"
                                    class="w-full mt-4 group-hover:bg-blue-50 group-hover:border-blue-300 group-hover:text-blue-700 dark:group-hover:bg-blue-900/20 dark:group-hover:border-blue-600 dark:group-hover:text-blue-400 transition-all duration-200 cursor-pointer"
                                >
                                    Ver Detalhes
                                    <svg class="w-4 h-4 ml-2 group-hover:translate-x-1 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                    </svg>
                                </Button>
                            </CardContent>
                        </Card>
                    </div>
                </div>

                <!-- Router View for Modals -->
                <router-view :key="route.fullPath.concat('create-gondola')" />
            </div>
        </div>
    </div>
</template> 
<script setup lang="ts">
import { PlusIcon, ShoppingBagIcon } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useEditorService } from '@plannerate/services/editorService';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import Header from './partials/Header.vue';

const props = defineProps({
    record: {
        type: Object,
        default: () => ({}),
    },
});

const editorService = useEditorService();
const record = ref<any>(props.record); // Substitua 'any' pelo tipo correto, se possível

const gondolas = ref<any[]>([]); // Substitua 'any' pelo tipo correto, se possível

const route = useRoute();
const router = useRouter();
const planogramId = computed(() => route.params.id);

// Computed properties for statistics
const totalSections = computed(() => {
    return gondolas.value.reduce((total, gondola) => {
        return total + (gondola.sections?.length || 0)
    }, 0)
})

const totalProducts = computed(() => {
    return gondolas.value.reduce((total, gondola) => {
        return total + (gondola.products?.length || 0)
    }, 0)
})

const completionPercentage = computed(() => {
    if (gondolas.value.length === 0) return 0
    
    const completed = gondolas.value.filter(gondola => {
        return gondola.sections?.length > 0 && gondola.products?.length > 0
    }).length
    
    return (completed / gondolas.value.length) * 100
})

// Função para abrir o modal de adicionar gôndola
const openAddGondolaModal = () => {
    router.push({
        name: 'plannerate.create',
        params: {
            id: planogramId.value,
        },
    });
};

onMounted(() => {
    if (record.value && Object.keys(record.value).length > 0) {
        return;
    }
    const planogramIdStr = Array.isArray(planogramId.value) ? planogramId.value[0] : planogramId.value;
    editorService.fetchPlanogram(planogramIdStr).then((data) => {
        record.value = data;
        gondolas.value = data.gondolas || [];
    });
});

// Função para redirecionar para a visualização da gôndola
const redirectToGondola = (id: string) => {
    router.push({
        name: 'gondola.view',
        params: {
            id: planogramId.value,
            gondolaId: id,
        },
    });
};
</script>

<style scoped>
/* Custom animations and effects */
@keyframes float {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-10px); }
}

@keyframes shimmer {
    0% { background-position: -200px 0; }
    100% { background-position: calc(200px + 100%) 0; }
}

.animate-float {
    animation: float 3s ease-in-out infinite;
}

.shimmer {
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
    background-size: 200px 100%;
    animation: shimmer 2s infinite;
}

/* Line clamp utility */
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* Custom gradients */
.bg-gradient-mesh {
    background: radial-gradient(circle at 25% 25%, #3b82f6 0%, transparent 50%), 
                radial-gradient(circle at 75% 75%, #8b5cf6 0%, transparent 50%),
                linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
}

.dark .bg-gradient-mesh {
    background: radial-gradient(circle at 25% 25%, #1e40af 0%, transparent 50%), 
                radial-gradient(circle at 75% 75%, #6d28d9 0%, transparent 50%),
                linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
}

/* Glass morphism effect */
.glass {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.dark .glass {
    background: rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

/* Custom scrollbar */
::-webkit-scrollbar {
    width: 6px;
}

::-webkit-scrollbar-track {
    background: transparent;
}

::-webkit-scrollbar-thumb {
    background: rgba(156, 163, 175, 0.5);
    border-radius: 3px;
}

::-webkit-scrollbar-thumb:hover {
    background: rgba(156, 163, 175, 0.8);
}

/* Hover effects */
.hover-lift {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.hover-lift:hover {
    transform: translateY(-4px);
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

/* Button styles */
.btn-gradient {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    transition: all 0.3s ease;
}

.btn-gradient:hover {
    background: linear-gradient(135deg, #5a67d8 0%, #6b46c1 100%);
    transform: translateY(-1px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
}

/* Card hover effects */
.card-hover {
    transition: all 0.3s ease;
}

.card-hover:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
}

/* Loading skeleton */
.skeleton {
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: loading 1.5s infinite;
}

@keyframes loading {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

.dark .skeleton {
    background: linear-gradient(90deg, #374151 25%, #4b5563 50%, #374151 75%);
    background-size: 200% 100%;
}

/* Responsive improvements */
@media (max-width: 640px) {
    .container {
        padding-left: 1rem;
        padding-right: 1rem;
    }
}

/* Focus states */
.focus-visible {
    outline: 2px solid transparent;
    outline-offset: 2px;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.5);
}

/* Text gradients */
.text-gradient {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

/* Custom borders */
.border-gradient {
    border: 1px solid transparent;
    background: linear-gradient(white, white) padding-box,
                linear-gradient(135deg, #667eea, #764ba2) border-box;
}

.dark .border-gradient {
    background: linear-gradient(#1f2937, #1f2937) padding-box,
                linear-gradient(135deg, #667eea, #764ba2) border-box;
}
</style>
