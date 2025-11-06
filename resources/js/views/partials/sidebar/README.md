# Componente Planogramns (Atualizado)

O componente `Planogramns.vue` foi atualizado para listar todos os planogramas disponíveis e permitir seleção de gôndolas baseada no planograma selecionado. As gôndolas são obtidas diretamente do objeto planograma retornado pela API.

## Funcionalidades Principais

### 🆕 **Lista de Planogramas**
- Busca todos os planogramas via API `/api/planograms`
- Seletor dropdown para escolher o planograma
- Detalhes do planograma selecionado (nome, tenant, quantidade de gôndolas)

### 🔄 **Gôndolas do Planograma**
- Lista gôndolas baseadas no planograma selecionado
- Gôndolas vêm do próprio objeto planograma (não do store do editor)
- Seleção visual e por dropdown
- Informações detalhadas da gôndola selecionada

### 📡 **Sincronização Inteligente**
- Auto-seleção do planograma atual se disponível no editor
- Sincronização com seção selecionada no editor
- Busca automática do planograma que contém uma gôndola específica

## Como usar

### Básico (Nova Interface)

```vue
<template>
  <Planogramns 
    v-model="selectedGondolaId" 
    @gondola-selected="handleGondolaSelected"
    @planogram-selected="handlePlanogramSelected"
  />
</template>

<script setup lang="ts">
import { ref } from 'vue';
import Planogramns from './Planogramns.vue';
import type { Gondola } from '@plannerate/types/gondola';

const selectedGondolaId = ref('');

const handleGondolaSelected = (gondola: Gondola) => {
  console.log('Gôndola selecionada:', gondola);
};

const handlePlanogramSelected = (planogram: any) => {
  console.log('Planograma selecionado:', planogram);
};
</script>
```

## Props

| Prop | Tipo | Padrão | Descrição |
|------|------|--------|-----------|
| `modelValue` | `string` | `''` | ID da gôndola selecionada |

## Eventos

| Evento | Parâmetros | Descrição |
|--------|-----------|-----------|
| `update:modelValue` | `(value: string)` | Emitido quando o ID da gôndola selecionada muda |
| `gondola-selected` | `(gondola: Gondola)` | Emitido quando uma gôndola é selecionada |
| `planogram-selected` | `(planogram: Planogram)` | 🆕 Emitido quando um planograma é selecionado |

## Layout Atualizado

O componente agora possui quatro seções principais:

1. **Seletor de Planograma**: Dropdown para escolher o planograma
2. **Seletor de Gôndola**: Aparece apenas quando um planograma está selecionado
3. **Lista de Gôndolas**: Mostra gôndolas do planograma selecionado com destaque
4. **Detalhes**: 
   - Informações do planograma selecionado (verde)
   - Informações da gôndola selecionada (azul)

## Estrutura de Dados

### Planograma
```typescript
interface Planogram {
  id: string;
  name: string;
  tenant?: {
    id: string;
    name: string;
  };
  gondolas: Gondola[];
}
```

### Fluxo de Dados
1. **Inicialização**: Busca planogramas via `editorService.fetchPlanograms()`
2. **Auto-seleção**: Se há um planograma no estado do editor, seleciona automaticamente
3. **Seleção de Planograma**: Reseta gôndola selecionada e mostra novas gôndolas
4. **Seleção de Gôndola**: Emite eventos para sincronização com componente pai

## API Endpoints

- `GET /api/planograms` - Lista todos os planogramas com gôndolas
- Resposta: `PlannerateResource::collection($planograms)`

## Integração com Section.vue

```vue
<Planogramns 
  v-model="formData.gondola_id" 
  @gondola-selected="handleGondolaSelected"
  @planogram-selected="handlePlanogramSelected"
/>
```

## Características Técnicas

- ✅ **Vue 3 Composition API** com TypeScript
- ✅ **Reatividade Completa** com computed e watchers
- ✅ **Loading States** para melhor UX
- ✅ **Error Handling** para falhas da API
- ✅ **Auto-sincronização** com estado do editor
- ✅ **Tipagem TypeScript** completa
- ✅ **Zero erros** de compilação

## Dependências

- Vue 3 Composition API
- Editor Store (Pinia)
- Editor Service (API calls)
- UI Components (Tooltip, Label, Select)
- Tipos TypeScript do Plannerate

## Exemplo de Console Logs

Ao usar o componente, você verá logs como:
```
Buscando planogramas com parâmetros: {}
Planogramas carregados: 5
Planograma selecionado: 01HXX...
Gôndola selecionada: 01HYY...
```

## Melhorias Implementadas

1. **Separação de Contextos**: Planogramas e gôndolas são tratados independentemente
2. **Fonte de Dados**: Gôndolas vêm diretamente da API, não do store do editor
3. **UX Melhorada**: Interface clara com seções bem definidas
4. **Sincronização Bidirecional**: Funciona tanto para entrada quanto para exibição de dados
5. **Flexibilidade**: Pode ser usado em qualquer contexto, não apenas no editor de seções