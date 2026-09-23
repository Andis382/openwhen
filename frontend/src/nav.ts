import type { Component } from 'vue'
import {
  PhChartBar,
  PhClockCounterClockwise,
  PhGearSix,
  PhMapTrifold,
  PhPath,
  PhSteeringWheel,
  PhStorefront,
  PhSunHorizon,
} from '@phosphor-icons/vue'

export type NavItem = {
  /** route name */
  name: string
  /** i18n key for the label */
  label: string
  icon: Component
  /** shown in the phone's bottom bar (max 4); others go under "More" */
  primary?: boolean
  roles?: string[]
}

const PLANNERS = ['OWNER', 'DISPATCHER']

export const NAV: NavItem[] = [
  { name: 'home', label: 'nav.home', icon: PhSunHorizon, primary: true, roles: PLANNERS },
  { name: 'plan', label: 'nav.plan', icon: PhMapTrifold, primary: true, roles: PLANNERS },
  { name: 'shops', label: 'nav.shops', icon: PhStorefront, primary: true, roles: PLANNERS },
  { name: 'routes', label: 'nav.routes', icon: PhPath, primary: true, roles: PLANNERS },
  { name: 'reports', label: 'nav.reports', icon: PhChartBar, roles: PLANNERS },
  { name: 'driver', label: 'nav.driverToday', icon: PhSteeringWheel, primary: true, roles: ['DRIVER'] },
  { name: 'driver-trips', label: 'nav.driverTrips', icon: PhClockCounterClockwise, primary: true, roles: ['DRIVER'] },
  { name: 'settings', label: 'nav.settings', icon: PhGearSix },
]
