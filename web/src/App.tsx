import { NavLink, Route, Routes } from 'react-router-dom'
import { useAuth } from './auth/AuthContext'
import { ProtectedRoute } from './auth/ProtectedRoute'
import { AdminUsersPage } from './pages/AdminUsersPage'
import { AuthPage } from './pages/AuthPage'

function HomePage() {
  return (
    <main className="page hero">
      <span className="eyebrow">Bibliothèque interactive</span>
      <h1>Lire, comprendre et approfondir.</h1>
      <p>
        Retrouvez le livre du Souverain Sacrificateur KADIMA, progressez à votre rythme
        et interrogez son contenu avec des réponses accompagnées de leurs sources.
      </p>
      <div className="actions">
        <button className="primary" type="button">Commencer la lecture</button>
        <button className="secondary" type="button">Poser une question</button>
      </div>
    </main>
  )
}

function Placeholder({ title }: { title: string }) {
  return <main className="page"><h1>{title}</h1><p>Ce module sera livré dans le lot fonctionnel correspondant.</p></main>
}

export default function App() {
  const { user, logout } = useAuth()
  return (
    <div className="app-shell">
      <header>
        <NavLink className="brand" to="/">SSK <span>Book</span></NavLink>
        <nav aria-label="Navigation principale">
          <NavLink to="/lecture">Lecture</NavLink>
          <NavLink to="/questions">Questions</NavLink>
          <NavLink to="/quiz">Quiz</NavLink>
          {user?.roles.includes('ROLE_ADMIN') && <NavLink to="/admin/utilisateurs">Administration</NavLink>}
        </nav>
        {user ? <button className="account" type="button" onClick={() => void logout()}>Se déconnecter</button> : <NavLink className="account" to="/connexion">Se connecter</NavLink>}
      </header>
      <Routes>
        <Route path="/" element={<HomePage />} />
        <Route path="/lecture" element={<Placeholder title="Lecture" />} />
        <Route path="/questions" element={<Placeholder title="Demander au livre" />} />
        <Route path="/quiz" element={<Placeholder title="Quiz" />} />
        <Route path="/connexion" element={<AuthPage mode="login" />} />
        <Route path="/inscription" element={<AuthPage mode="register" />} />
        <Route element={<ProtectedRoute admin />}>
          <Route path="/admin/utilisateurs" element={<AdminUsersPage />} />
        </Route>
      </Routes>
    </div>
  )
}
