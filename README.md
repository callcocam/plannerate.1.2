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

## Documentação

Para documentação completa, visite [a documentação oficial](https://github.com/callcocam/plannerate/docs).

## Contribuição

Contribuições são bem-vindas! Por favor, veja [CONTRIBUTING](CONTRIBUTING.md) para detalhes.

## Créditos

- [Claudio Campos](https://github.com/callcocam)
- [Todos os Contribuidores](../../contributors)

## Licença

Este projeto é licenciado sob a licença MIT. Veja o arquivo [LICENSE.md](LICENSE.md) para mais detalhes.
