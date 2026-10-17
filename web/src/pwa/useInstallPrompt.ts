import { useCallback, useEffect, useState } from 'react'

export type BeforeInstallPromptEvent = Event & {
  prompt: () => Promise<void>
  userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>
}

type Listener = () => void

const listeners = new Set<Listener>()
let deferredPrompt: BeforeInstallPromptEvent | null = null

function notify(): void {
  listeners.forEach((listener) => listener())
}

export function isStandaloneDisplay(): boolean {
  if (typeof window === 'undefined') return false
  const nav = window.navigator as Navigator & { standalone?: boolean }
  return window.matchMedia('(display-mode: standalone)').matches
    || window.matchMedia('(display-mode: fullscreen)').matches
    || nav.standalone === true
    || document.referrer.startsWith('android-app://')
}

export function isIosDevice(): boolean {
  if (typeof window === 'undefined') return false
  const nav = window.navigator
  if (/iPad|iPhone|iPod/.test(nav.userAgent)) return true
  return nav.platform === 'MacIntel' && nav.maxTouchPoints > 1
}

if (typeof window !== 'undefined') {
  window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault()
    deferredPrompt = event as BeforeInstallPromptEvent
    notify()
  })
  window.addEventListener('appinstalled', () => {
    deferredPrompt = null
    notify()
  })
  window.matchMedia('(display-mode: standalone)').addEventListener('change', notify)
}

export function useInstallPrompt() {
  const [installed, setInstalled] = useState(() => isStandaloneDisplay())
  const [canPrompt, setCanPrompt] = useState(() => deferredPrompt !== null)
  const [ios] = useState(() => isIosDevice())

  useEffect(() => {
    const sync = () => {
      setInstalled(isStandaloneDisplay())
      setCanPrompt(deferredPrompt !== null)
    }
    sync()
    listeners.add(sync)
    return () => { listeners.delete(sync) }
  }, [])

  const install = useCallback(async (): Promise<'accepted' | 'dismissed' | 'unavailable'> => {
    if (!deferredPrompt) return 'unavailable'
    const promptEvent = deferredPrompt
    deferredPrompt = null
    setCanPrompt(false)
    await promptEvent.prompt()
    const { outcome } = await promptEvent.userChoice
    if (outcome === 'accepted') setInstalled(true)
    notify()
    return outcome
  }, [])

  return { installed, canPrompt, ios, install }
}
