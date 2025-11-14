/// <reference types="vite/client" />

import './../css/app.css';
import './echo'; // Importar configuração do Echo
import type { App, Component } from 'vue'
import Plannerate from './App.vue';
import ConfirmModal from './components/Confirm.vue';
import AlertConfirm from './components/AlertConfirm.vue';
import router from './routes';
import { createPinia } from 'pinia';

// Definição de interfaces para tipagem adequada
export interface PluginOptions {
    baseUrl?: string;
    tenant?: string | null;
    apiPath?: string;
    csrfToken?: string;
    [key: string]: any;
}

export interface ComponentMap {
    [key: string]: Component;
}

// Re-exportando componentes principais para uso direto
export { default as PlannerateApp } from './App.vue';
export { default as ConfirmModal } from './components/Confirm.vue';
export { default as PlannerateRouter } from './routes';

// Criação do pinia para gerenciamento de estado
const pinia = createPinia();

/**
 * Função de instalação do plugin Plannerate
 * @param app Instância do aplicativo Vue
 * @param options Opções de configuração do plugin
 */
const install = (app: App, options: PluginOptions = {}): void => {
    // Registro de componentes globais
    registerMainComponents(app);

    // Registro automático de componentes UI
    registerUIComponents(app);
    // console.log('plannerate');
    // Configuração de plugins
    app.use(router);
    app.use(pinia);

    // Configuração global
    app.config.globalProperties.$plannerate = options;

    // Configuração de injeção para acesso em componentes
    app.provide('plannerateOptions', options);
}

/**
 * Registra os componentes principais do Plannerate
 */
const registerMainComponents = (app: App): void => {
    app.component('Plannerate', Plannerate);
    app.component('v-plannerate', Plannerate);
    app.component('ConfirmModal', ConfirmModal);
    app.component('v-confirm-modal', ConfirmModal);
    app.component('AlertConfirm', AlertConfirm);
    app.component('v-alert-confirm', AlertConfirm);
    app.component('queue-monitor', () => import('./components/QueueMonitor.vue'));
}

/**
 * Registra automaticamente todos os componentes UI
 */
const registerUIComponents = (app: App): void => {
    const componentRegistry: string[] = [];

    // Importação de componentes UI usando glob do Vite
    Object.entries<{ default: Component }>(
        import.meta.glob<{ default: Component }>('./components/ui/**/*.vue', { eager: true })
    ).forEach(([path, definition]) => {
        const componentFileName = path.split('/').pop() || '';
        const originalName = componentFileName.replace(/\.\w+$/, '');

        if (componentRegistry.indexOf(originalName) === -1) {
            app.component(originalName, definition.default);
            componentRegistry.push(originalName);
        }
    });
}

// Exportando o plugin com método install
export default {
    install
}