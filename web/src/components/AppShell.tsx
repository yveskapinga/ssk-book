import { NavLink } from 'react-router-dom'
import { useAuth } from '../auth/AuthContext'

const readerLinks = [
  ['/espace', 'Aperçu'], ['/lecture', 'Lecture'], ['/questions', 'Demander'], ['/quiz', 'Quiz'], ['/progression', 'Progression'],
] as const

export function AppShell({ children }: { children: React.ReactNode }) {
  const { user, logout } = useAuth()
  const admin = user?.roles.includes('ROLE_ADMIN')
  return <div className="workspace-shell">
    <aside className="sidebar">
      <NavLink className="workspace-brand" to="/espace"><span>SSK</span><strong>Book</strong></NavLink>
      <div className="sidebar-section"><small>Mon espace</small>{readerLinks.map(([to,label])=><NavLink key={to} to={to}>{label}</NavLink>)}</div>
      {admin&&<div className="sidebar-section"><small>Administration</small><NavLink to="/admin">Centre de pilotage</NavLink></div>}
      <div className="sidebar-user"><div className="avatar">{user?.displayName?.slice(0,1).toUpperCase()??'L'}</div><div><strong>{user?.displayName??'Lecteur'}</strong><small>{admin?'Administrateur':'Lecteur'}</small></div><button aria-label="Se déconnecter" onClick={()=>void logout()}>↗</button></div>
    </aside>
    <div className="workspace-main">
      <header className="mobile-header"><NavLink className="workspace-brand" to="/espace"><span>SSK</span><strong>Book</strong></NavLink><NavLink className="mobile-profile" to={admin?'/admin':'/progression'}>{user?.displayName?.slice(0,1).toUpperCase()??'L'}</NavLink></header>
      {children}
      <nav className="mobile-tabs">{readerLinks.slice(0,4).map(([to,label])=><NavLink key={to} to={to}><span>{label.slice(0,1)}</span>{label}</NavLink>)}</nav>
    </div>
  </div>
}
