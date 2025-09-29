import { createRouter, createWebHistory, RouteRecordRaw } from 'vue-router'
import HomePage from '../pages/HomePage.vue'
import AboutPage from '../pages/ListPage.vue'
import BurgerPage from "../pages/BurgerPage.vue";

const routes: Array<RouteRecordRaw> = [
    {
        path: '/',
        name: 'Home',
        component: HomePage,
    },
    {
        path: '/list',
        name: 'List',
        component: AboutPage,
    },
    {
        path: '/burgerorder',
        name: 'Burger Page',
        component: BurgerPage,
    },
]

const router = createRouter({
    history: createWebHistory(),
    routes,
})

export default router
