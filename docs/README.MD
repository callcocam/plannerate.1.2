Geração Automática de Planogramas
1. Funcionalidades Aprovadas

Motor de planograma automático com:

Score por SKU (quantidade, valor, margem).

Integração de mix/ABC (macro 1).

Integração de estoque-alvo (macro 2).

Anti-churn (penaliza mudanças desnecessárias).

Regras de adjacência, locks e blocos indivisíveis.

Zonas de prateleira (nobre, intermediária, rodapé).

“TO_REMOVE = 1 frente” para itens a retirar.

Equivalentes/substituíveis.

Seed determinística para reprodutibilidade.

Templates pré-definidos:

Amaciantes (concentrados em cima, diluídos em baixo, variação 1/2/3+ módulos).

Registry para lookup automático por departamento/categoria.

TemplateMode: auto, hybrid, template (hard).

Orquestração:

Regras por módulo, prateleira ou faixa horizontal (0–100%).

Possibilidade de misturar templates e motor automático.

KPIs e Relatórios:

% de estoque-alvo atingido.

Itens com déficit de estoque.

Itens “TO_REMOVE”.

Auditoria com versão de parâmetros, usuário e timestamp.

Flags de baixa confiança (CV>1 ou série curta).

2. Macros e Regras de Negócio

Macro 1: AnaliseCompletaSortimentoEStatus (mix/ABC)

Média ponderada (qtd, valor, margem).

Classificação ABC com cortes dinâmicos.

Ranking por categoria.

Identificação de produtos a retirar do mix (C com % < metade do menor B).

Status do produto (Ativo/Inativo).

Macro 2: CalcularEstoqueAlvo

Nível de serviço (z-score).

Cobertura em dias ajustada por lead time/fornecedor.

Estoque mínimo + estoque de segurança.

Identificação de séries com alta variabilidade (CV > 1).

Forecast robusto (média móvel, smoothing ou mediana).

Ambas foram portadas para TypeScript e integradas ao motor.

3. Fluxo do Sistema

Entrada de dados:

Gôndola (dimensões, módulos, prateleiras).

SKUs (dimensões, vendas, margem, fornecedor, lead time).

Séries temporais de vendas (DailySales).

Templates e regras (se aplicável).

Processamento:

Aplicação do mix/ABC (macro 1).

Cálculo de estoque-alvo (macro 2).

Geração do score ajustado (mix, estoque, anti-churn, etc.).

Distribuição automática respeitando dimensões, zonas e políticas.

Ajustes por template, orquestração ou locks.

Auditoria e geração de relatórios.

Visualização:

Painel de execução (Runner).

Comparação Antes → Depois (heatmap, flags).

KPIs em tempo real.

Editor visual de templates (drag & drop).

Editor de políticas por categoria (sliders, checkboxes).

Relatórios:

KPIs de desempenho e auditoria exportáveis (PDF/CSV).

Histórico de versões (undo/redo, export/import).

Integrações:

Conexão com banco de dados (produtos e vendas).

APIs/robôs para importar/exportar planogramas.

Estrutura pronta para futura integração com ERPs.

4. Interface do Usuário

AutoPlanogramRunner.vue: painel operacional com botões para rodar motor, aplicar templates, recalcular prateleiras, salvar versões, exportar e comparar.

TemplateEditor.vue: editor drag & drop para alocar categorias em módulos/prateleiras/faixas.

PolicyEditor.vue: ajuste dinâmico de parâmetros (pesos do mix, penalidades, anti-churn, metas de marca, adjacência, equivalentes).

VersionsCompare.vue: comparação visual antes/depois com heatmap, flags (“REM”, “P$”), observações.

5. Configurações Dinâmicas

Pesos de qtd/valor/margem.

Cortes ABC (corte A, corte B).

Nível de serviço por classe ABC.

Cobertura em dias (ajustada por fornecedor/lead time).

Penalidades de anti-churn (movimento, frente, ganho mínimo).

Metas/tetos de marca.

Regras de adjacência.

Locks de SKU, prateleira, blocos de marca.

TemplateMode (auto, híbrido, hard).

Equivalentes/substituíveis.

