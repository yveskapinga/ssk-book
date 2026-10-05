import { useEffect, useRef, useState } from 'react'
import { useInstallPrompt } from '../pwa/useInstallPrompt'

export function InstallPrompt() {
  const { installed, canPrompt, ios, install } = useInstallPrompt()
  const [nudged, setNudged] = useState(false)
  const bannerRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    const root = document.documentElement
    if (installed) {
      root.classList.remove('has-install-prompt')
      root.style.setProperty('--install-prompt-offset', '0px')
      return
    }

    const applyOffset = () => {
      const height = bannerRef.current?.offsetHeight ?? 0
      root.classList.add('has-install-prompt')
      root.style.setProperty('--install-prompt-offset', `${height}px`)
    }

    applyOffset()
    const observer = new ResizeObserver(applyOffset)
    if (bannerRef.current) observer.observe(bannerRef.current)
    window.addEventListener('resize', applyOffset)
    return () => {
      observer.disconnect()
      window.removeEventListener('resize', applyOffset)
      root.classList.remove('has-install-prompt')
      root.style.setProperty('--install-prompt-offset', '0px')
    }
  }, [installed, ios, canPrompt, nudged])

  if (installed) return null

  async function onInstall() {
    if (canPrompt) {
      await install()
      return
    }
    setNudged(true)
    bannerRef.current?.scrollIntoView({ block: 'start' })
  }

  return (
    <div ref={bannerRef} className={`install-prompt${nudged ? ' is-nudged' : ''}`} role="status" aria-live="polite">
      <img className="install-prompt-logo" src="/logo.png" width={40} height={40} alt="" />
      <div className="install-prompt-copy">
        <strong>Installez SSK Book</strong>
        <p>
          {ios
            ? 'Sur iPhone ou iPad : touchez Partager, puis « Sur l’écran d’accueil ». Ce bandeau reste affiché tant que l’application n’est pas installée.'
            : canPrompt
              ? 'Ajoutez l’application sur l’écran d’accueil pour la rouvrir en un geste. Ce bandeau reste affiché tant qu’elle n’est pas installée.'
              : 'Dans le menu du navigateur (⋮), choisissez « Installer l’application ». Ce bandeau reste affiché tant qu’elle n’est pas installée.'}
        </p>
      </div>
      {ios ? (
        <ol className="install-prompt-steps">
          <li>Partager</li>
          <li>Sur l’écran d’accueil</li>
          <li>Ajouter</li>
        </ol>
      ) : (
        <button type="button" className="install-prompt-action" onClick={() => void onInstall()}>
          Installer maintenant
        </button>
      )}
    </div>
  )
}
