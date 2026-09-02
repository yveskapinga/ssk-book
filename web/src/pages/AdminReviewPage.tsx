import { useMemo, useState, type FormEvent } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { apiRequest, ApiError } from '../api/client'
import { useAuth } from '../auth/AuthContext'

type Version = { id: string; book_title: string; version_number: number; label: string; status: string; page_count: number | null; chunk_count: string | number; embedded_count: string | number; image_count?: string | number; latest_decision:string|null }
type Chunk = { id:string; position:number; start_page:number; end_page:number; content:string; embedded:boolean }

function statusLabel(status: string) {
  if (status === 'REVIEW_REQUIRED') return 'En attente de revue'
  if (status === 'PUBLISHED') return 'Publiée'
  if (status === 'FAILED') return 'Échec'
  if (status === 'REJECTED') return 'Rejetée'
  return status
}

function isIndexed(version: Version) {
  return Number(version.embedded_count) === Number(version.chunk_count) && Number(version.chunk_count) > 0
}

export function AdminReviewPage({ embedded=false }: { embedded?:boolean }) {
  const { token } = useAuth(); const cache = useQueryClient()
  const [busy, setBusy] = useState<string | null>(null)
  const [error, setError] = useState('')
  const [feedback, setFeedback] = useState('')
  const [query, setQuery] = useState('')
  const [preview,setPreview]=useState<{versionId:string;chunks:Chunk[]}|null>(null)
  const [rejectingId, setRejectingId] = useState<string | null>(null)
  const [rejectNotes, setRejectNotes] = useState('')
  const versions = useQuery({ queryKey: ['book-versions'], queryFn: () => apiRequest<{data:{items:Version[]}}>('/api/admin/book-versions?limit=100', {}, token).then(r => r.data.items) })

  const filtered = useMemo(() => {
    const rows = [...(versions.data ?? [])]
    const needle = query.trim().toLocaleLowerCase('fr')
    const matched = needle ? rows.filter(v => `${v.book_title} ${v.label} ${v.status} ${v.version_number}`.toLocaleLowerCase('fr').includes(needle)) : rows
    return matched.sort((a, b) => {
      const rank = (v: Version) => v.status === 'REVIEW_REQUIRED' ? 0 : v.status === 'PUBLISHED' ? 2 : 1
      const byStatus = rank(a) - rank(b)
      if (byStatus !== 0) return byStatus
      return Number(b.page_count ?? 0) - Number(a.page_count ?? 0)
    })
  }, [versions.data, query])

  async function action(version: Version, type: 'embed'|'approve'|'publish'|'images') {
    setBusy(`${type}:${version.id}`); setError(''); setFeedback(''); setRejectingId(null)
    try {
      const path = type === 'embed' ? 'embeddings' : type === 'publish' ? 'publish' : type === 'images' ? 'images' : 'review'
      const body = type === 'approve' ? JSON.stringify({decision:'APPROVED'}) : undefined
      await apiRequest(`/api/admin/book-versions/${version.id}/${path}`, {method:'POST', body}, token)
      setFeedback(type === 'embed' ? 'Indexation terminée.' : type === 'approve' ? 'Version approuvée. Cliquez maintenant sur Publier.' : type === 'images' ? 'Photos du livre extraites.' : 'Version publiée.')
      await cache.invalidateQueries({queryKey:['book-versions']})
    } catch (reason) { setError(reason instanceof ApiError ? reason.message : 'Opération impossible.') }
    finally { setBusy(null) }
  }

  async function reject(event: FormEvent, version: Version) {
    event.preventDefault()
    setBusy(`reject:${version.id}`); setError(''); setFeedback('')
    try {
      await apiRequest(`/api/admin/book-versions/${version.id}/review`, {method:'POST', body: JSON.stringify({decision:'REJECTED', notes: rejectNotes})}, token)
      setRejectingId(null); setRejectNotes(''); setFeedback('Version rejetée.')
      await cache.invalidateQueries({queryKey:['book-versions']})
    } catch (reason) { setError(reason instanceof ApiError ? reason.message : 'Rejet impossible.') }
    finally { setBusy(null) }
  }

  async function inspect(version: Version) {
    setBusy(`inspect:${version.id}`); setError('')
    try {
      const result=await apiRequest<{data:{items:Chunk[]}}>(`/api/admin/book-versions/${version.id}/chunks?limit=30`,{},token)
      setPreview({versionId:version.id,chunks:result.data.items})
    } catch (reason) { setError(reason instanceof ApiError?reason.message:'Aperçu indisponible.') }
    finally { setBusy(null) }
  }

  const Root=embedded?'div':'main'
  return <Root className={embedded?'embedded-workflow':'page admin-page'}>
    {embedded
      ? <div className="section-heading"><div><h2>Contrôler et publier</h2><p>Les boutons Approuver, Publier et Rejeter sont visibles sur chaque carte. Approuvez d’abord, puis publiez.</p></div></div>
      : <div className="page-heading"><div><span className="eyebrow">Administration</span><h1>Revue des versions</h1></div></div>}
    {error && <div className="form-error" role="alert">{error}</div>}
    {feedback && <div className="form-success" role="status">{feedback}</div>}
    {versions.isPending && <p>Chargement…</p>}
    <label className="table-search review-search"><span>⌕</span><input value={query} onChange={e=>setQuery(e.target.value)} placeholder="Rechercher une version"/></label>
    <div className="review-list">
      {filtered.map(v => {
        const canReview = v.status === 'REVIEW_REQUIRED'
        const indexed = isIndexed(v)
        const canPublish = canReview && indexed && v.latest_decision === 'APPROVED'
        const locked = busy !== null
        const approveTitle = canReview ? 'Enregistrer la décision d’approbation' : 'Disponible uniquement pour une version en attente de revue'
        const publishTitle = canPublish ? 'Rendre cette version lisible par le public' : !canReview ? 'Disponible uniquement pour une version en attente de revue' : !indexed ? 'Indexez tous les passages avant de publier' : 'Approuvez d’abord cette version'
        return <article key={v.id} className={`review-card ${canReview ? 'pending' : ''}`}>
          <div className="review-card-head">
            <div>
              <strong>{v.book_title}</strong>
              <small>Version {v.version_number} · {v.label} · {v.page_count??'—'} pages · {v.embedded_count}/{v.chunk_count} passages indexés · {v.image_count??0} photos</small>
            </div>
            <span className={`status ${v.status==='PUBLISHED'?'active':''}`}>{statusLabel(v.status)}</span>
          </div>
          {canReview && v.latest_decision==='APPROVED' && <p className="help-text">Décision : approuvée. Vous pouvez publier.</p>}
          {canReview && !indexed && <p className="help-text">Indexez tous les passages avant de publier.</p>}
          {canReview && indexed && v.latest_decision!=='APPROVED' && <p className="help-text">Approuvez d’abord cette version, puis cliquez sur Publier.</p>}
          <div className="row-actions">
            <button type="button" className="secondary" disabled={locked} onClick={()=>void inspect(v)}>Inspecter les passages</button>
            <button type="button" className="secondary" disabled={locked || v.status==='FAILED'} onClick={()=>void action(v,'images')}>{busy===`images:${v.id}`?'Extraction des photos…':'Préparer les images'}</button>
            <button type="button" className="secondary" disabled={locked || !canReview || indexed} title={indexed ? 'Tous les passages sont déjà indexés' : approveTitle} onClick={()=>void action(v,'embed')}>{busy===`embed:${v.id}`?'Indexation…':'Indexer'}</button>
            <button type="button" className="secondary" disabled={locked || !canReview} title={approveTitle} onClick={()=>void action(v,'approve')}>{busy===`approve:${v.id}`?'Approbation…':'Approuver'}</button>
            <button type="button" className="primary" disabled={locked || !canPublish} title={publishTitle} onClick={()=>void action(v,'publish')}>{busy===`publish:${v.id}`?'Publication…':'Publier'}</button>
            <button type="button" className="danger" disabled={locked || !canReview} title={canReview ? 'Rejeter cette extraction' : 'Disponible uniquement pour une version en attente de revue'} onClick={()=>{setRejectingId(v.id); setRejectNotes(''); setPreview(null)}}>Rejeter</button>
          </div>
          {rejectingId===v.id && <form className="reject-form nested" onSubmit={event=>void reject(event, v)}>
            <h3>Motif de rejet</h3>
            <p>La version ne sera pas publiée. Indiquez un motif explicite (au moins 5 caractères).</p>
            <textarea value={rejectNotes} onChange={e=>setRejectNotes(e.target.value)} minLength={5} required rows={4} placeholder="Décrivez le problème d’extraction, de pagination ou de qualité."/>
            <div className="row-actions">
              <button type="button" className="secondary" onClick={()=>setRejectingId(null)}>Annuler</button>
              <button className="danger" disabled={locked || rejectNotes.trim().length<5}>Confirmer le rejet</button>
            </div>
          </form>}
          {preview?.versionId===v.id && <section className="chunk-preview standalone">
            <div className="section-heading"><div><h3>Passages extraits</h3><p>Premiers résultats de cette version</p></div><button type="button" className="secondary" onClick={()=>setPreview(null)}>Fermer</button></div>
            {preview.chunks.map(c=><details key={c.id}><summary>Passage {c.position+1} · pages {c.start_page}–{c.end_page} · {c.embedded?'indexé':'non indexé'}</summary><p>{c.content}</p></details>)}
          </section>}
        </article>
      })}
      {filtered.length===0 && !versions.isPending && <div className="empty-panel"><strong>Aucune version</strong><p>Importez d’abord un PDF.</p></div>}
    </div>
  </Root>
}