Seed para reprodutibilidade.

6. Integrações e Automações

Banco de dados: busca de SKUs, vendas e séries históricas.

ERP/robôs: endpoints REST para importar/exportar planogramas e status de produtos.

APIs internas: geração de relatórios em PDF/CSV, export/import de políticas.

Histórico local: versões salvas em memória ou LocalStorage.

7. Pontos de Refinamento Final

Solver leve (ILP/CP) como fallback em conflitos de espaço.

Substituição automática de SKUs equivalentes.

Teto por marca/categoria para evitar concentração excessiva.

Flags de confiabilidade em relatórios.

Assistente de conflito no UI.

Heatmaps por vendas, margem e estoque.

Auditoria expandida (policyId, versão, usuário, timestamp).

AB tests offline (comparar variantes antes de publicar).

Determinismo garantido via seed.

Testes automatizados (unitários e property-based).

---

# 8. STATUS ATUAL DA IMPLEMENTAÇÃO

## ✅ **FUNCIONALIDADES JÁ IMPLEMENTADAS**

### Backend PHP Existente:
- ✅ **Models básicos**: Planogram, Gondola, Section, Shelf, Segment
- ✅ **Controllers CRUD**: Criação, edição, visualização de planogramas
- ✅ **Análises básicas**: ABCAnalysisService, TargetStockAnalysisService, BCGAnalysisService
- ✅ **Sistema de rotas**: APIs e interfaces web configuradas
- ✅ **Multi-tenancy**: Sistema de isolamento por cliente/tenant
- ✅ **Autenticação**: Integração com sistema de usuários

### Frontend Vue.js Existente:
- ✅ **Editor visual**: Interface drag & drop para posicionamento manual
- ✅ **Sidebars dinâmicos**: Produtos à esquerda, propriedades à direita
- ✅ **Sistema de estado**: Store Pinia centralizado para gerenciamento
- ✅ **Análises visuais**: Modals com parâmetros configuráveis (ABC, BCG, Target Stock)
- ✅ **Responsividade**: Interface adaptável para diferentes telas
- ✅ **Histórico**: Sistema básico de undo/redo implementado

### 🆕 **GERAÇÃO AUTOMÁTICA DE PLANOGRAMA (RECÉM IMPLEMENTADA)**:
- ✅ **Botão "Gerar Automático"**: Integrado ao Info.vue com modal de filtros
- ✅ **AutoGenerateModal.vue**: Modal para configuração de filtros (dimensões, produtos não utilizados, etc.)
- ✅ **ScoreEngineService.php**: Sistema completo de pontuação por SKU (qtd, valor, margem)
- ✅ **AutoPlanogramController.php**: API completa para geração automática
- ✅ **Integração ABC Analysis**: Score com bônus/penalidades baseados na classificação ABC
- ✅ **Filtro por Categoria**: Busca produtos baseada na categoria do planograma (hierárquica)
- ✅ **Filtros Avançados**: Dimensões, produtos não utilizados, limite de quantidade
- ✅ **Distribuição Automática**: Algoritmo de posicionamento em segmentos/camadas
- ✅ **Conversão EAN→ID**: Sistema robusto de conversão de códigos de barras para IDs
- ✅ **Validação de Dimensões**: Filtro funcional para produtos com dimensões configuradas

## ⏳ **FUNCIONALIDADES A IMPLEMENTAR (conforme especificação)**

### Motor de Planograma Automático:
- ✅ **Score Engine**: Sistema de pontuação por SKU (qtd, valor, margem) - IMPLEMENTADO
- ✅ **Optimization Engine**: Distribuição automática respeitando dimensões e políticas - IMPLEMENTADO
- ⏳ **Template System**: Registry para lookup automático por departamento/categoria
- ⏳ **Anti-churn Algorithm**: Penalização de mudanças desnecessárias  
- ⏳ **Adjacency Rules**: Regras de produtos que devem ficar próximos
- ⏳ **Shelf Zones**: Configuração de zonas (nobre, intermediária, rodapé)

