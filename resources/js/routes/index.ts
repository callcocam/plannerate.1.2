import { createRouter, createWebHistory, RouteRecordRaw } from 'vue-router';
import NotFound from '../views/NotFound.vue';

// Define your routes
const routes: Array<RouteRecordRaw> = [
    {
        path: '/plannerate/show/:id',
        name: 'plannerate.home',
        component: () => import('../views/Home.vue'),
        redirect: { name: 'plannerate.index' },
        children: [
            {
                path: '',
                name: 'plannerate.index',
                component: () => import('./../views/Create.vue'),
                props: true,
                children: [

                    {
                        path: 'criar',
                        name: 'plannerate.create',
                        component: () => import('./../views/gondolas/Create.vue'),
                        props: true,

                    }
                ]

            },
            {
                name: 'gondola.view',
                path: 'gondola/:gondolaId',
                component: () => import('./../views/View.vue'),
                props: true,
                redirect: { name: 'plannerate.gondola.view' },
                children: [
                    { 
                        path: '',
                        name: 'plannerate.gondola.view',
                        component: () => import('./../views/gondolas/Gondola.vue'),
                        props: true,

                    },
                    {
                        path: 'criar',
                        name: 'plannerate.gondola.create',
                        component: () => import('./../views/gondolas/Create.vue'),
                        props: true,

                    },
                    {
                        path: 'editar',
                        name: 'plannerate.gondola.edit',
                        component: () => import('./../views/gondolas/Edit.vue'),
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