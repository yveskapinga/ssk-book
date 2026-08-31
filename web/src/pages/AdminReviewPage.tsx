import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { apiRequest, ApiError } from '../api/client'
import { useAuth } from '../auth/AuthContext'

type Version = { id: string; book_title: string; version_number: number; label: string; status: string; page_count: number | null; chunk_count: string; embedded_count: string; latest_decision:string|null }
type Chunk = { id:string; position:number; start_page:number; end_page:number; content:string; embedded:boolean }

export function AdminReviewPage() {
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
  return <main className="page admin-page">
    <div className="page-heading"><div><span className="eyebrow">Administration</span><h1>Revue des versions</h1></div></div>
    {error && <div className="form-error">{error}</div>}
    {versions.isPending && <p>Chargement…</p>}
    <div className="review-list">{versions.data?.map(v => <article className="review-card" key={v.id}>
      <div><small>Version {v.version_number}</small><h2>{v.book_title}</h2><p>{v.label} · {v.page_count ?? '—'} pages · {v.chunk_count} passages</p></div>
      <span className={`status ${v.status === 'PUBLISHED' ? 'active' : ''}`}>{v.status}</span>
      <div className="review-progress"><span>Embeddings</span><strong>{v.embedded_count}/{v.chunk_count}</strong></div>
      <div className="row-actions">
        <button onClick={() => inspect(v.id)} disabled={busy !== null}>Inspecter</button>
        <button onClick={() => action(v,'embed')} disabled={busy !== null || v.status !== 'REVIEW_REQUIRED' || v.embedded_count === v.chunk_count}>Indexer</button>
        <button onClick={() => action(v,'approve')} disabled={busy !== null || v.status !== 'REVIEW_REQUIRED'}>Approuver</button>
        <button onClick={() => action(v,'reject')} disabled={busy !== null || v.status !== 'REVIEW_REQUIRED'}>Rejeter</button>
        <button className="primary" onClick={() => action(v,'publish')} disabled={busy !== null || v.status !== 'REVIEW_REQUIRED' || v.latest_decision !== 'APPROVED' || v.embedded_count !== v.chunk_count}>Publier</button>
      </div>
      {preview?.versionId===v.id&&<div className="chunk-preview"><h3>Premiers passages extraits</h3>{preview.chunks.map(c=><details key={c.id}><summary>Passage {c.position+1} · pages {c.start_page}–{c.end_page} · {c.embedded?'indexé':'non indexé'}</summary><p>{c.content}</p></details>)}</div>}
    </article>)}</div>
  </main>
}