### Interface Avançada:
- ✅ **Modal de Filtros**: AutoGenerateModal.vue com configurações básicas - IMPLEMENTADO
- ⏳ **AutoPlanogramRunner.vue**: Painel operacional principal
- ⏳ **TemplateEditor.vue**: Editor drag & drop completo para templates
- ⏳ **PolicyEditor.vue**: Interface dinâmica para configuração de políticas
- ⏳ **VersionsCompare.vue**: Comparação visual antes/depois com heatmaps
- ⏳ **ScoreVisualization**: Visualização de scores e flags de confiança

### Macros Avançadas:
- ⏳ **Macro 1 Completa**: Mix/ABC com classificação dinâmica robusta
- ⏳ **Macro 2 Completa**: Estoque-alvo com nível de serviço por classe
- ⏳ **Forecast Engine**: Previsão robusta (média móvel, smoothing, mediana)
- ⏳ **Confidence Flags**: Sistema de flags para séries com alta variabilidade

---

# 8.1. DETALHAMENTO DA IMPLEMENTAÇÃO RECENTE

## 🚀 **GERAÇÃO AUTOMÁTICA DE PLANOGRAMA - FUNCIONALIDADE COMPLETA**

### **O que foi implementado:**

#### **Frontend (Vue.js + TypeScript):**
- **Info.vue**: Botão "Gerar Automático" integrado ao lado do botão "Inverter"
- **AutoGenerateModal.vue**: Modal completo com filtros configuráveis:
  - ✅ Apenas produtos com dimensões configuradas (padrão: ativo)
  - ✅ Apenas produtos não utilizados na gôndola atual
  - ✅ Produtos com vendas registradas
  - ✅ Produtos pendurávels (hangable)
  - ✅ Produtos empilháveis (stackable)  
  - ✅ Limite de quantidade de produtos (padrão: 20)
- **engineService.ts**: Serviço TypeScript para comunicação com API do motor

#### **Backend (Laravel + PHP):**
- **AutoPlanogramController.php**: Controller completo com endpoints:
  - `POST /api/plannerate/auto-planogram/{gondola}/calculate-scores`: Calcula scores e distribui produtos
  - Busca produtos baseada na categoria do planograma (hierárquica)
  - Aplicação de filtros (dimensões, não utilizados, limite)
  - Distribuição automática em segmentos/camadas
- **ScoreEngineService.php**: Motor de pontuação completo:
  - Cálculo de score baseado em quantidade, valor e margem
  - Integração com ABCAnalysisService para bônus/penalidades
  - Conversão robusta EAN → Product ID
  - Normalização de dados para comparação
- **Rotas API**: Endpoints configurados e funcionais

### **Como funciona:**
1. **Usuário clica "Gerar Automático"** → Abre modal de filtros
2. **Configura filtros** → Seleciona opções desejadas
3. **Confirma geração** → Frontend chama API do backend
4. **Backend busca produtos** → Filtra por categoria do planograma + filtros
5. **Calcula scores** → Usa ABCAnalysisService + ScoreEngineService
6. **Distribui produtos** → Posiciona automaticamente nos segmentos
7. **Retorna resultado** → Frontend atualiza interface com produtos posicionados

### **Filtros funcionais:**
- ✅ **Dimensões**: Só produtos com dimensões configuradas
- ✅ **Não utilizados**: Exclui produtos já posicionados na gôndola
- ✅ **Limite**: Controla quantidade máxima de produtos
- ✅ **Categoria hierárquica**: Busca na categoria do planograma + subcategorias

### **Validações implementadas:**
- ✅ Verificação se planograma tem categoria definida
- ✅ Tratamento de produtos sem dimensões
- ✅ Conversão segura EAN → Product ID
- ✅ Criação automática de segmentos se necessário
- ✅ Mensagens de erro amigáveis para o usuário

### **⚠️ LIMITAÇÃO ATUAL IDENTIFICADA:**
- ❌ **Sistema de Frentes/Facing**: Atualmente coloca apenas 1 frente por produto
- ❌ **Cálculo de Quantidade**: Não considera score para definir número de frentes
- ❌ **Otimização de Espaço**: Não aproveita largura total dos segmentos

