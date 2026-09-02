import { Link } from 'react-router-dom'
import { StatGrid } from '../components/StatGrid'
import { useAuth } from '../auth/AuthContext'

export function ReaderHomePage(){const{user}=useAuth();return <main className="workspace-page"><div className="page-title"><div><span className="eyebrow">Bonjour {user?.displayName?.split(' ')[0]}</span><h1>Reprendre votre parcours</h1></div><Link className="primary button-link" to="/lecture">Continuer la lecture</Link></div>
  <StatGrid items={[{label:'Progression du livre',value:'0 %',detail:'Commencez le premier chapitre'},{label:'Quiz terminés',value:0,detail:'Aucune tentative'},{label:'Questions posées',value:0,detail:'Historique personnel'},{label:'Série de lecture',value:'0 jour',detail:'Votre rythme'}]}/>
  <section className="dashboard-grid"><article className="feature-card reading-card"><div><span className="card-kicker">Lecture actuelle</span><h2>Le Prophète KADIMA Bakenge</h2><p>Votre progression apparaîtra ici dès votre première session.</p></div><div className="progress-track"><span style={{width:'0%'}}/></div><Link to="/lecture">Ouvrir le lecteur →</Link></article>
  <article className="feature-card"><span className="card-kicker">Prochaine action</span><h2>Explorer le contenu</h2><p>Posez une question au livre et consultez les pages utilisées dans la réponse.</p><Link to="/questions">Demander au livre →</Link></article>
  <article className="feature-card"><span className="card-kicker">Connaissances</span><h2>Tester votre lecture</h2><p>Les quiz publiés seront classés par partie et niveau.</p><Link to="/quiz">Voir les quiz →</Link></article></section>
</main>}
