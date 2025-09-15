# Plannerate - Sistema de Gestão de Planogramas

[![Latest Version on Packagist](https://img.shields.io/packagist/v/callcocam/plannerate.svg?style=flat-square)](https://packagist.org/packages/callcocam/plannerate)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/callcocam/plannerate/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/callcocam/plannerate/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/callcocam/plannerate/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/callcocam/plannerate/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/callcocam/plannerate.svg?style=flat-square)](https://packagist.org/packages/callcocam/plannerate)

O Plannerate é um sistema de gestão de planogramas para varejo, permitindo criar e gerenciar layouts detalhados de gôndolas, prateleiras e produtos em lojas. Este pacote integra um backend Laravel com um frontend Vue 3, oferecendo uma solução completa e reusável.

## Características

- Sistema completo de gestão de planogramas
- Interface Vue 3 com componentes reutilizáveis
- Sistema multitenancy integrado
- Gerenciamento de lojas, departamentos, gôndolas, seções e produtos
- Visualização interativa de layouts

## Instalação

### 1. Instalação via Composer

Instale o pacote via Composer:

```bash
composer require callcocam/plannerate
```

### 2. Publicação e Execução das Migrações

Publique e execute as migrações para criar as tabelas necessárias:

```bash
php artisan vendor:publish --tag="plannerate-migrations"
php artisan migrate
```

### 3. Publicação dos Assets e Configurações

```bash
# Publicar configurações
php artisan vendor:publish --tag="plannerate-config"

# Publicar assets (CSS, JS)
php artisan vendor:publish --tag="plannerate-assets"

# Publicar views (opcional)
php artisan vendor:publish --tag="plannerate-views"
```

### 4. Instalação dos Componentes Frontend

Para projetos que já usam Vue 3, você pode instalá-lo automaticamente usando:

```bash
php artisan plannerate:install-frontend
```

Ou manualmente:

1. Instale as dependências necessárias:

```bash
npm install vue@^3.3.0 vue-router@^4.2.0 pinia@^2.1.0
```

2. Compile os assets:

```bash
npm run build
```

## Integração com Vue

### Opção 1: Usando o Plugin Vue em uma aplicação existente

```js
// Em seu arquivo main.js ou app.js
import { createApp } from 'vue';
import Plannerate from 'plannerate-vue';
import 'plannerate-vue/style.css';

const app = createApp(App);

app.use(Plannerate, {
    baseUrl: '/api',
    tenant: 'default'
});

app.mount('#app');
```

### Opção 2: Uso com Inertia.js

Para projetos Inertia.js:

```js
// Em seu arquivo app.js
import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import Plannerate from 'plannerate-vue';
import 'plannerate-vue/style.css';

createInertiaApp({
  resolve: name => {
    const pages = import.meta.glob('./Pages/**/*.vue', { eager: true });
    return pages[`./Pages/${name}.vue`];
  },
  setup({ el, App, props, plugin }) {
    const app = createApp({ render: () => h(App, props) });
    
    app.use(plugin);
    app.use(Plannerate, {
      baseUrl: '/api',
      tenant: 'default'
    });
    
    app.mount(el);
  },
});
```

### Opção 3: Usando Componentes Individuais

Você também pode importar componentes específicos:

```vue
<script setup>
import { PlannerateApp, ConfirmModal } from 'plannerate-vue';
</script>

<template>
  <PlannerateApp :record="myRecord" />
  <ConfirmModal v-model:isOpen="showConfirm" @confirm="handleConfirm" />
</template>
```

## Uso no Backend Laravel

### Controladores

O pacote inclui controladores prontos para uso. Para integrar com seu aplicativo:

```php
// routes/web.php
Route::middleware(['auth'])->group(function () {
    Route::get('/planograms', [App\Http\Controllers\PlanogramController::class, 'index'])->name('planograms.index');
    // Outras rotas conforme necessário
});
```

### Acesso Direto à API

```php
use Callcocam\Plannerate\Facades\Plannerate;

// Obter um planograma
$planogram = Plannerate::getPlanogram($id);

// Criar uma nova gôndola
$gondola = Plannerate::createGondola([
    'planogram_id' => $planogramId,
    'name' => 'Nova Gôndola',
    'num_modulos' => 3
]);
```

## Customização

### Temas e Estilos

O pacote usa variáveis CSS que podem ser sobrescritas:

```css
:root {
  --primary-color: #your-primary-color;
  --secondary-color: #your-secondary-color;
}
```

### Configurações

Você pode personalizar o comportamento do pacote editando o arquivo `config/plannerate.php`:

```php
return [
    'tenant_middleware' => 'tenant', // Middleware para multitenancy (opcional)
    'use_soft_deletes' => true,     // Usar exclusão suave
    'default_scale' => 1,           // Escala padrão para o renderizador
    // Outras configurações
];
```

## Progresso do Desenvolvimento

### Melhorias de Interface ✅

- ✅ **Suporte a Números Decimais**: Campos de cremalheira e furos agora aceitam valores decimais
  - Campos `cremalheira_width`, `hole_height`, `hole_width` e `hole_spacing` migrados de `integer` para `decimal(8,2)`
  - Migração atualizada para suportar números decimais (ex: 4.2, 3.5, 2.1)
  - Modelo Section atualizado com casts `decimal:2` para os campos específicos
  - Validações nos Requests alteradas de `integer` para `numeric`
  - SectionResource retorna valores como `float` ao invés de `int`
  - Tipos TypeScript atualizados com exemplos de valores decimais
  - Composables e formulários frontend atualizados com `step="any"` para aceitar decimais
  - Compatibilidade mantida com o frontend existente

- ✅ **Padronização de Modais**: TargetStockResultModal.vue e AnalysisResultModal.vue atualizados para usar o mesmo padrão de estilo do BCGResultModal.vue
  - Migração para componentes Dialog do shadcn/ui
  - Melhor estrutura de layout com DialogContent, DialogHeader e DialogFooter
  - Responsividade aprimorada com flex layout
  - Consistência visual entre modais de análise
  - TargetStockResultModal: Resumo melhorado com métricas de estoque atual e alvo
  - AnalysisResultModal: Resumo otimizado com cards separados para itens ativos/inativos e porcentagens
  - Todas as funcionalidades mantidas (filtros, ordenação, exportação, recálculo)

- ✅ **Correção de Validação de Gôndola**: Campo "Localização" corrigido para ser opcional
  - Alinhamento entre validação frontend (Zod) e backend (Laravel)
  - Campo location removido da validação obrigatória no useGondolaCreateForm.ts
  - Campo location removido da validação obrigatória no useGondolaEditForm.ts
  - Interface atualizada para indicar que o campo é opcional
  - Consistência com as regras de validação do backend (nullable)

- ✅ **Correção de Erros de Null/Undefined**: Componentes Segment.vue e Layer.vue corrigidos para evitar erros de renderização
  - Adicionadas verificações de segurança para `props.segment.layer.product` e `props.layer.product`
  - Computed properties `outerSegmentStyle`, `innerSegmentStyle`, `depthCount` e `layerStyle` protegidas contra valores null/undefined
  - Templates atualizados com `v-if` para evitar renderização quando dados não estão disponíveis
  - Logs de warning adicionados para facilitar debugging quando dados estão faltando
  - Valores padrão (0px, 0) aplicados quando propriedades não estão disponíveis
  - Estabilidade da aplicação melhorada, eliminando crashes por tentativa de acesso a propriedades de objetos null

- ✅ **Correção de Edição de Seções**: Sistema de edição de seções agora salva corretamente os tamanhos dos furos da cremalheira
  - Implementado recálculo automático dos furos quando campos relacionados à cremalheira são alterados no backend
  - Frontend atualizado para chamar a API diretamente em vez de apenas atualizar o estado local
  - Adicionadas rotas nested para seções (`gondolas/{gondolaId}/sections/{id}`) para melhor organização da API
  - Furos da cremalheira são automaticamente recalculados quando `hole_height`, `hole_width`, `hole_spacing`, `height` ou `base_height` são modificados
  - Alinhamento visual dos furos aprimorado para perfeita centralização na cremalheira
  - Sincronização entre estado local e backend garantida após edições

### ✅ Refatoração do AutoPlanogramController (FASE 1 Concluída)

- ✅ **FASE 1A - Otimização de Logs**: Redução de 43% dos logs (de 76 para 43 logs)
  - Remoção de 33 logs debug excessivos
  - Manutenção de todos os logs críticos de produção (erros, warnings, operações principais)
  - Melhoria significativa de performance por redução de I/O de logging
  - Logs mais limpos e focados para debugging eficiente

- ✅ **FASE 1B - Eliminação de Duplicações**: Consolidação da extração de largura de produtos
  - Criação do método utilitário `getProductWidth()` 
  - Substituição de 7 ocorrências duplicadas do padrão `floatval($productData['width'] ?? 25)`
  - Centralização da lógica de fallback para larguras de produtos
  - Melhoria da manutenibilidade e facilidade de futuras modificações

- ✅ **FASE 1C - Limpeza de Comentários**: Padronização e profissionalização dos comentários
  - Remoção de comentários temporários e referências de debug
  - Padronização da documentação inline
  - Comentários mais profissionais e informativos

- ✅ **FASE 2A - FacingCalculatorService**: Extração completa de cálculos de facing
  - Criação do `FacingCalculatorService` (181 linhas)
  - Métodos extraídos: `calculateOptimalFacing`, `calculateConservativeFacing`, `calculateAdaptiveFacing`
  - Responsabilidade única para todos os cálculos de facing
  - Dependency injection implementada no controller
  
- ✅ **FASE 2B - ProductDataExtractorService**: Extração de processamento de dados de produtos
  - Criação do `ProductDataExtractorService` (210 linhas)
  - Métodos extraídos: `enrichScoresWithProductData`, `applyDynamicFilters`, `getCategoryDescendants`
  - Centralização da lógica de dados de produtos e filtros
  - Facilita manutenção e testes unitários

- ✅ **Resultados Quantitativos EXCEPCIONAIS (Fases 1+2)**:
  - **Redução total**: De 2.065 para 1.657 linhas (408 linhas removidas - 19.8%!)
  - **Services criados**: 2 services especializados (391 linhas de código extraído)
  - **Progresso para meta de 800 linhas**: 50.9% concluído
  - **Arquitetura SOLID**: Princípios aplicados com dependency injection
  - **Zero breaking changes**: APIs públicas 100% preservadas
  - **Manutenibilidade**: Drasticamente melhorada com responsabilidades separadas

### ✅ Integração ABC + Target Stock (FASE 2 Concluída)

- ✅ **FASE 2A - Frontend Expandido**: Modal de geração automática expandido com parâmetros ABC + Target Stock
  - Interface de 3 colunas com configurações completas de análise
  - Parâmetros ABC: pesos (quantidade, valor, margem) e thresholds (A, B)
  - Parâmetros Target Stock: dias de cobertura, estoque segurança, service level
  - Configuração de facing por classe (A, B, C) com limites min/max
  - Dois botões: "Gerar Básico" (atual) e "🧠 Gerar Inteligente" (novo)

- ✅ **FASE 2B - Service Frontend**: Criado `autoplanogramService.ts` para chamadas da API
  - Interface TypeScript para requisições inteligentes
  - Tratamento de erros robusto
  - Integração com `apiService` existente

- ✅ **FASE 2C - Componente Pai**: Modificado `Info.vue` para suportar geração inteligente
  - Novo método `executeIntelligentGeneration()` implementado
  - Função `showGenerationStats()` para exibir resultados detalhados
  - Evento `@confirm-intelligent` adicionado ao modal

- ✅ **FASE 2D - Backend Completo**: Implementado endpoint `generateIntelligent()` no controller
  - **Método principal**: `generateIntelligent()` com validação completa
  - **Análise ABC**: `executeABCAnalysis()` usando ScoreEngine existente
  - **Análise Target Stock**: `executeTargetStockAnalysis()` com cálculos científicos
  - **Processamento inteligente**: `processProductsIntelligently()` combinando ABC + Target Stock
  - **Facing inteligente**: `calculateIntelligentFacing()` baseado em classe ABC + urgência
  - **Distribuição**: `distributeIntelligently()` usando ProductPlacementService existente
  - **Métodos auxiliares**: 8 métodos de suporte para cálculos e métricas

- ✅ **FASE 2E - Rota API**: Adicionada rota `/api/plannerate/auto-planogram/generate-intelligent`
  - Integrada ao grupo de rotas existente do auto-planograma
  - Nome da rota: `api.auto-planogram.generate-intelligent`

### ✅ Correção de Filtros do Modal de Geração Automática (Concluída)

- ✅ **Problema Identificado**: O limite configurado (ex: 50) não estava sendo respeitado devido à lógica incorreta do modo inteligente
- ✅ **Solução Implementada**: Criação de estado separado `generationMode` para controlar modo básico vs inteligente
- ✅ **Melhorias Realizadas**:
  - Estado reativo `generationMode` independente do limite configurado
  - Métodos `setBasicMode()` e `setIntelligentMode()` agora preservam o limite do usuário
  - Modo básico: respeita o limite configurado (ex: 50 produtos)
  - Modo inteligente: usa limite alto (999999) apenas internamente para processar todos os produtos
  - Função `resetFilters()` atualizada para incluir reset do modo de geração
- ✅ **Resultado**: Agora o limite de 50 (ou qualquer valor) é respeitado corretamente no modo básico
- ✅ **Correção Backend**: Modificado `getAllProductsByPlanogramCategory()` para usar `$filters['limit']` em vez do valor hardcoded 999999
- ✅ **Campo de Limite Inteligente**: Adicionado campo específico para limitar produtos no modo inteligente (10-200 produtos)
  - Interface separada para modo básico (máx 50) e inteligente (máx 200)
  - Facilita testes de ABC + Target Stock com números controlados
  - Valor padrão: 100 produtos para modo inteligente
- ✅ **Remoção de Fallbacks de Dimensões**: Sistema agora usa APENAS produtos com dimensões reais
  - Removido fallback de 25mm que causava produtos "fantasmas" de 25cm
  - Filtros rigorosos: apenas produtos com `width > 0` são processados
  - Logs detalhados para produtos ignorados por falta de dimensões
  - Melhora significativa na precisão da distribuição de produtos

### Próximas Melhorias ⏳

- ⏳ **FASE 3A**: Quebra de métodos gigantes (`placeProductsSequentially`, `calculateScores`) (~300 linhas)
- ⏳ **FASE 3B**: Extrair services adicionais (ShelfSpaceValidator, GondolaStructureAnalyzer) (~200 linhas)
- ⏳ **FASE 3C**: Otimizações finais com Design Patterns (~157 linhas para atingir meta de 800 linhas)
- ⏳ Implementação de novos recursos de análise
- ⏳ Melhorias de acessibilidade

### Melhorias de Debug e Monitoramento ✅

- ✅ **Sistema de Logs Detalhados para Recálculo de Furos**: Implementado sistema completo de logs para monitorar todo o fluxo de recálculo dos furos da cremalheira
  - **Frontend (Section.vue)**: Logs para capturar mudanças no formulário e envio de dados
  - **Serviço (sectionService.ts)**: Logs para monitorar chamadas da API
  - **Controller (SectionController.php)**: Logs detalhados para processamento e verificação de campos
  - **Serviço de Posicionamento (ShelfPositioningService.php)**: Logs para cálculos matemáticos dos furos
  - **Modelo (Section.php)**: Logs para operações de banco de dados
  - Rastreamento completo desde modificação no frontend até salvamento no banco
  - Logs com timestamps e identificadores únicos para facilitar debugging
  - Monitoramento de todos os passos: mudança → envio → processamento → cálculo → salvamento → resposta

## Documentação

Para documentação completa, visite [a documentação oficial](https://github.com/callcocam/plannerate/docs).

## Desenvolvimentos Recentes

### ✅ Seleção Múltipla de Produtos (Concluído)
- Implementada seleção múltipla com CTRL+click nos produtos
- Indicadores visuais para produtos selecionados (borda azul)
- Contador de produtos selecionados no cabeçalho
- Drag and drop de múltiplos produtos simultaneamente
- Botão "Limpar" e suporte à tecla ESC
- Validação inteligente de largura para múltiplos produtos
- Toast de feedback ao adicionar múltiplos produtos
- Compatibilidade total com seleção única existente

## Contribuição

Contribuições são bem-vindas! Por favor, veja [CONTRIBUTING](CONTRIBUTING.md) para detalhes.

## Créditos

- [Claudio Campos](https://github.com/callcocam)
- [Todos os Contribuidores](../../contributors)

## Licença

Este projeto é licenciado sob a licença MIT. Veja o arquivo [LICENSE.md](LICENSE.md) para mais detalhes.
