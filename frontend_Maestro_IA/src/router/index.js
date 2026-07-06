import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import DashboardView from '../views/DashboardView.vue'
import AdminAccessView from '../views/AdminAccessView.vue'
import LoginView from '../views/LoginView.vue'
import RegisterView from '../views/RegisterView.vue'
import StudentDashboardView from '../views/StudentDashboardView.vue'
import StudentHomeView from '../views/StudentHomeView.vue'
import StudentTaskDetailView from '../views/StudentTaskDetailView.vue'

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/',
      redirect: '/dashboard',
    },
    {
      path: '/dashboard',
      name: 'dashboard',
      component: DashboardView,
      meta: { requiresAuth: true },
    },
    {
      path: '/login',
      name: 'login',
      component: LoginView,
      meta: { guest: true },
    },
    {
      path: '/register',
      name: 'register',
      component: RegisterView,
      meta: { guest: true },
    },
    {
      path: '/admin',
      name: 'admin.dashboard',
      component: DashboardView,
      meta: { requiresAuth: true, role: 'admin' },
    },
    {
      path: '/admin/access',
      name: 'admin.access',
      component: AdminAccessView,
      meta: { requiresAuth: true, role: 'admin' },
    },
    {
      path: '/student/home',
      name: 'student.home',
      component: StudentHomeView,
      meta: { requiresAuth: true, role: 'student' },
    },
    {
      path: '/student',
      name: 'student.dashboard',
      component: StudentDashboardView,
      meta: { requiresAuth: true, role: 'student' },
    },
    {
      path: '/student/tasks/:id',
      name: 'student.tasks.show',
      component: StudentTaskDetailView,
      meta: { requiresAuth: true, role: 'student' },
    },
    {
      path: '/:pathMatch(.*)*',
      name: 'not-found',
      redirect: '/dashboard',
    },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (auth.token && !auth.user) {
    await auth.fetchMe()
  }

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login' }
  }

  if (to.name === 'dashboard' && auth.isAuthenticated) {
    return { name: auth.defaultRouteName }
  }

  if (to.meta.role && auth.user?.role !== to.meta.role) {
    return { name: auth.defaultRouteName }
  }

  if (to.meta.guest && auth.isAuthenticated) {
    return { name: auth.defaultRouteName }
  }

  return true
})
