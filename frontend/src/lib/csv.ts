/** Starter files for the two imports, with an example row each, in the format the server reads. */
export const SHOPS_TEMPLATE = [
  'code,name,address,town,lat,lng,phone,contact,notes,usual order,mon,tue,wed,thu,fri,sat,sun',
  'A-001,Market Ardi,Rruga e Kavajës 112,Tiranë,41.3232,19.8012,069 123 4567,Ardian Hoxha,Entrance at the back,3850,10:00-21:00,07:00-21:00,07:00-21:00,07:00-21:00,07:00-21:00,07:00-21:00,closed',
  'A-002,Furra Artan,Rruga Medar Shtylla 8,Tiranë,41.3271,19.8330,068 555 0101,Artan Vata,,2400,"06:30-13:00, 15:00-21:00","06:30-13:00, 15:00-21:00",,,,,',
].join('\n')

export const HISTORY_TEMPLATE = ['shop,date,time,open', 'A-001,2026-08-03,08:40,no', 'A-001,2026-08-03,10:25,yes', 'Furra Artan,04.08.2026,07:10,yes'].join('\n')

/** Saves text as a file from the browser. */
export function downloadText(name: string, text: string, type = 'text/csv;charset=utf-8') {
  const url = URL.createObjectURL(new Blob(['﻿' + text], { type }))
  const link = document.createElement('a')
  link.href = url
  link.download = name
  document.body.appendChild(link)
  link.click()
  link.remove()
  setTimeout(() => URL.revokeObjectURL(url), 1000)
}
