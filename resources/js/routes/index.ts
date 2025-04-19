import { createRouter, createWebHistory, RouteRecordRaw } from 'vue-router';
import NotFound from '../views/NotFound.vue';

// Define your routes
const routes: Array<RouteRecordRaw> = [
    {
        path: '/plannerate/editor/:id',
        name: 'plannerate.home',
        component: () => import('@plannerate/views/Home.vue'),
        props: true,
        redirect: { name: 'plannerate.index' },
        children: [
            {
                path: '',
                name: 'plannerate.index',
                component: () => import('@plannerate/views/Create.vue'),
                props: route => ({ 
                    id: route.params.id,
                    record: route.params.record 
                }),
                children: [
                    {
                        path: 'criar',
                        name: 'plannerate.create',
                        component: () => import('@plannerate/views/gondolas/form/Create.vue'),
                        props: true,
                    }
                ]
            },
            {
                path: 'gondola/:gondolaId',
                name: 'gondola.view',
                component: () => import('@plannerate/views/View.vue'), 
                redirect: { name: 'plannerate.gondola.view' },  
                children: [
                    { 
                        path: '',
                        name: 'plannerate.gondola.view',
                        component: () => import('@plannerate/views/gondolas/Gondola.vue'),
                        props: true,
                    },
                    {
                        path: 'criar',
                        name: 'plannerate.gondola.create',
                        component: () => import('@plannerate/views/gondolas/form/Create.vue'),
                        props: true,
                    },
                    {
                        path: 'editar',
                        name: 'plannerate.gondola.add_section',
                        component: () => import('@plannerate/views/gondolas/form/AddSectionModal.vue'),
                        props: true,
                    },
                ]
            }
        ]
    },

    { path: '/:pathMatch(.*)*', name: 'NotFound', component: NotFound },
    // Add more routes as needed
];

// Create the router instance
const router = createRouter({
    history: createWebHistory(),
    routes,
    // Usar algoritmo de correspondência de rotas mais preciso
    strict: true,
    
});
 

export default router;