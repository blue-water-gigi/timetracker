export type SystemRole = 'admin' | 'employee'
export type ProjectRole = 'participant' | 'senior' | 'manager' | 'project_lead'
export type TimesheetStatus = 'draft' | 'submitted' | 'approved' | 'rejected'

export interface Timestamps {
  createdAt?: string | null
  updatedAt?: string | null
  submittedAt?: string | null
  reviewedAt?: string | null
}

export interface EmbeddedCollection<T> {
  data: T[]
}

export interface User {
  id: number
  firstName?: string
  lastName?: string
  systemRole: SystemRole
  email: string
  workspace?: Workspace
  ownedOrganizations?: EmbeddedCollection<Organization>
  timestamps: Timestamps
}

export interface Organization {
  id: number
  owner?: User
  name: string
  slug: string
  workspaces?: EmbeddedCollection<Workspace>
  deletedAt?: string
  timestamps: Timestamps
  workspacesCount?: number
  usersCount?: number
}

export interface Workspace {
  id: number
  name: string
  slug: string
  description?: string
  active: boolean
  organization?: Organization
  projects?: EmbeddedCollection<Project>
  timestamps: Timestamps
  usersCount?: number
}

export interface Project {
  id: number
  workspace?: Workspace
  createdBy?: User
  updatedBy?: User
  name: string
  description?: string
  slug: string
  active: boolean
  periodStart?: string
  periodEnd?: string
  timestamps: Timestamps
  memberships?: EmbeddedCollection<ProjectMember>
  membershipsCount?: number
}

export interface ProjectMember {
  id: number
  project?: Project
  user?: User
  projectRole: ProjectRole
  approvalRank: number
  active: boolean
  timestamps: Timestamps
}

export interface TimeEntry {
  id: number
  timesheet?: Timesheet
  workDate: string
  description?: string
  hours: string
  isOvertime: boolean
  timestamps: Timestamps
}

export interface Timesheet {
  id: number
  workspace?: Workspace
  project?: Project
  createdBy?: User
  periodStart: string
  periodEnd: string
  status: TimesheetStatus
  entries?: TimeEntry[]
  reviewedBy?: User
  reviewComment?: string
  entriesCount?: number
  timestamps: Timestamps
}

export interface OrganizationPayload {
  name: string
  slug: string
}

export interface WorkspacePayload {
  name: string
  slug: string
  description?: string | null
  active?: boolean
}

export interface ProjectPayload {
  name: string
  slug: string
  description?: string | null
  active?: boolean
  period_start?: string | null
  period_end?: string | null
}

export interface TimesheetPayload {
  period_start: string
  period_end: string
}

export interface TimeEntryPayload {
  work_date: string
  description?: string | null
  hours: number | string
  is_overtime?: boolean
}
