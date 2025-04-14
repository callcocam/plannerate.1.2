import { createRouter, createWebHistory, RouteRecordRaw } from 'vue-router';
import NotFound from '../views/NotFound.vue';

// Define your routes
const routes: Array<RouteRecordRaw> = [
    {
        path: '/plannerate',
        name: 'plannerate.home',
        component: () => import('../views/Home.vue'),
        redirect: { name: 'plannerate.index' },
        children: [
            {
                path: '',
                name: 'plannerate.index',
                component: () => import('../views/List.vue'),
            },
            {
                path: 'cadastrar',
                name: 'plannerate.create',
                component: () => import('./../views/Create.vue')
            },
            {
                path: ':id/editar',
                name: 'plannerate.edit',
                component: () => import('../views/Edit.vue'),
                props: true
            },
            {
                path: ':id',
                name: 'plannerate.view',
                component: () => import('../views/View.vue'),
                props: true,
                children: [
                    {
                        path: 'gondola/criar',
                        name: 'plannerate.gondola.create',
                        component: () => import('./../views/gondolas/Create.vue'),
                        props: true,

                    },
                    {
                        name: 'gondola.view',
                        path: 'gondola/:gondolaId',
                        component: () => import('./../views/gondolas/Gondola.vue'),
                        props: true,
                        children: [
                            {
                                path: 'criar',
                                name: 'gondola.create',
                                component: () => import('./../views/gondolas/Create.vue'),
                                props: true,

                            },
                            {
                                path: 'editar',
                                name: 'gondola.edit',
                                component: () => import('./../views/gondolas/Edit.vue'),
                                props: true,

                            },
                        ]
                    }
                ]
            }
        ]
    },

    { path: '/:pathMatch(.*)*', name: 'NotFound', component: NotFound },
    // Add more routes as needed
];

// Create the router instance
const router = createRouter({
    // @ts-ignore
    history: createWebHistory(),
    routes
});

// Navigation guards (optional)
// router.beforeEach((to, from, next) => {
//     // Add your navigation guard logic here
//     next();
// });

export default router;