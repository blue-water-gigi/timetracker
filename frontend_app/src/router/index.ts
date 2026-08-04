import { createRouter, createWebHistory } from 'vue-router'

import { useAuthStore } from '@/stores/auth'

export const router = createRouter({
  history: createWebHistory(),
  scrollBehavior: () => ({ top: 0 }),
  routes: [
    {
      path: '/',
      redirect: '/app',
    },
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/auth/LoginView.vue'),
      meta: { guestOnly: true },
    },
    {
      path: '/register',
      name: 'register',
      component: () => import('@/views/auth/RegisterView.vue'),
      meta: { guestOnly: true },
    },
    {
      path: '/app',
      component: () => import('@/components/layout/AppShell.vue'),
      meta: { requiresAuth: true },
      children: [
        {
          path: '',
          name: 'dashboard',
          component: () => import('@/views/DashboardView.vue'),
        },
        {
          path: 'organizations',
          name: 'organizations',
          component: () => import('@/views/organizations/OrganizationsView.vue'),
          meta: { adminOnly: true },
        },
        {
          path: 'organizations/:organizationId',
          name: 'organization',
          component: () => import('@/views/organizations/OrganizationView.vue'),
          meta: { adminOnly: true },
        },
        {
          path: 'organizations/:organizationId/workspaces/:workspaceId',
          name: 'workspace',
          component: () => import('@/views/workspaces/WorkspaceView.vue'),
          meta: { adminOnly: true },
        },
        {
          path: 'projects',
          name: 'my-projects',
          component: () => import('@/views/projects/MyProjectsView.vue'),
        },
        {
          path: 'workspaces/:workspaceId/projects/:projectId',
          name: 'project',
          component: () => import('@/views/projects/ProjectView.vue'),
        },
        {
          path: 'workspaces/:workspaceId/projects/:projectId/timesheets/:timesheetId',
          name: 'timesheet',
          component: () => import('@/views/timesheets/TimesheetView.vue'),
        },
        {
          path: 'my-time',
          name: 'my-time',
          component: () => import('@/views/timesheets/MyTimeView.vue'),
        },
        {
          path: 'reviews',
          name: 'reviews',
          component: () => import('@/views/timesheets/ReviewInboxView.vue'),
        },
        {
          path: 'profile',
          name: 'profile',
          component: () => import('@/views/ProfileView.vue'),
        },
      ],
    },
    {
      path: '/:pathMatch(.*)*',
      component: () => import('@/views/NotFoundView.vue'),
    },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  await auth.initialize()

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (to.meta.guestOnly && auth.isAuthenticated) {
    return { name: 'dashboard' }
  }

  if (to.meta.adminOnly && !auth.isAdmin) {
    return { name: 'dashboard' }
  }

  return true
})
