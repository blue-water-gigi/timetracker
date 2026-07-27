import { request } from '@/services/api-client'
import type { ApiResponse, CollectionResponse } from '@/types/api'
import type {
  Organization,
  OrganizationPayload,
  Project,
  ProjectMember,
  ProjectPayload,
  ProjectRole,
  TimeEntry,
  TimeEntryPayload,
  Timesheet,
  TimesheetPayload,
  User,
  Workspace,
  WorkspacePayload,
} from '@/types/domain'

const json = (body: unknown): string => JSON.stringify(body)

export const api = {
  me: () => request<ApiResponse<User>>('/me'),
  login: (payload: { email: string; password: string }) =>
    request<ApiResponse<User>>('/login', { method: 'POST', body: json(payload) }),
  registerAdmin: (payload: {
    first_name?: string
    last_name?: string
    email: string
    password: string
  }) => request<ApiResponse<User>>('/register/admin', { method: 'POST', body: json(payload) }),
  registerEmployee: (payload: {
    first_name?: string
    last_name?: string
    join_code: string
    email: string
    password: string
  }) => request<ApiResponse<User>>('/register/employee', { method: 'POST', body: json(payload) }),
  logout: () => request<void>('/logout', { method: 'DELETE' }),

  organizations: (page = 1) =>
    request<CollectionResponse<Organization>>(`/organizations?page=${page}`),
  organization: (organizationId: number) =>
    request<ApiResponse<Organization>>(`/organizations/${organizationId}`),
  createOrganization: (payload: OrganizationPayload) =>
    request<ApiResponse<Organization>>('/organizations', {
      method: 'POST',
      body: json(payload),
    }),
  updateOrganization: (organizationId: number, payload: Partial<OrganizationPayload>) =>
    request<ApiResponse<Organization>>(`/organizations/${organizationId}`, {
      method: 'PATCH',
      body: json(payload),
    }),
  archiveOrganization: (organizationId: number) =>
    request<void>(`/organizations/${organizationId}`, { method: 'DELETE' }),

  workspaces: (organizationId: number, page = 1) =>
    request<CollectionResponse<Workspace>>(
      `/organizations/${organizationId}/workspaces?page=${page}`,
    ),
  workspace: (organizationId: number, workspaceId: number) =>
    request<ApiResponse<Workspace>>(`/organizations/${organizationId}/workspaces/${workspaceId}`),
  createWorkspace: (organizationId: number, payload: WorkspacePayload) =>
    request<ApiResponse<Workspace>>(`/organizations/${organizationId}/workspaces`, {
      method: 'POST',
      body: json(payload),
    }),
  updateWorkspace: (
    organizationId: number,
    workspaceId: number,
    payload: Partial<WorkspacePayload>,
  ) =>
    request<ApiResponse<Workspace>>(`/organizations/${organizationId}/workspaces/${workspaceId}`, {
      method: 'PATCH',
      body: json(payload),
    }),
  archiveWorkspace: (organizationId: number, workspaceId: number) =>
    request<void>(`/organizations/${organizationId}/workspaces/${workspaceId}`, {
      method: 'DELETE',
    }),
  rotateJoinCode: (organizationId: number, workspaceId: number) =>
    request<ApiResponse<Workspace> & { meta: { joinCode: string } }>(
      `/organizations/${organizationId}/workspaces/${workspaceId}/rotate-join-code`,
      { method: 'POST', body: json({}) },
    ),

  projects: (workspaceId: number, page = 1, mine = false) =>
    request<CollectionResponse<Project>>(
      `/workspaces/${workspaceId}/${mine ? 'my-projects' : 'projects'}?page=${page}`,
    ),
  project: (workspaceId: number, projectId: number) =>
    request<ApiResponse<Project>>(`/workspaces/${workspaceId}/projects/${projectId}`),
  createProject: (workspaceId: number, payload: ProjectPayload) =>
    request<ApiResponse<Project>>(`/workspaces/${workspaceId}/projects`, {
      method: 'POST',
      body: json(payload),
    }),
  updateProject: (workspaceId: number, projectId: number, payload: Partial<ProjectPayload>) =>
    request<ApiResponse<Project>>(`/workspaces/${workspaceId}/projects/${projectId}`, {
      method: 'PATCH',
      body: json(payload),
    }),
  archiveProject: (workspaceId: number, projectId: number) =>
    request<void>(`/workspaces/${workspaceId}/projects/${projectId}`, { method: 'DELETE' }),

  members: (workspaceId: number, projectId: number, page = 1) =>
    request<CollectionResponse<ProjectMember>>(
      `/workspaces/${workspaceId}/projects/${projectId}/members?page=${page}`,
    ),
  createMember: (
    workspaceId: number,
    projectId: number,
    payload: { user_id: number; project_role: ProjectRole; active?: boolean },
  ) =>
    request<ApiResponse<ProjectMember>>(
      `/workspaces/${workspaceId}/projects/${projectId}/members`,
      { method: 'POST', body: json(payload) },
    ),
  updateMember: (
    workspaceId: number,
    projectId: number,
    membershipId: number,
    payload: { project_role?: ProjectRole; active?: boolean },
  ) =>
    request<ApiResponse<ProjectMember>>(
      `/workspaces/${workspaceId}/projects/${projectId}/members/${membershipId}`,
      { method: 'PATCH', body: json(payload) },
    ),
  removeMember: (workspaceId: number, projectId: number, membershipId: number) =>
    request<void>(`/workspaces/${workspaceId}/projects/${projectId}/members/${membershipId}`, {
      method: 'DELETE',
    }),

  timesheets: (workspaceId: number, projectId: number, page = 1) =>
    request<CollectionResponse<Timesheet>>(
      `/workspaces/${workspaceId}/projects/${projectId}/timesheets?page=${page}`,
    ),
  timesheet: (workspaceId: number, projectId: number, timesheetId: number) =>
    request<ApiResponse<Timesheet>>(
      `/workspaces/${workspaceId}/projects/${projectId}/timesheets/${timesheetId}`,
    ),
  createTimesheet: (workspaceId: number, projectId: number, payload: TimesheetPayload) =>
    request<ApiResponse<Timesheet>>(`/workspaces/${workspaceId}/projects/${projectId}/timesheets`, {
      method: 'POST',
      body: json(payload),
    }),
  updateTimesheet: (
    workspaceId: number,
    projectId: number,
    timesheetId: number,
    payload: Partial<TimesheetPayload>,
  ) =>
    request<ApiResponse<Timesheet>>(
      `/workspaces/${workspaceId}/projects/${projectId}/timesheets/${timesheetId}`,
      { method: 'PATCH', body: json(payload) },
    ),
  submitTimesheet: (workspaceId: number, projectId: number, timesheetId: number) =>
    request<ApiResponse<Timesheet>>(
      `/workspaces/${workspaceId}/projects/${projectId}/timesheets/${timesheetId}/submit`,
      { method: 'POST', body: json({}) },
    ),
  reviewTimesheet: (
    workspaceId: number,
    projectId: number,
    timesheetId: number,
    decision: 'approve' | 'reject',
    reviewComment?: string,
  ) =>
    request<ApiResponse<Timesheet>>(
      `/workspaces/${workspaceId}/projects/${projectId}/timesheets/${timesheetId}/${decision}`,
      { method: 'POST', body: json({ review_comment: reviewComment || null }) },
    ),

  createEntry: (
    workspaceId: number,
    projectId: number,
    timesheetId: number,
    payload: TimeEntryPayload,
  ) =>
    request<ApiResponse<TimeEntry>>(
      `/workspaces/${workspaceId}/projects/${projectId}/timesheets/${timesheetId}/entries`,
      { method: 'POST', body: json(payload) },
    ),
  updateEntry: (
    workspaceId: number,
    projectId: number,
    timesheetId: number,
    entryId: number,
    payload: Partial<TimeEntryPayload>,
  ) =>
    request<ApiResponse<TimeEntry>>(
      `/workspaces/${workspaceId}/projects/${projectId}/timesheets/${timesheetId}/entries/${entryId}`,
      { method: 'PATCH', body: json(payload) },
    ),
  removeEntry: (workspaceId: number, projectId: number, timesheetId: number, entryId: number) =>
    request<void>(
      `/workspaces/${workspaceId}/projects/${projectId}/timesheets/${timesheetId}/entries/${entryId}`,
      { method: 'DELETE' },
    ),
}
