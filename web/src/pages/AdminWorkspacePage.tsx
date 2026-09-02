import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { apiRequest, ApiError } from '../api/client'
import { useAuth } from '../auth/AuthContext'
import { DataTable, type Column } from '../components/DataTable'
import { PageTabs } from '../components/PageTabs'
import { StatGrid } from '../components/StatGrid'
import { AdminBookImportPage } from './AdminBookImportPage'
import { AdminReviewPage } from './AdminReviewPage'

type Tab='overview'|'import'|'review'|'users'
type UserRow={id:string;displayName:string;email:string;roles:string[];status:'ACTIVE'|'SUSPENDED';createdAt:string;lastLoginAt:string|null}
type Version={id:string;book_title:string;version_number:number;label:string;status:string;page_count:number|null;chunk_count:string;embedded_count:string;created_at:string}

export function AdminWorkspacePage(){const{token,user:actor}=useAuth();const cache=useQueryClient();const[tab,setTab]=useState<Tab>('overview');const[error,setError]=useState('')
  const users=useQuery({queryKey:['admin-users'],queryFn:()=>apiRequest<{data:{items:UserRow[]}}>('/api/admin/users?limit=100',{},token).then(r=>r.data.items)})
  const versions=useQuery({queryKey:['book-versions'],queryFn:()=>apiRequest<{data:{items:Version[]}}>('/api/admin/book-versions?limit=100',{},token).then(r=>r.data.items)})
  async function changeStatus(row:UserRow){setError('');try{await apiRequest(`/api/admin/users/${row.id}/status`,{method:'PATCH',body:JSON.stringify({status:row.status==='ACTIVE'?'SUSPENDED':'ACTIVE'})},token);await cache.invalidateQueries({queryKey:['admin-users']})}catch(reason){setError(reason instanceof ApiError?reason.message:'Modification impossible.')}}
  const columns:Column<UserRow>[]=[{key:'user',label:'Utilisateur',priority:'primary',render:r=><><strong>{r.displayName}</strong><small>{r.email}</small></>},{key:'role',label:'Rôle',render:r=>r.roles.includes('ROLE_ADMIN')?'Administrateur':'Lecteur'},{key:'status',label:'Statut',render:r=><span className={`status ${r.status.toLowerCase()}`}>{r.status==='ACTIVE'?'Actif':'Suspendu'}</span>},{key:'login',label:'Dernière connexion',priority:'secondary',render:r=>r.lastLoginAt?new Date(r.lastLoginAt).toLocaleDateString('fr-FR'):'Jamais'}]
  const pending=versions.data?.filter(v=>v.status==='REVIEW_REQUIRED')??[];const published=versions.data?.filter(v=>v.status==='PUBLISHED')??[];const embedded=versions.data?.reduce((n,v)=>n+Number(v.embedded_count),0)??0;const chunks=versions.data?.reduce((n,v)=>n+Number(v.chunk_count),0)??0
  return <main className="workspace-page admin-workspace"><div className="page-title"><div><span className="eyebrow">Centre de pilotage</span><h1>Administration du contenu</h1></div><button className="primary" onClick={()=>setTab('import')}>Importer une version</button></div>
    <PageTabs tabs={[{id:'overview',label:'Pilotage'},{id:'import',label:'1. Importer'},{id:'review',label:'2. Contrôler et publier',count:pending.length},{id:'users',label:'Utilisateurs',count:users.data?.length}]} active={tab} onChange={setTab}/>
    {error&&<div className="form-error">{error}</div>}
    {tab==='overview'&&<><StatGrid items={[{label:'Versions publiées',value:published.length,tone:'good'},{label:'En attente de contrôle',value:pending.length,tone:pending.length?'warn':'neutral'},{label:'Passages indexés',value:`${embedded}/${chunks}`},{label:'Utilisateurs actifs',value:users.data?.filter(u=>u.status==='ACTIVE').length??0}]}/><div className="operations-grid"><section className="operation-card"><span>01</span><div><h2>Importer le PDF</h2><p>Créer une version et extraire les pages.</p></div><button onClick={()=>setTab('import')}>Ouvrir →</button></section><section className="operation-card"><span>02</span><div><h2>Contrôler les passages</h2><p>Inspecter l’extraction avant indexation.</p></div><button onClick={()=>setTab('review')}>Ouvrir →</button></section><section className="operation-card"><span>03</span><div><h2>Indexer et approuver</h2><p>Calculer les vecteurs puis publier.</p></div><button onClick={()=>setTab('review')}>Ouvrir →</button></section></div><section className="activity-panel"><div className="section-heading"><div><h2>Versions récentes</h2><p>Suivi du flux éditorial</p></div></div>{versions.data?.slice(0,5).map(v=><div className="activity-row" key={v.id}><div><strong>{v.book_title}</strong><small>Version {v.version_number} · {v.label}</small></div><span className={`status ${v.status==='PUBLISHED'?'active':''}`}>{v.status}</span><span>{v.page_count??'—'} pages</span></div>)}</section></>}
    {tab==='import'&&<AdminBookImportPage embedded/>}
    {tab==='review'&&<AdminReviewPage embedded/>}
    {tab==='users'&&<section><div className="section-heading"><div><h2>Accès et comptes</h2><p>Rechercher un utilisateur puis ouvrir le menu trois-points pour agir.</p></div></div><DataTable rows={users.data??[]} columns={columns} identify={r=>r.id} searchText={r=>`${r.displayName} ${r.email} ${r.status} ${r.roles.join(' ')}`} actions={r=>[{label:'Voir l’activité',onClick:()=>{}},{label:r.status==='ACTIVE'?'Suspendre':'Réactiver',onClick:()=>void changeStatus(r),danger:r.status==='ACTIVE',disabled:r.id===actor?.id}]} empty="Aucun utilisateur trouvé."/></section>}
  </main>
}
