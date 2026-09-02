import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { apiRequest, ApiError } from '../api/client'
import { useAuth } from '../auth/AuthContext'
import { DataTable, type Column } from '../components/DataTable'

type Version = { id: string; book_title: string; version_number: number; label: string; status: string; page_count: number | null; chunk_count: string; embedded_count: string; latest_decision:string|null }
type Chunk = { id:string; position:number; start_page:number; end_page:number; content:string; embedded:boolean }

export function AdminReviewPage({ embedded=false }: { embedded?:boolean }) {
  const { token } = useAuth(); const cache = useQueryClient(); const [busy, setBusy] = useState<string | null>(null); const [error, setError] = useState(''); const [preview,setPreview]=useState<{versionId:string;chunks:Chunk[]}|null>(null)
  const versions = useQuery({ queryKey: ['book-versions'], queryFn: () => apiRequest<{data:{items:Version[]}}>('/api/admin/book-versions?limit=100', {}, token).then(r => r.data.items) })
  async function action(version: Version, type: 'embed'|'approve'|'reject'|'publish') {
    setBusy(version.id+type); setError('')
    try {
      const path = type === 'embed' ? 'embeddings' : type === 'publish' ? 'publish' : 'review'
      const body = type === 'approve' ? JSON.stringify({decision:'APPROVED'}) : type === 'reject' ? JSON.stringify({decision:'REJECTED', notes:'Extraction rejetée lors de la revue administrative.'}) : undefined
      await apiRequest(`/api/admin/book-versions/${version.id}/${path}`, {method:'POST', body}, token)
      await cache.invalidateQueries({queryKey:['book-versions']})
    } catch (reason) { setError(reason instanceof ApiError ? reason.message : 'Opération impossible.') }
    finally { setBusy(null) }
  }
  async function inspect(versionId:string){setBusy(versionId+'inspect');setError('');try{const result=await apiRequest<{data:{items:Chunk[]}}>(`/api/admin/book-versions/${versionId}/chunks?limit=30`,{},token);setPreview({versionId,chunks:result.data.items})}catch(reason){setError(reason instanceof ApiError?reason.message:'Aperçu indisponible.')}finally{setBusy(null)}}
  const columns:Column<Version>[]=[
    {key:'version',label:'Livre et version',priority:'primary',render:v=><><strong>{v.book_title}</strong><small>Version {v.version_number} · {v.label}</small></>},
    {key:'volume',label:'Contenu',render:v=><>{v.page_count??'—'} pages<small>{v.chunk_count} passages</small></>},
    {key:'index',label:'Indexation',render:v=><><strong>{v.embedded_count}/{v.chunk_count}</strong><small>embeddings</small></>},
    {key:'status',label:'Statut',render:v=><span className={`status ${v.status==='PUBLISHED'?'active':''}`}>{v.status}</span>},
  ]
  const Root=embedded?'div':'main'
  return <Root className={embedded?'embedded-workflow':'page admin-page'}>
    {!embedded&&<div className="page-heading"><div><span className="eyebrow">Administration</span><h1>Revue des versions</h1></div></div>}
    {error && <div className="form-error">{error}</div>}
    {versions.isPending && <p>Chargement…</p>}
    {versions.data&&<DataTable rows={versions.data} columns={columns} identify={v=>v.id} searchText={v=>`${v.book_title} ${v.label} ${v.status} ${v.version_number}`} actions={v=>[
      {label:'Inspecter les passages',onClick:()=>void inspect(v.id),disabled:busy!==null},
      {label:'Calculer les embeddings',onClick:()=>void action(v,'embed'),disabled:busy!==null||v.status!=='REVIEW_REQUIRED'||v.embedded_count===v.chunk_count},
      {label:'Approuver',onClick:()=>void action(v,'approve'),disabled:busy!==null||v.status!=='REVIEW_REQUIRED'},
      {label:'Publier',onClick:()=>void action(v,'publish'),disabled:busy!==null||v.status!=='REVIEW_REQUIRED'||v.latest_decision!=='APPROVED'||v.embedded_count!==v.chunk_count},
      {label:'Rejeter',onClick:()=>void action(v,'reject'),danger:true,disabled:busy!==null||v.status!=='REVIEW_REQUIRED'},
    ]}/>}
    {preview&&<section className="chunk-preview standalone"><div className="section-heading"><div><h2>Passages extraits</h2><p>Premiers résultats de la version sélectionnée</p></div><button className="secondary" onClick={()=>setPreview(null)}>Fermer</button></div>{preview.chunks.map(c=><details key={c.id}><summary>Passage {c.position+1} · pages {c.start_page}–{c.end_page} · {c.embedded?'indexé':'non indexé'}</summary><p>{c.content}</p></details>)}</section>}
  </Root>
}
