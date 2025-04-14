import './../css/app.css';
import type { App } from 'vue'
import Plannerate from './App.vue';
import ConfirmModal from './components/Confirm.vue';
import router from './routes';
// Vamos comfigurar o pinia para o plannerate
// @ts-ignore
import { createPinia } from 'pinia';

const pinia = createPinia();
// @ts-ignore 
interface PluginOptions {
    [key: string]: any
}
interface ComponentDefinition {
    default: any;
    [key: string]: any;
}
 

const install = (app: App, options: PluginOptions = {}) => {
    const componentRegistry: string[] = [];
    app.component('Plannerate', Plannerate);
    app.component('v-plannerate', Plannerate);

    app.component('ConfirmModal', ConfirmModal)
    app.component('v-confirm-modal', ConfirmModal)

    Object.entries<ComponentDefinition>(
        // @ts-ignore
        import.meta.glob<ComponentDefinition>('./components/ui/**/*.vue', { eager: true })
    ).forEach(([path, definition]) => {
        const originalName = path.split('/').pop()?.replace(/\.\w+$/, '') || '';
        // OriginalName => SidebarItem
        if (componentRegistry.indexOf(originalName) === -1) {
            app.component(originalName, definition.default);
        }
        componentRegistry.push(originalName);
        // console.log('Component registered:', originalName);
    });

    app.use(router);

    app.use(pinia);

    app.config.globalProperties.$plannerate = options
}

export default {
    install
}