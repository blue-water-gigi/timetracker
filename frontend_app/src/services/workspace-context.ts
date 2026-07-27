import { api } from '@/services/api'
import type { Project, Timesheet, User } from '@/types/domain'

export async function accessibleProjects(user: User): Promise<Project[]> {
  if (user.systemRole === 'employee') {
    const workspaceId = user.workspace?.id
    return workspaceId ? (await api.projects(workspaceId, 1, true)).data : []
  }

  const organizations = (await api.organizations()).data
  const workspaceResponses = await Promise.all(
    organizations.map((organization) => api.workspaces(organization.id)),
  )
  const workspaces = workspaceResponses.flatMap((response) => response.data)
  const projectResponses = await Promise.all(
    workspaces.map((workspace) => api.projects(workspace.id)),
  )

  return projectResponses.flatMap((response) => response.data)
}

export async function projectTimesheets(projects: Project[]): Promise<Timesheet[]> {
  const responses = await Promise.all(
    projects.map(async (project) => {
      const workspaceId = project.workspace?.id
      if (!workspaceId) {
        return []
      }

      const timesheets = (await api.timesheets(workspaceId, project.id)).data

      return timesheets.map((timesheet) => ({
        ...timesheet,
        project: {
          ...(timesheet.project ?? project),
          workspace: project.workspace,
        },
      }))
    }),
  )

  return responses.flat()
}
