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

- ✅ **Correção de Justificação de Produtos**: Sistema de justificação corrigido para evitar alargamento indevido dos produtos
  - Adicionado campo `distributed_width` nas tabelas `segments` e `layers` para armazenar larguras calculadas
  - Implementado método `calculateDistributedWidths()` no modelo `Shelf` para cálculo automático das larguras
  - Cálculo baseado no alinhamento da gôndola: para `justify`, distribui espaço proporcionalmente entre produtos
  - Frontend atualizado para usar `distributed_width` do backend ao invés de cálculos locais inconsistentes
  - Produtos mantêm tamanho natural, apenas o espaçamento é distribuído para ocupar a prateleira
  - Suporte a múltiplos segmentos na mesma prateleira com distribuição correta
  - Migration `add_distributed_width_to_segments_and_layers` adicionada ao package

### Próximas Melhorias ⏳

- ⏳ Implementação de novos recursos de análise
- ⏳ Otimização de performance
- ⏳ Melhorias de acessibilidade

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
