import { useEffect, useId, useState } from 'react'
import { NavLink, useLocation } from 'react-router-dom'
import { useAuth } from '../auth/AuthContext'
import { Brand } from './Brand'

const readerLinks = [
  { to: '/espace', label: 'Aperçu' },
  { to: '/lecture', label: 'Lecture' },
  { to: '/questions', label: 'Demander au livre' },
  { to: '/quiz', label: 'Quiz' },
  { to: '/progression', label: 'Suivi' },
] as const

function NavSections({ onNavigate }: { onNavigate?: () => void }) {
  const { user } = useAuth()
  const admin = user?.roles.includes('ROLE_ADMIN')

  return <>
    <div className="sidebar-section">
      <small>Mon espace</small>
      {readerLinks.map(link => (
        <NavLink key={link.to} to={link.to} onClick={onNavigate}>{link.label}</NavLink>
      ))}
    </div>
    {admin && (
      <div className="sidebar-section">
        <small>Administration</small>
        <NavLink to="/admin" onClick={onNavigate}>Centre de pilotage</NavLink>
      </div>
    )}
  </>
}

export function AppShell({ children }: { children: React.ReactNode }) {
  const { user, logout } = useAuth()
  const location = useLocation()
  const immersive = location.pathname.startsWith('/lecture')
  const admin = user?.roles.includes('ROLE_ADMIN')
  const [menuOpen, setMenuOpen] = useState(false)
  const drawerId = useId()

  useEffect(() => {
    setMenuOpen(false)
  }, [location.pathname])

  useEffect(() => {
    if (!menuOpen) return
    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape') setMenuOpen(false)
    }
    document.addEventListener('keydown', onKey)
    const previous = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    return () => {
      document.removeEventListener('keydown', onKey)
      document.body.style.overflow = previous
    }
  }, [menuOpen])

  return <div className={`workspace-shell${immersive ? ' immersive' : ''}${menuOpen ? ' menu-open' : ''}`}>
    <aside className="sidebar desktop-sidebar">
      <Brand className="workspace-brand" to="/espace" />
      <NavSections />
      <div className="sidebar-user">
        <div className="avatar">{user?.displayName?.slice(0, 1).toUpperCase() ?? 'L'}</div>
        <div><strong>{user?.displayName ?? 'Lecteur'}</strong><small>{admin ? 'Administrateur' : 'Lecteur'}</small></div>
        <button type="button" aria-label="Se déconnecter" onClick={() => void logout()}>↗</button>
      </div>
    </aside>

    <div className="workspace-main">
      <header className="mobile-header">
        <button
          type="button"
          className="menu-toggle"
          aria-label={menuOpen ? 'Fermer le menu' : 'Ouvrir le menu'}
          aria-expanded={menuOpen}
          aria-controls={drawerId}
          onClick={() => setMenuOpen(open => !open)}
        >
          <span className="menu-toggle-bars" aria-hidden="true" />
        </button>
        <Brand className="workspace-brand" to="/espace" />
        <div className="mobile-header-actions">
          <span className="mobile-profile" aria-hidden="true">{user?.displayName?.slice(0, 1).toUpperCase() ?? 'L'}</span>
        </div>
      </header>

      {children}
    </div>

    <div
      className={`mobile-drawer-backdrop${menuOpen ? ' is-open' : ''}`}
      hidden={!menuOpen}
      onClick={() => setMenuOpen(false)}
    />
    <aside
      id={drawerId}
      className={`sidebar mobile-drawer${menuOpen ? ' is-open' : ''}`}
      aria-hidden={!menuOpen}
      aria-label="Navigation"
    >
      <div className="mobile-drawer-head">
        <Brand className="workspace-brand" to="/espace" onClick={() => setMenuOpen(false)} />
        <button type="button" className="drawer-close" aria-label="Fermer le menu" onClick={() => setMenuOpen(false)}>×</button>
      </div>
      <NavSections onNavigate={() => setMenuOpen(false)} />
      <div className="sidebar-user">
        <div className="avatar">{user?.displayName?.slice(0, 1).toUpperCase() ?? 'L'}</div>
        <div><strong>{user?.displayName ?? 'Lecteur'}</strong><small>{admin ? 'Administrateur' : 'Lecteur'}</small></div>
        <button type="button" aria-label="Se déconnecter" onClick={() => void logout()}>↗</button>
      </div>
    </aside>
  </div>
}
