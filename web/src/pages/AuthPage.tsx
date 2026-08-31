import { useState, type FormEvent } from 'react'
import { Link, Navigate, useNavigate } from 'react-router-dom'
import { ApiError } from '../api/client'
import { useAuth } from '../auth/AuthContext'

export function AuthPage({ mode }: { mode: 'login' | 'register' }) {
  const { user, login, register } = useAuth()
  const navigate = useNavigate()
  const [displayName, setDisplayName] = useState('')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState('')
  const [submitting, setSubmitting] = useState(false)

  if (user) return <Navigate to="/" replace />

  async function submit(event: FormEvent) {
    event.preventDefault()
    setSubmitting(true)
    setError('')
    try {
      if (mode === 'register') await register(displayName, email, password)
      else await login(email, password)
      navigate('/')
    } catch (reason) {
      setError(reason instanceof ApiError ? reason.message : 'Impossible de terminer cette opération.')
    } finally {
      setSubmitting(false)
    }
  }

  const registering = mode === 'register'
  return (
    <main className="auth-page">
      <form className="auth-card" onSubmit={submit}>
        <span className="eyebrow">SSK Book</span>
        <h1>{registering ? 'Créer un compte' : 'Bienvenue'}</h1>
        <p>{registering ? 'Créez votre espace personnel de lecture.' : 'Connectez-vous pour reprendre votre lecture.'}</p>
        {error && <div className="form-error" role="alert">{error}</div>}
        {registering && <label>Nom complet<input value={displayName} onChange={(e) => setDisplayName(e.target.value)} minLength={2} maxLength={120} required /></label>}
        <label>Adresse e-mail<input type="email" value={email} onChange={(e) => setEmail(e.target.value)} autoComplete="email" required /></label>
        <label>Mot de passe<input type="password" value={password} onChange={(e) => setPassword(e.target.value)} autoComplete={registering ? 'new-password' : 'current-password'} minLength={10} required /></label>
        <button className="primary full" disabled={submitting}>{submitting ? 'Veuillez patienter…' : registering ? 'Créer mon compte' : 'Se connecter'}</button>
        <small>{registering ? <>Déjà inscrit ? <Link to="/connexion">Se connecter</Link></> : <>Nouveau lecteur ? <Link to="/inscription">Créer un compte</Link></>}</small>
      </form>
    </main>
  )
}
