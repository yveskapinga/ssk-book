import { NavLink, useLocation } from 'react-router-dom'
import { useAuth } from '../auth/AuthContext'
import { Brand } from './Brand'

const readerLinks = [
  { to: '/espace', label: 'Aperçu', mark: 'A' },
  { to: '/lecture', label: 'Lecture', mark: 'L' },
  { to: '/questions', label: 'Demander', mark: '?' },
  { to: '/quiz', label: 'Quiz', mark: 'Q' },
  { to: '/progression', label: 'Suivi', mark: 'S' },
] as const

export function AppShell({ children }: { children: React.ReactNode }) {
  const { user, logout } = useAuth()
  const location = useLocation()
  const immersive = location.pathname.startsWith('/lecture')
  const admin = user?.roles.includes('ROLE_ADMIN')
  const mobileLinks = admin
    ? [...readerLinks, { to: '/admin', label: 'Admin', mark: '+' } as const]
    : readerLinks

  return <div className={`workspace-shell${immersive ? ' immersive' : ''}`}>
    <aside className="sidebar">
      <Brand className="workspace-brand" to="/espace" />
      <div className="sidebar-section"><small>Mon espace</small>{readerLinks.map(link => <NavLink key={link.to} to={link.to}>{link.label}</NavLink>)}</div>
      {admin && <div className="sidebar-section"><small>Administration</small><NavLink to="/admin">Centre de pilotage</NavLink></div>}
      <div className="sidebar-user">
        <div className="avatar">{user?.displayName?.slice(0, 1).toUpperCase() ?? 'L'}</div>
        <div><strong>{user?.displayName ?? 'Lecteur'}</strong><small>{admin ? 'Administrateur' : 'Lecteur'}</small></div>
        <button type="button" aria-label="Se déconnecter" onClick={() => void logout()}>↗</button>
      </div>
    </aside>
    <div className="workspace-main">
      <header className="mobile-header">
        <Brand className="workspace-brand" to="/espace" />
        <div className="mobile-header-actions">
          <button type="button" className="ghost" onClick={() => void logout()}>Quitter</button>
          <span className="mobile-profile">{user?.displayName?.slice(0, 1).toUpperCase() ?? 'L'}</span>
        </div>
      </header>
      {children}
      <nav className={`mobile-tabs ${admin ? 'with-admin' : ''}`} aria-label="Navigation principale">
        {mobileLinks.map(link => <NavLink key={link.to} to={link.to}><span>{link.mark}</span>{link.label}</NavLink>)}
      </nav>
    </div>
  </div>
}
