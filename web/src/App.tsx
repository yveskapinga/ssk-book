import { Link, Navigate, Outlet, Route, Routes } from 'react-router-dom'
import { Brand } from './components/Brand'
import { useAuth } from './auth/AuthContext'
import { ProtectedRoute } from './auth/ProtectedRoute'
import { AppShell } from './components/AppShell'
import { AdminWorkspacePage } from './pages/AdminWorkspacePage'
import { AskBookPage } from './pages/AskBookPage'
import { AuthPage } from './pages/AuthPage'
import { ProgressWorkspacePage } from './pages/ProgressWorkspacePage'
import { QuizWorkspacePage } from './pages/QuizWorkspacePage'
import { ReaderHomePage } from './pages/ReaderHomePage'
import { ReadingWorkspacePage } from './pages/ReadingWorkspacePage'
import { DeleteAccountPage, PrivacyPage, TermsPage } from './pages/LegalPages'

function PublicHomePage() {
  const { user } = useAuth()
  if (user) return <Navigate to="/espace" replace />
  return <div className="public-shell"><header className="public-header"><Brand to="/" /><nav className="public-nav"><Link to="/legal/privacy">Confidentialité</Link><Link className="ghost" to="/connexion">Se connecter</Link><Link className="primary" to="/inscription">Créer un compte</Link></nav></header><main className="page hero"><span className="eyebrow">Bibliothèque interactive</span><h1>Lire, comprendre et approfondir.</h1><p>Découvrez le livre du Souverain Sacrificateur KADIMA, vérifiez vos connaissances et obtenez des réponses accompagnées de leurs pages sources.</p><div className="actions"><Link className="primary" to="/inscription">Commencer la lecture</Link><Link className="secondary" to="/connexion">J’ai déjà un compte</Link></div></main></div>
}

function WorkspaceLayout(){return <AppShell><Outlet/></AppShell>}

export default function App(){return <Routes>
  <Route path="/" element={<PublicHomePage/>}/>
  <Route path="/connexion" element={<AuthPage mode="login"/>}/>
  <Route path="/inscription" element={<AuthPage mode="register"/>}/>
  <Route path="/legal/privacy" element={<PrivacyPage/>}/>
  <Route path="/legal/terms" element={<TermsPage/>}/>
  <Route path="/legal/delete-account" element={<DeleteAccountPage/>}/>
  <Route element={<ProtectedRoute/>}><Route element={<WorkspaceLayout/>}>
    <Route path="/espace" element={<ReaderHomePage/>}/>
    <Route path="/lecture" element={<ReadingWorkspacePage/>}/>
    <Route path="/questions" element={<AskBookPage/>}/>
    <Route path="/quiz" element={<QuizWorkspacePage/>}/>
    <Route path="/progression" element={<ProgressWorkspacePage/>}/>
    <Route element={<ProtectedRoute admin/>}><Route path="/admin" element={<AdminWorkspacePage/>}/></Route>
  </Route></Route>
  <Route path="*" element={<Navigate to="/" replace/>}/>
</Routes>}