### **🔧 CORREÇÃO NECESSÁRIA (Fase 2):**
- ⏳ **Cálculo Inteligente de Frentes**: Baseado em score + dimensões + espaço disponível
- ⏳ **Facing por Score**: Produtos A = mais frentes, Produtos C = menos frentes
- ⏳ **Aproveitamento de Espaço**: Calcular quantas frentes cabem por segmento
- ⏳ **Configuração de Facing**: Mínimo/máximo de frentes por classe ABC

---

# 9. ARQUITETURA TÉCNICA DETALHADA

## 🔧 **Stack Tecnológico**

### Backend:
- **Framework**: Laravel 10+ (PHP 8.2+)
- **Banco de Dados**: MySQL/PostgreSQL com soft deletes
- **APIs**: RESTful com recursos JSON
- **Multi-tenancy**: Isolamento por tenant_id
- **Autenticação**: Laravel Sanctum/Passport

### Frontend:
- **Framework**: Vue.js 3.3+ com Composition API
- **State Management**: Pinia 2.1+
- **Routing**: Vue Router 4.2+
- **Build Tool**: Vite 4+
- **UI Components**: ShadCN/UI + Tailwind CSS 3+
- **Ícones**: Lucide Vue Next
- **TypeScript**: Suporte completo com tipagem forte

## 🗂️ **Estrutura de Arquivos (Nova Organização)**

```
/packages/callcocam/plannerate/
├── src/
│   ├── Services/
│   │   ├── Analysis/ (EXISTENTE - a expandir)
│   │   │   ├── ABCAnalysisService.php
│   │   │   ├── TargetStockAnalysisService.php
│   │   │   └── BCGAnalysisService.php
│   │   └── Engine/ (NOVO - motor automático)
│   │       ├── ScoreEngineService.php ✅ IMPLEMENTADO
│   │       ├── TemplateRegistryService.php (NOVO)
│   │       ├── AntiChurnService.php (NOVO)
│   │       ├── AdjacencyRulesService.php (NOVO)
│   │       ├── ShelfZoneService.php (NOVO)
│   │       └── PlanogramOptimizationService.php (NOVO)
│   ├── Models/ (EXISTENTE - a expandir)
│   │   ├── Planogram.php
│   │   ├── Gondola.php
│   │   ├── Section.php
│   │   ├── Shelf.php
│   │   ├── Segment.php
│   │   ├── Template.php (NOVO)
│   │   ├── Policy.php (NOVO)
│   │   ├── PlanogramVersion.php (NOVO)
│   │   └── AuditLog.php (NOVO)
│   └── Http/Controllers/
│       └── Api/ (EXISTENTE - a expandir)
│           ├── AutoPlanogramController.php ✅ IMPLEMENTADO
│           ├── TemplateController.php (NOVO)
│           └── PolicyController.php (NOVO)
└── resources/js/
    ├── views/ (EXISTENTE - a expandir)
    │   ├── View.vue ✅ ATUALIZADO (botão "Gerar Automático")
    │   ├── AutoGenerateModal.vue ✅ IMPLEMENTADO
    │   ├── AutoPlanogramRunner.vue (NOVO)
    │   ├── TemplateEditor.vue (NOVO)
    │   ├── PolicyEditor.vue (NOVO)
    │   └── VersionsCompare.vue (NOVO)
    ├── components/engine/ (NOVO)
    │   ├── ScoreVisualization.vue (NOVO)
    │   ├── TemplateSelector.vue (NOVO)
    │   ├── ZoneConfig.vue (NOVO)
    │   ├── AdjacencyRules.vue (NOVO)
    │   └── PolicySliders.vue (NOVO)
    ├── services/ (EXISTENTE - a expandir)
    │   ├── engineService.ts ✅ IMPLEMENTADO
    │   └── analysisService.ts (expandir)
    └── store/ (EXISTENTE - a expandir)
        └── editor/ (expandir com engine state)
```

---

# 10. ESPECIFICAÇÕES DO MOTOR DE PLANOGRAMA

## 🎯 **Score Engine (Sistema de Pontuação)**

