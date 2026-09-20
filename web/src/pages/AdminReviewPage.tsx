import { useMemo, useState, type FormEvent } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { apiRequest, ApiError } from '../api/client'
import { useAuth } from '../auth/AuthContext'
import { BusyButton } from '../components/BusyButton'
import { OperationProgress } from '../components/OperationProgress'
import { Spinner } from '../components/Spinner'

type Version = { id: string; book_title: string; version_number: number; label: string; status: string; page_count: number | null; chunk_count: string | number; embedded_count: string | number; image_count?: string | number; latest_decision:string|null }
type Chunk = { id:string; position:number; start_page:number; end_page:number; content:string; embedded:boolean }
type ProgressState = { versionId: string; title: string; current: number; total: number; indeterminate?: boolean; done?: boolean; detail?: string }

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

function actionLabel(type: string) {
  if (type === 'embed') return 'Indexation'
  if (type === 'images') return 'Extraction des photos'
  if (type === 'approve') return 'Approbation'
  if (type === 'publish') return 'Publication'
  if (type === 'inspect') return 'Chargement des passages'
  if (type === 'reject') return 'Rejet'
  return 'Traitement'
}

export function AdminReviewPage({ embedded=false }: { embedded?:boolean }) {
  const { token } = useAuth(); const cache = useQueryClient()
  const [busy, setBusy] = useState<string | null>(null)
  const [progress, setProgress] = useState<ProgressState | null>(null)
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
    const title = `${actionLabel(type)} · ${version.book_title}`
    if (type === 'embed') {
      setProgress({
        versionId: version.id,
        title,
        current: Number(version.embedded_count),
        total: Number(version.chunk_count),
        detail: 'Vectorisation des passages via Gemini…',
      })
    } else {
      setProgress({ versionId: version.id, title, current: 0, total: 0, indeterminate: true, detail: 'Veuillez patienter…' })
    }
    try {
      const path = type === 'embed' ? 'embeddings' : type === 'publish' ? 'publish' : type === 'images' ? 'images' : 'review'
      const body = type === 'approve' ? JSON.stringify({decision:'APPROVED'}) : undefined
      if (type === 'embed') {
        let done = false
        while (!done) {
          const result = await apiRequest<{data:{total:string|number; embedded:string|number; done?:boolean}}>(`/api/admin/book-versions/${version.id}/${path}`, {method:'POST', body}, token)
          const embeddedCount = Number(result.data.embedded)
          const total = Number(result.data.total)
          done = result.data.done === true || (total > 0 && embeddedCount >= total)
          setProgress({
            versionId: version.id,
            title,
            current: embeddedCount,
            total,
            done,
            detail: done ? 'Tous les passages sont indexés.' : 'Indexation par lots en cours…',
          })
          await cache.invalidateQueries({queryKey:['book-versions']})
        }
        setFeedback('Indexation terminée.')
      } else {
        await apiRequest(`/api/admin/book-versions/${version.id}/${path}`, {method:'POST', body}, token)
        setProgress({ versionId: version.id, title, current: 1, total: 1, done: true, detail: 'Opération terminée.' })
        setFeedback(type === 'approve' ? 'Version approuvée. Cliquez maintenant sur Publier.' : type === 'images' ? 'Photos du livre extraites.' : 'Version publiée.')
        await cache.invalidateQueries({queryKey:['book-versions']})
      }
    } catch (reason) {
      setError(reason instanceof ApiError ? reason.message : 'Opération impossible.')
      setProgress(null)
    } finally {
      setBusy(null)
      window.setTimeout(() => setProgress((current) => (current?.done ? null : current)), 1600)
    }
  }

  async function reject(event: FormEvent, version: Version) {
    event.preventDefault()
    setBusy(`reject:${version.id}`); setError(''); setFeedback('')
    setProgress({ versionId: version.id, title: `Rejet · ${version.book_title}`, current: 0, total: 0, indeterminate: true })
    try {
      await apiRequest(`/api/admin/book-versions/${version.id}/review`, {method:'POST', body: JSON.stringify({decision:'REJECTED', notes: rejectNotes})}, token)
      setRejectingId(null); setRejectNotes(''); setFeedback('Version rejetée.')
      setProgress({ versionId: version.id, title: `Rejet · ${version.book_title}`, current: 1, total: 1, done: true })
      await cache.invalidateQueries({queryKey:['book-versions']})
    } catch (reason) {
      setError(reason instanceof ApiError ? reason.message : 'Rejet impossible.')
      setProgress(null)
    } finally {
      setBusy(null)
      window.setTimeout(() => setProgress((current) => (current?.done ? null : current)), 1200)
    }
  }

  async function inspect(version: Version) {
    setBusy(`inspect:${version.id}`); setError('')
    setProgress({ versionId: version.id, title: `Passages · ${version.book_title}`, current: 0, total: 0, indeterminate: true, detail: 'Chargement de l’aperçu…' })
    try {
      const result=await apiRequest<{data:{items:Chunk[]}}>(`/api/admin/book-versions/${version.id}/chunks?limit=30`,{},token)
      setPreview({versionId:version.id,chunks:result.data.items})
      setProgress(null)
    } catch (reason) {
      setError(reason instanceof ApiError?reason.message:'Aperçu indisponible.')
      setProgress(null)
    } finally {
      setBusy(null)
    }
  }

  const Root=embedded?'div':'main'
  return <Root className={embedded?'embedded-workflow':'page admin-page'}>
    {embedded
      ? <div className="section-heading"><div><h2>Contrôler et publier</h2><p>Les boutons Approuver, Publier et Rejeter sont visibles sur chaque carte. Approuvez d’abord, puis publiez.</p></div></div>
      : <div className="page-heading"><div><span className="eyebrow">Administration</span><h1>Revue des versions</h1></div></div>}
    {error && <div className="form-error" role="alert">{error}</div>}
    {feedback && <div className="form-success" role="status">{feedback}</div>}
    {progress && (
      <OperationProgress
        title={progress.title}
        detail={progress.detail}
        current={progress.current}
        total={progress.total}
        indeterminate={progress.indeterminate}
        done={progress.done}
      />
    )}
    {versions.isPending && <p className="inline-busy"><Spinner size="sm" label="Chargement des versions…" /></p>}
    <label className="table-search review-search"><span>⌕</span><input value={query} onChange={e=>setQuery(e.target.value)} placeholder="Rechercher une version"/></label>
    <div className="review-list">
      {filtered.map(v => {
        const canReview = v.status === 'REVIEW_REQUIRED'
        const indexed = isIndexed(v)
        const canPublish = canReview && indexed && v.latest_decision === 'APPROVED'
        const locked = busy !== null
        const approveTitle = canReview ? 'Enregistrer la décision d’approbation' : 'Disponible uniquement pour une version en attente de revue'
        const publishTitle = canPublish ? 'Rendre cette version lisible par le public' : !canReview ? 'Disponible uniquement pour une version en attente de revue' : !indexed ? 'Indexez tous les passages avant de publier' : 'Approuvez d’abord cette version'
        const embedProgress = progress?.versionId === v.id && busy?.startsWith('embed:')
          ? progress
          : null
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
          {embedProgress && (
            <OperationProgress
              title="Indexation des passages"
              detail={embedProgress.detail}
              current={embedProgress.current}
              total={embedProgress.total}
              done={embedProgress.done}
            />
          )}
          <div className="row-actions">
            <BusyButton className="secondary" busy={busy===`inspect:${v.id}`} busyLabel="Chargement…" disabled={locked && busy!==`inspect:${v.id}`} onClick={()=>void inspect(v)}>Inspecter les passages</BusyButton>
            <BusyButton className="secondary" busy={busy===`images:${v.id}`} busyLabel="Extraction…" disabled={(locked && busy!==`images:${v.id}`) || v.status==='FAILED'} onClick={()=>void action(v,'images')}>Préparer les images</BusyButton>
            <BusyButton className="secondary" busy={busy===`embed:${v.id}`} busyLabel="Indexation…" disabled={(locked && busy!==`embed:${v.id}`) || !canReview || indexed} title={indexed ? 'Tous les passages sont déjà indexés' : 'Indexer les passages'} onClick={()=>void action(v,'embed')}>Indexer</BusyButton>
            <BusyButton className="secondary" busy={busy===`approve:${v.id}`} busyLabel="Approbation…" disabled={(locked && busy!==`approve:${v.id}`) || !canReview} title={approveTitle} onClick={()=>void action(v,'approve')}>Approuver</BusyButton>
            <BusyButton className="primary" busy={busy===`publish:${v.id}`} busyLabel="Publication…" disabled={(locked && busy!==`publish:${v.id}`) || !canPublish} title={publishTitle} onClick={()=>void action(v,'publish')}>Publier</BusyButton>
            <button type="button" className="danger" disabled={locked || !canReview} title={canReview ? 'Rejeter cette extraction' : 'Disponible uniquement pour une version en attente de revue'} onClick={()=>{setRejectingId(v.id); setRejectNotes(''); setPreview(null)}}>Rejeter</button>
          </div>
          {rejectingId===v.id && <form className="reject-form nested" onSubmit={event=>void reject(event, v)}>
            <h3>Motif de rejet</h3>
            <p>La version ne sera pas publiée. Indiquez un motif explicite (au moins 5 caractères).</p>
            <textarea value={rejectNotes} onChange={e=>setRejectNotes(e.target.value)} minLength={5} required rows={4} placeholder="Décrivez le problème d’extraction, de pagination ou de qualité."/>
            <div className="row-actions">
              <button type="button" className="secondary" onClick={()=>setRejectingId(null)}>Annuler</button>
              <BusyButton className="danger" type="submit" busy={busy===`reject:${v.id}`} busyLabel="Rejet…" disabled={locked || rejectNotes.trim().length<5}>Confirmer le rejet</BusyButton>
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
