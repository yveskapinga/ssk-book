import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { apiRequest, ApiError } from '../api/client'
import { useAuth } from '../auth/AuthContext'

type AdminUser = {
  id: string
  email: string
  displayName: string
  roles: string[]
  status: 'ACTIVE' | 'SUSPENDED'
  createdAt: string
  lastLoginAt: string | null
}

type UserList = { items: AdminUser[]; page: number; limit: number }

export function AdminUsersPage() {
  const { token, user: currentUser } = useAuth()
  const queryClient = useQueryClient()
  const [actionError, setActionError] = useState('')
  const [pendingId, setPendingId] = useState<string | null>(null)
  const users = useQuery({
    queryKey: ['admin-users'],
    queryFn: () => apiRequest<{ data: UserList }>('/api/admin/users?page=1&limit=100', {}, token).then((result) => result.data),
  })

  async function toggleStatus(user: AdminUser) {
    setActionError('')
    setPendingId(user.id)
    try {
      await apiRequest(`/api/admin/users/${user.id}/status`, {
        method: 'PATCH', body: JSON.stringify({ status: user.status === 'ACTIVE' ? 'SUSPENDED' : 'ACTIVE' }),
      }, token)
      await queryClient.invalidateQueries({ queryKey: ['admin-users'] })
    } catch (reason) {
      setActionError(reason instanceof ApiError ? reason.message : 'La modification a échoué.')
    } finally {
      setPendingId(null)
    }
  }

  return (
    <main className="page admin-page">
      <div className="page-heading">
        <div><span className="eyebrow">Administration</span><h1>Utilisateurs</h1></div>
        <span className="count">{users.data?.items.length ?? 0} comptes</span>
      </div>
      {actionError && <div className="form-error" role="alert">{actionError}</div>}
      {users.isPending && <p>Chargement des utilisateurs…</p>}
      {users.isError && <div className="form-error">Impossible de charger les utilisateurs.</div>}
      {users.data && (
        <div className="table-wrap">
          <table>
            <thead><tr><th>Utilisateur</th><th>Rôle</th><th>Statut</th><th>Dernière connexion</th><th><span className="sr-only">Action</span></th></tr></thead>
            <tbody>{users.data.items.map((user) => (
              <tr key={user.id}>
                <td><strong>{user.displayName}</strong><small>{user.email}</small></td>
                <td>{user.roles.includes('ROLE_ADMIN') ? 'Administrateur' : 'Lecteur'}</td>
                <td><span className={`status ${user.status.toLowerCase()}`}>{user.status === 'ACTIVE' ? 'Actif' : 'Suspendu'}</span></td>
                <td>{user.lastLoginAt ? new Date(user.lastLoginAt).toLocaleDateString('fr-FR') : 'Jamais'}</td>
                <td><button className="table-action" disabled={pendingId === user.id || currentUser?.id === user.id} onClick={() => toggleStatus(user)}>{user.status === 'ACTIVE' ? 'Suspendre' : 'Réactiver'}</button></td>
              </tr>
            ))}</tbody>
          </table>
        </div>
      )}
    </main>
  )
}