### Fórmula Base:
```typescript
Score = (W_qty * Qty_normalized) + 
        (W_val * Value_normalized) + 
        (W_margin * Margin_normalized) +
        ABC_bonus + 
        Stock_penalty +
        Anti_churn_penalty
```

### Parâmetros Configuráveis:
- **Pesos**: W_qty (0.30), W_val (0.30), W_margin (0.40)
- **Bônus ABC**: Classe A (+20%), Classe B (0%), Classe C (-10%)
- **Penalidade Estoque**: Déficit (-15%), Excesso (-5%)
- **Anti-churn**: Mudança de posição (-10%), Frente alterada (-5%)

## 🎨 **Template System**

### Tipos de Template:
1. **Departamental**: Por categoria de produto (ex: amaciantes, detergentes)
2. **Sazonal**: Para períodos específicos (natal, verão, volta às aulas)
3. **Promocional**: Para campanhas e ofertas especiais
4. **Padrão**: Templates genéricos reutilizáveis

### Modos de Aplicação:
- **Auto**: Template aplicado automaticamente
- **Hybrid**: Combina template com otimização automática
- **Hard**: Template rígido, sem modificações

## ⚙️ **Sistema de Regras**

### Regras de Adjacência:
```json
{
  "adjacency_rules": [
    {
      "product_a": "detergente_liquido",
      "product_b": "amaciante",
      "relationship": "must_be_adjacent",
      "distance_max": 1
    }
  ]
}
```

### Zonas de Prateleira:
- **Zona Nobre** (altura 100-160cm): Produtos A, alta margem
- **Zona Intermediária** (altura 80-100cm): Produtos B, médio giro
- **Zona Rodapé** (altura 40-80cm): Produtos C, baixo giro
- **Zona Espelho** (altura 160cm->)

## 🔢 **Sistema de Frentes/Facing**

### **Estrutura Atual:**
```
Segment (largura total disponível)
└── Layer (produto + quantity = frentes)
    ├── product_id: ID do produto
    ├── quantity: Número de frentes horizontais
    └── Cálculo: quantity × product_width ≤ segment_width
```

### **Algoritmo de Facing Inteligente (A Implementar):**
```typescript
facing_quantity = calculateOptimalFacing({
  product_score: number,        // Score calculado pelo engine
  product_width: number,        // Largura do produto (dimensões)
  segment_width: number,        // Largura disponível no segmento
  abc_class: 'A' | 'B' | 'C',  // Classificação ABC
  min_facing: number,           // Mínimo de frentes (padrão: 1)
  max_facing: number            // Máximo de frentes por classe
});
```

### **Regras de Facing por Classe:**
- **Classe A**: 2-6 frentes (produtos premium, alta visibilidade)
- **Classe B**: 1-3 frentes (produtos médios, presença moderada)
- **Classe C**: 1-2 frentes (produtos básicos, presença mínima)

### **Cálculo de Unidades Totais:**
```typescript
total_units = fronts × height_units × depth_units
// fronts: Layer.quantity (horizontal)
// height_units: Segment.quantity (vertical/empilhamento)
// depth_units: shelf_depth ÷ product_depth (profundidade)
```

---

# 11. INTERFACE DO USUÁRIO - DETALHAMENTO

## 🚀 **AutoPlanogramRunner.vue - Painel Operacional**

### Funcionalidades:
- **Botão "Executar Motor"**: Inicia otimização automática
- **Seletor de Template**: Dropdown com templates disponíveis
- **Configuração Dinâmica**: Sliders para pesos e parâmetros
- **Preview em Tempo Real**: Visualização antes de aplicar
- **Histórico de Execuções**: Lista com versões anteriores

### Layout:
```
┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐
│   CONFIGURAÇÃO  │ │    EXECUÇÃO     │ │   RESULTADOS    │
│                 │ │                 │ │                 │
│ ○ Pesos         │ │ ▶ Executar      │ │ 📊 KPIs         │
│ ○ Templates     │ │ ⏸ Pausar        │ │ 🎯 Score Total  │
│ ○ Regras        │ │ 🔄 Resetar      │ │ ⚠ Warnings      │
│ ○ Zonas         │ │ 💾 Salvar       │ │ ✅ Sucessos     │
└─────────────────┘ └─────────────────┘ └─────────────────┘
```

