import type { Component } from 'vue'
import { PhChatCircleDots, PhGearSix, PhHouse } from '@phosphor-icons/vue'

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

export const NAV: NavItem[] = [
  { name: 'home', label: 'nav.home', icon: PhHouse, primary: true },
  { name: 'messages', label: 'nav.messages', icon: PhChatCircleDots, primary: true },
  { name: 'settings', label: 'nav.settings', icon: PhGearSix },
]
