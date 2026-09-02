import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { StatGrid } from '../components/StatGrid'
import { useAuth } from '../auth/AuthContext'
import { apiRequest, ApiError } from '../api/client'

type Dashboard = {
  stats: { reading_percent: number|string; bookmark_count:number|string; note_count:number|string; quiz_count:number|string; question_count:number|string }
  library: { slug:string; title:string; page_count:number|null; chunk_count:string }[]
}

export function ReaderHomePage(){
  const { user, token } = useAuth()
  const dash = useQuery({
    queryKey: ['dashboard'],
    queryFn: () => apiRequest<{data:Dashboard}>('/api/me/dashboard', {}, token).then(r => r.data),
  })
  const stats = dash.data?.stats
  const book = dash.data?.library[0]
  return <main className="workspace-page">
    <div className="page-title"><div><span className="eyebrow">Bonjour {user?.displayName?.split(' ')[0]}</span><h1>Reprendre votre parcours</h1></div>
      <Link className="primary" to={book ? `/lecture?slug=${book.slug}` : '/lecture'}>Continuer la lecture</Link></div>
    {dash.isError && <div className="form-error" role="alert">{dash.error instanceof ApiError ? dash.error.message : 'Impossible de charger l’aperçu.'}</div>}
    <StatGrid items={[
      {label:'Progression du livre', value:`${stats?.reading_percent ?? 0} %`, detail: book ? book.title : 'Aucun livre publié'},
      {label:'Quiz terminés', value: Number(stats?.quiz_count ?? 0), detail:'Tentatives clôturées'},
      {label:'Questions posées', value: Number(stats?.question_count ?? 0), detail:'Historique personnel'},
      {label:'Favoris', value: Number(stats?.bookmark_count ?? 0), detail:`${Number(stats?.note_count ?? 0)} notes`},
    ]}/>
    <section className="dashboard-grid">
      <article className="feature-card reading-card"><div><span className="card-kicker">Lecture actuelle</span><h2>{book?.title ?? 'Livre en attente de publication'}</h2>
        <p>{book ? 'Reprenez au dernier passage enregistré.' : 'Le lecteur s’activera après la publication de la première version.'}</p></div>
        <div className="progress-track"><span style={{width:`${Number(stats?.reading_percent ?? 0)}%`}}/></div>
        <Link to={book ? `/lecture?slug=${book.slug}` : '/lecture'}>Ouvrir le lecteur →</Link></article>
      <article className="feature-card"><span className="card-kicker">Prochaine action</span><h2>Explorer le contenu</h2><p>Posez une question au livre et consultez les pages utilisées dans la réponse.</p><Link to="/questions">Demander au livre →</Link></article>
      <article className="feature-card"><span className="card-kicker">Connaissances</span><h2>Tester votre lecture</h2><p>Les quiz publiés sont classés par titre et restent soumis à une validation humaine.</p><Link to="/quiz">Voir les quiz →</Link></article>
    </section>
  </main>
}
