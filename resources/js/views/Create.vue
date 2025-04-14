<template>
    <div class="container mx-auto max-w-4xl p-4">
        <div class="mb-6 flex items-center">
            <Button variant="outline" size="sm" class="mr-3" @click="goBack">
                <ChevronLeft class="mr-1 h-4 w-4" />
                Voltar
            </Button>
            <h1 class="text-2xl font-bold text-primary">Novo Planograma</h1>
        </div>

        <form @submit.prevent="savePlanogram" class="space-y-8">
            <Card>
                <CardHeader>
                    <CardTitle>Informações Básicas</CardTitle>
                    <CardDescription>Preencha os dados principais do planograma</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <!-- Nome -->
                        <div class="space-y-2">
                            <Label for="name" required>Nome</Label>
                            <Input id="name" v-model="form.name" placeholder="Nome do planograma" :error="errors.name" />
                            <p v-if="errors.name" class="text-sm text-destructive">{{ errors.name }}</p>
                        </div>

                        <!-- Slug -->
                        <div class="space-y-2">
                            <Label for="slug">Slug</Label>
                            <Input id="slug" v-model="form.slug" placeholder="slug-do-planograma" readonly disabled />
                            <p class="text-xs text-muted-foreground">Gerado automaticamente a partir do nome</p>
                        </div>

                        <!-- Descrição -->
                        <div class="col-span-1 space-y-2 md:col-span-2">
                            <Label for="description">Descrição</Label>
                            <Textarea
                                id="description"
                                v-model="form.description"
                                placeholder="Descrição do planograma"
                                rows="3"
                                :error="errors.description"
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Relações</CardTitle>
                    <CardDescription>Vincule o planograma a outros elementos</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <!-- Loja -->
                        <div class="space-y-2">
                            <Label for="store_id">Loja</Label>
                            <Select v-model="form.store_id" :error="errors.store_id">
                                <SelectTrigger>
                                    <SelectValue placeholder="Selecionar loja" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="store in stores" :key="store.id" :value="store.id">
                                        {{ store.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="errors.store_id" class="text-sm text-destructive">{{ errors.store_id }}</p>
                        </div>

                        <!-- Cluster -->
                        <div class="space-y-2">
                            <Label for="cluster_id">Cluster</Label>
                            <Select v-model="form.cluster_id" :error="errors.cluster_id">
                                <SelectTrigger>
                                    <SelectValue placeholder="Selecionar cluster" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="cluster in clusters" :key="cluster.id" :value="cluster.id">
                                        {{ cluster.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <!-- Departamento -->
                        <div class="space-y-2">
                            <Label for="department_id">Departamento</Label>
                            <Select v-model="form.department_id" :error="errors.department_id">
                                <SelectTrigger>
                                    <SelectValue placeholder="Selecionar departamento" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="department in departments" :key="department.id" :value="department.id">
                                        {{ department.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <!-- Status -->
                        <div class="space-y-2">
                            <Label for="status" required>Status</Label>
                            <Select v-model="form.status" :error="errors.status">
                                <SelectTrigger>
                                    <SelectValue placeholder="Selecionar status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="status in statusOptions" :key="status.value" :value="status.value">
                                        {{ status.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="errors.status" class="text-sm text-destructive">{{ errors.status }}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Período de Execução</CardTitle>
                    <CardDescription>Defina o prazo para a loja executar o planograma</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <!-- Data de Início -->
                        <div class="space-y-2">
                            <Label for="start_date">Data de Início</Label>
                            <Input
                                type="date"
                                v-model="form.start_date"
                                :enableTimePicker="false"
                                format="DD/MM/YYYY"
                                placeholder="Selecione a data inicial"
                                autoApply
                                :error="errors.start_date"
                            />
                            <p v-if="errors.start_date" class="text-sm text-destructive">{{ errors.start_date }}</p>
                        </div>

                        <!-- Data de Término -->
                        <div class="space-y-2">
                            <Label for="end_date">Data de Término</Label>
                            <Input
                                type="date"
                                v-model="form.end_date"
                                :enableTimePicker="false"
                                format="DD/MM/YYYY"
                                placeholder="Selecione a data final"
                                autoApply
                                :minDate="form.start_date"
                                :error="errors.end_date"
                            />
                            <p v-if="errors.end_date" class="text-sm text-destructive">{{ errors.end_date }}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <div class="flex justify-end gap-3">
                <Button variant="outline" type="button" @click="goBack">Cancelar</Button>
                <Button type="submit" :loading="isSubmitting">
                    <Loader2 v-if="isSubmitting" class="mr-2 h-4 w-4 animate-spin" />
                    <Save v-else class="mr-2 h-4 w-4" />
                    Salvar Planograma
                </Button>
            </div>
        </form>
    </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { apiService, handleValidationErrors } from './../services';

import { ChevronLeft, Loader2, Save } from 'lucide-vue-next';
import { Button } from '../components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '../components/ui/card';
import { Input } from '../components/ui/input';
import { Label } from '../components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '../components/ui/select';
import { Textarea } from '../components/ui/textarea';

// Roteador
const router = useRouter();
const route = useRoute() as any;

// Estado do formulário
const form = reactive({
    name: '',
    slug: '',
    description: '',
    store_id: '',
    cluster_id: '',
    department_id: '',
    start_date: '',
    end_date: '',
    status: 'draft',
});

// Estado de submissão
const isSubmitting = ref(false);
const errors = reactive<Record<string, string>>({});

// Opções de status
const statusOptions = [
    { value: 'draft', label: 'Rascunho' },
    { value: 'published', label: 'Publicado' },
];

// Dados para os selects
const stores = ref([] as any[]);
const clusters = ref([] as any[]);
const departments = ref([] as any[]);

// Carregar dados relacionados
const loadRelatedData = async () => {
    try {
        // Carregar lojas
        const routePath = route.name.replace('.', '/').replace('index', 'create');
        console.log('Carregando lojas:', routePath);
        const storesResponse = await apiService.get(routePath);
        stores.value = storesResponse.data?.stores || [];

        // Carregar clusters
        clusters.value = storesResponse.data?.clusters || [];
        console.log('Carregando clusters:', clusters.value);

        // Carregar departamentos 
        departments.value = storesResponse.data?.departments || [];

        // Carregar stores

    } catch (error) {
        console.error('Erro ao carregar dados relacionados:', error);
    }
};

// Método para salvar o planograma
const savePlanogram = async () => {
    isSubmitting.value = true;

    try {
        const routePath = route.name.replace('.create',  '');
        console.log('Salvando planograma:', form, routePath);
        const response = await apiService.post(routePath, form);

        // Redirecionar após sucesso
        goBack();
    } catch (error) {
        console.error('Erro ao salvar planograma:', error);

        // Tratar erros de validação
        if (error.response && error.response.data && error.response.data.errors) {
            Object.assign(errors, error.response.data.errors);
        }
        handleValidationErrors(error, errors);
    } finally {
        isSubmitting.value = false;
    }
};

// Método para voltar à listagem
const goBack = () => {
    router.push({ name: 'plannerate.index' });
};
onMounted(() => {
    // Carregar dados relacionados ao montar o componente
    loadRelatedData();
});
</script>

<style scoped>
/* Customizar o datepicker para combinar com o shadcn-vue */
:deep(.dp__main) {
    font-family: inherit;
}

:deep(.dp__input) {
    height: 2.5rem;
    border-radius: 0.375rem;
    border: 1px solid hsl(var(--input));
    background-color: transparent;
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
    width: 100%;
}

:deep(.dp__input:focus) {
    outline: none;
    border-color: hsl(var(--ring));
    box-shadow: 0 0 0 1px hsl(var(--ring));
}

/* Estilo para campos obrigatórios */
.required:after {
    content: ' *';
    color: hsl(var(--destructive));
}
</style>
