export type NotificationType = 'timesheet.submitted' | 'timesheet.reviewed'

export interface TimesheetNotificationData {
  timesheetId: number
  workspaceId: number
  projectId: number
  authorId?: number
  reviewerId?: number
  decision?: 'approved' | 'rejected'
  submittedAt?: string
  reviewedAt?: string
  reviewComment?: string | null
}

export interface AppNotification {
  id: string
  type: NotificationType | string
  payload: TimesheetNotificationData
  isRead: boolean
  readAt: string | null
  createdAt: string | null
}
