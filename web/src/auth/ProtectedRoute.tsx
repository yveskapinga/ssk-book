import { Navigate, Outlet } from 'react-router-dom'
import { useAuth } from './AuthContext'

export function ProtectedRoute({ admin = false }: { admin?: boolean }) {
  const { user, initializing } = useAuth()
  if (initializing) return <main className="page"><p>Chargement…</p></main>
  if (!user) return <Navigate to="/connexion" replace />
  if (admin && !user.roles.includes('ROLE_ADMIN')) return <Navigate to="/" replace />
  return <Outlet />
}
