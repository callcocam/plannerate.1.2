<?php
/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */
// config for Callcocam/Plannerate
return [
    /*
    |--------------------------------------------------------------------------
    | Configurações Gerais
    |--------------------------------------------------------------------------
    |
    | Configurações gerais para o funcionamento do Plannerate.
    |
    */

    // Prefixo para as rotas da API
    'api_prefix' => 'api/plannerate',

    // Prefixo para as rotas web
    'web_prefix' => 'plannerate',

    // Middleware para autenticação
    'middleware' => ['web', 'auth'],

    // Middleware para multi-tenancy (opcional)
    'tenant_middleware' => null,

    /*
    |--------------------------------------------------------------------------
    | Configurações de Armazenamento
    |--------------------------------------------------------------------------
    |
    | Configurações para o armazenamento de arquivos.
    |
    */

    // Disco para armazenamento de imagens
    'storage_disk' => 'public',

    // Pasta para armazenamento de imagens
    'storage_path' => 'plannerate',

    /*
    |--------------------------------------------------------------------------
    | Configurações de Visualização
    |--------------------------------------------------------------------------
    |
    | Configurações para a visualização de planogramas.
    |
    */

    // Escala padrão para renderização
    'default_scale' => 1,

    // Tema padrão
    'default_theme' => 'light',

    // Opções de temas disponíveis
    'themes' => [
        'light' => [
            'primary' => '#4f46e5',
            'secondary' => '#0ea5e9',
            'accent' => '#e11d48',
            'background' => '#f9fafb',
            'text' => '#1f2937',
        ],
        'dark' => [
            'primary' => '#818cf8',
            'secondary' => '#38bdf8',
            'accent' => '#fb7185',
            'background' => '#1f2937',
            'text' => '#f9fafb',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Configurações do Banco de Dados
    |--------------------------------------------------------------------------
    |
    | Configurações relacionadas ao banco de dados.
    |
    */

    // Usar soft deletes para exclusões
    'use_soft_deletes' => true,

    /*
    |--------------------------------------------------------------------------
    | Configurações do Frontend Vue
    |--------------------------------------------------------------------------
    |
    | Configurações para o frontend Vue.
    |
    */

    // Publicar automaticamente os assets do Vue durante a instalação
    'auto_publish_assets' => true,

    // Versão do Vue requerida
    'vue_version' => '^3.3.0',

    // Dependências do Vue
    'vue_dependencies' => [
        'vue' => '^3.3.0',
        'vue-router' => '^4.2.0',
        'pinia' => '^2.1.0',
    ],

    /*
    |--------------------------------------------------------------------------
    | Configurações do Motor de Planograma Automático
    |--------------------------------------------------------------------------
    |
    | Configurações para o sistema automático de geração de planogramas.
    |
    */

    // Motor de Score Engine
    'score_engine' => [
        // Pesos padrão para cálculo de scores
        'default_weights' => [
            'quantity' => 0.30,  // Peso quantidade de vendas
            'value' => 0.30,     // Peso valor de vendas
            'margin' => 0.40,    // Peso margem de lucro
        ],

        // Bônus por classificação ABC
        'abc_bonuses' => [
            'class_a' => 0.20,   // Bônus 20% para classe A
            'class_b' => 0.00,   // Sem bônus para classe B
            'class_c' => -0.10,  // Penalidade 10% para classe C
        ],

        // Penalidades de estoque
        'stock_penalties' => [
            'deficit' => -0.15,  // Penalidade 15% para déficit
            'excess' => -0.05,   // Penalidade 5% para excesso
        ],

        // Anti-churn (evitar mudanças desnecessárias)
        'anti_churn' => [
            'position_change' => -0.10,  // Penalidade 10% por mudança de posição
            'facing_change' => -0.05,    // Penalidade 5% por alteração de frentes
            'minimum_improvement' => 0.15, // Melhoria mínima para justificar mudança
        ],

        // Flags de confiabilidade
        'confidence_flags' => [
            'HIGH_VARIABILITY' => 'Alta variabilidade nas vendas (CV > 1.0)',
            'SHORT_SERIES' => 'Série histórica curta (< 30 dias)',
            'ZERO_STOCK' => 'Estoque zerado ou muito baixo',
            'NO_SALES_DATA' => 'Sem dados de vendas disponíveis',
            'OK' => 'Dados confiáveis para análise',
        ],
    ],

    // Sistema de Templates
    'templates' => [
        // Tipos de template disponíveis
        'types' => [
            'departmental' => 'Por departamento/categoria',
            'seasonal' => 'Sazonal (natal, verão, etc.)',
            'promotional' => 'Promocional/campanhas',
            'standard' => 'Padrão genérico',
        ],

        // Modos de aplicação
        'modes' => [
            'auto' => 'Automático - template aplicado sem modificações',
            'hybrid' => 'Híbrido - combina template com otimização',
            'hard' => 'Rígido - template não pode ser alterado',
        ],

        // Registry para lookup automático
        'registry' => [
            'amaciantes' => [
                'concentrados_top' => true,   // Concentrados em cima
                'diluidos_bottom' => true,    // Diluídos embaixo
                'variation_modules' => [1, 2, 3], // Variação 1/2/3+ módulos
            ],
            'detergentes' => [
                'liquidos_eye_level' => true, // Líquidos na altura dos olhos
                'pos_grouped' => true,        // Agrupar por tipo/marca
            ],
        ],
    ],

    // Zonas de Prateleira
    'shelf_zones' => [
        'nobre' => [
            'height_min' => 120,  // 120cm mínimo
            'height_max' => 160,  // 160cm máximo
            'description' => 'Zona nobre - produtos classe A, alta margem',
            'priority_multiplier' => 1.5, // Multiplicador de prioridade
        ],
        'intermediaria' => [
            'height_min' => 80,   // 80cm mínimo
            'height_max' => 120,  // 120cm máximo
            'description' => 'Zona intermediária - produtos classe B, médio giro',
            'priority_multiplier' => 1.0,
        ],
        'rodape' => [
            'height_min' => 40,   // 40cm mínimo
            'height_max' => 80,   // 80cm máximo
            'description' => 'Zona rodapé - produtos classe C, baixo giro',
            'priority_multiplier' => 0.7,
        ],
    ],

    // Regras de Adjacência
    'adjacency_rules' => [
        // Produtos que devem ficar próximos
        'must_be_adjacent' => [
            ['detergente_liquido', 'amaciante'],
            ['shampoo', 'condicionador'],
        ],
        
        // Produtos que devem ficar separados
        'must_be_separated' => [
            ['produtos_limpeza', 'alimentos'],
        ],
        
        // Distância máxima permitida
        'max_distance' => 2, // Máximo 2 posições de distância
    ],

    // Configurações de Performance
    'performance' => [
        'max_execution_time' => 300,    // 5 minutos máximo
        'max_products_per_batch' => 500, // Máximo de produtos por lote
        'cache_results_minutes' => 30,   // Cache resultados por 30min
        'enable_logging' => true,        // Habilitar logs detalhados
    ],

    // Configurações de Auditoria
    'audit' => [
        'track_all_changes' => true,     // Rastrear todas as mudanças
        'keep_versions' => 10,           // Manter últimas 10 versões
        'log_user_actions' => true,      // Log ações do usuário
        'export_format' => 'json',       // Formato de export (json/csv)
    ],
];
