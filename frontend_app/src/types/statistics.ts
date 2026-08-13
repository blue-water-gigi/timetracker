import type { ProjectRole } from '@/types/domain'

export type StatisticsGranularity = 'day' | 'week' | 'month' | 'quarter'

export interface StatisticsQuery {
  from: string
  to: string
  granularity: StatisticsGranularity
}

export interface StatisticsPeriod extends StatisticsQuery {
  status: 'approved'
}

export interface StatisticsTimelinePoint {
  bucketStart: string
  hours: number
  overtimeHours: number
}

export interface StatisticsActivityDay {
  date: string
  hours: number
  overtimeHours: number
}

export interface StatisticsProjectShare {
  projectId: number
  name: string
  hours: number
  sharePercent: number
}

export interface PersonalStatistics {
  period: StatisticsPeriod
  summary: {
    totalHours: number
    previousHours: number
    deltaHours: number
    deltaPercent: number | null
    overtimeHours: number
    overtimeSharePercent: number
    pendingHours: number
  }
  timeline: StatisticsTimelinePoint[]
  dailyActivity: StatisticsActivityDay[]
  projects: StatisticsProjectShare[]
}

export interface WorkspaceStatisticsEmployee {
  userId: number
  name: string
  hours: number
  overtimeHours: number
}

export interface WorkspaceStatistics {
  period: StatisticsPeriod
  summary: {
    totalHours: number
    overtimeHours: number
    overtimeSharePercent: number
  }
  timeline: StatisticsTimelinePoint[]
  projects: StatisticsProjectShare[]
  employees: WorkspaceStatisticsEmployee[]
}

export interface RecentApprovedTimesheet {
  timesheetId: number
  userId: number
  userName: string
  periodStart: string
  periodEnd: string
  hours: number
  approvedAt: string | null
}

export interface ProjectStatistics {
  period: StatisticsPeriod
  summary: {
    totalHours: number
    overtimeHours: number
    overtimeSharePercent: number
    activeMembersCount: number
  }
  timeline: StatisticsTimelinePoint[]
  dailyActivity: StatisticsActivityDay[]
  recentApprovedTimesheets: RecentApprovedTimesheet[]
}

export interface ProjectTeamStatisticsEmployee {
  userId: number
  name: string
  role: ProjectRole | null
  active: boolean
  hours: number
  overtimeHours: number
  sharePercent: number
}

export interface ProjectTeamStatistics {
  period: StatisticsPeriod
  summary: {
    totalHours: number
    overtimeHours: number
    overtimeSharePercent: number
    contributorsCount: number
  }
  employees: ProjectTeamStatisticsEmployee[]
}