## 🎨 **TemplateEditor.vue - Editor de Templates**

### Funcionalidades:
- **Drag & Drop Visual**: Arrastar categorias para módulos/prateleiras
- **Configuração por Zona**: Definir regras por altura da prateleira
- **Preview 3D**: Visualização tridimensional do resultado
- **Biblioteca de Templates**: Galeria com templates pré-definidos
- **Exportar/Importar**: Compartilhar templates entre planogramas

## 📊 **VersionsCompare.vue - Comparação de Versões**

### Funcionalidades:
- **Heatmap de Mudanças**: Cores indicando alterações (verde=novo, vermelho=removido)
- **Diff de KPIs**: Comparação lado a lado dos indicadores
- **Timeline de Versões**: Histórico cronológico das modificações
- **Flags Visuais**: Marcadores para produtos "REM", "P$", "NEW"
- **Anotações**: Sistema de comentários por mudança

---

# 12. ROADMAP DE IMPLEMENTAÇÃO

## 🎯 **Fase 1 - Fundação (Semanas 1-2)** ✅ **CONCLUÍDA**
- ✅ Botão "Gerar Automático" adicionado ao Info.vue
- ✅ ScoreEngineService - Sistema básico de pontuação IMPLEMENTADO
- ✅ AutoGenerateModal.vue - Modal de filtros IMPLEMENTADO
- ✅ API Controller para o motor automático IMPLEMENTADO
- ✅ Integração Frontend ↔ Backend funcionando
- ✅ Sistema de filtros por categoria hierárquica
- ✅ Distribuição automática em segmentos/camadas
- ✅ Validação de produtos com dimensões

## 🎯 **Fase 2 - Motor Core (Semanas 3-4)** 🚧 **EM ANDAMENTO**
- ⏳ TemplateRegistryService e Model Template
- ⏳ AntiChurnService - Algoritmo de penalização
- ⏳ ShelfZoneService - Sistema de zonas
- ⏳ Interface básica do AutoPlanogramRunner
- ⏳ Expansão do modal de filtros com mais opções
- ⏳ Sistema de templates pré-definidos

## 🎯 **Fase 3 - Interface Avançada (Semanas 5-6)**
- ⏳ TemplateEditor.vue completo
- ⏳ PolicyEditor.vue com sliders dinâmicos
- ⏳ Sistema de visualização de scores
- ⏳ Integração completa Frontend ↔ Backend

## 🎯 **Fase 4 - Refinamentos (Semanas 7-8)**
- ⏳ VersionsCompare.vue com heatmaps
- ⏳ AuditLog completo com timestamp/usuário
- ⏳ Sistema de flags de confiabilidade
- ⏳ Solver de conflitos (ILP/CP básico)

## 🎯 **Fase 5 - Otimizações (Semanas 9-10)**
- ⏳ Performance tuning do motor
- ⏳ Testes A/B offline
- ⏳ Determinismo garantido via seed
- ⏳ Documentação completa e testes automatizados

---

# 13. CRITÉRIOS DE SUCESSO

## 📈 **KPIs de Performance**
- **Tempo de Execução**: < 30 segundos para gôndola com 100 SKUs
- **Melhoria de Score**: Mínimo 15% vs. posicionamento manual
- **Taxa de Aceitação**: > 80% das sugestões aprovadas pelos usuários
- **Redução de Trabalho**: 70% menos tempo na configuração manual

## ✅ **Funcionalidades Obrigatórias**
- Motor automático funcionando com score básico
- Sistema de templates operacional  
- Interface visual intuitiva e responsiva
- Versionamento e comparação de resultados
- Auditoria completa das mudanças
- Integração perfeita com sistema existente (zero breaking changes)

## 🚀 **Critérios de Qualidade**
- Código limpo com documentação em PT-BR
- Testes automatizados cobrindo cenários principais
- Performance otimizada para uso em produção
- Interface acessível e user-friendly
- Sistema robusto com tratamento de erros
- Compatibilidade total com funcionalidades existentes