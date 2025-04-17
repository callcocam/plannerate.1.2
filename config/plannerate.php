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
];
