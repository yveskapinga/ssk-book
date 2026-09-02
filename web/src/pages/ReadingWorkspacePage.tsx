import { useEffect, useMemo, useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useSearchParams } from 'react-router-dom'
import { AuthImage } from '../components/AuthImage'
import { PageTabs } from '../components/PageTabs'
import { SearchableSelect } from '../components/SearchableSelect'
import { apiRequest, ApiError } from '../api/client'
import { useAuth } from '../auth/AuthContext'

type Toc = { id:string; parent_id:string|null; node_type:string; title:string; start_page:number; end_page:number }
type Block = { type:'text'; content:string } | { type:'image'; url:string; width_px:number; height_px:number }
type Leaf = {
  page_number: number
  page_count: number
  blocks: Block[]
  chunk: { id:string; position:number; start_page:number; end_page:number; content:string } | null
}
type BookPayload = { book:{ slug:string; title:string; page_count:number|null; version_id:string }; toc:Toc[]; progress:{ chunk_id:string; page_number:number; position:number }|null; chunkCount:number }
type Note = { id:string; body:string; start_page:number; excerpt?:string }

export function ReadingWorkspacePage(){
  const { token } = useAuth(); const cache = useQueryClient()
  const [params, setParams] = useSearchParams()
  const [tab, setTab] = useState<'read'|'contents'|'notes'>('read')
  const urlPage = Number(params.get('page')) || 0
  const [page, setPage] = useState<number | null>(urlPage > 0 ? urlPage : null)
  const [query, setQuery] = useState('')
  const [note, setNote] = useState('')
  const [feedback, setFeedback] = useState('')
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)

  const library = useQuery({ queryKey:['library'], queryFn:()=>apiRequest<{data:{items:{slug:string;title:string}[]}}>('/api/library', {}, token).then(r=>r.data.items) })
  const slug = params.get('slug') || library.data?.[0]?.slug || ''
  const book = useQuery({ queryKey:['book', slug], enabled:Boolean(slug), queryFn:()=>apiRequest<{data:BookPayload}>(`/api/books/${slug}`, {}, token).then(r=>r.data) })
  const leaf = useQuery({ queryKey:['reading-page', slug, page], enabled:Boolean(slug) && page !== null && page > 0, queryFn:()=>apiRequest<{data:Leaf}>(`/api/books/${slug}/pages/${page}`, {}, token).then(r=>r.data) })
  const notes = useQuery({ queryKey:['notes'], queryFn:()=>apiRequest<{data:{items:Note[]}}>('/api/me/notes', {}, token).then(r=>r.data.items) })
  const toc = book.data?.toc ?? []
  const matches = useMemo(()=>toc.filter(n=>n.title.toLowerCase().includes(query.toLowerCase())), [toc, query])
  const current = leaf.data
  const total = current?.page_count || book.data?.book.page_count || 0
  const chunk = current?.chunk
  const currentPage = page ?? 0

  useEffect(() => {
    if (!slug || page === null) return
    if (params.get('slug') !== slug || params.get('page') !== String(page)) {
      setParams({ slug, page: String(page) }, { replace: true })
    }
  }, [slug, page, params, setParams])

  useEffect(() => {
    if (page !== null || !book.data) return
    setPage(book.data.progress?.page_number || 1)
  }, [book.data, page])

  useEffect(() => {
    if (!slug || page === null) return
    void apiRequest(`/api/books/${slug}/progress`, { method:'PATCH', body: JSON.stringify({ pageNumber: page }) }, token).catch((reason) => {
      setError(reason instanceof ApiError ? reason.message : 'La progression n’a pas pu être enregistrée.')
    })
  }, [page, slug, token])

  function openAtPage(pdfPage: number) {
    setPage(pdfPage)
    setTab('read')
  }

  function go(delta:number) {
    if (page === null) return
    const nextPage = page + delta
    if (nextPage < 1 || nextPage > total) return
    setPage(nextPage)
  }

  async function bookmark() {
    if (!chunk || !slug) return
    setBusy(true); setError(''); setFeedback('')
    try {
      await apiRequest(`/api/books/${slug}/bookmarks`, { method:'POST', body: JSON.stringify({ chunkId: chunk.id }) }, token)
      setFeedback('Passage ajouté aux favoris.')
      await cache.invalidateQueries({ queryKey:['dashboard'] })
    } catch (reason) { setError(reason instanceof ApiError ? reason.message : 'Favori impossible.') }
    finally { setBusy(false) }
  }
  async function saveNote() {
    if (!chunk || !slug) return
    setBusy(true); setError(''); setFeedback('')
    try {
      await apiRequest(`/api/books/${slug}/notes`, { method:'POST', body: JSON.stringify({ chunkId: chunk.id, body: note }) }, token)
      setNote(''); setFeedback('Note enregistrée.'); await cache.invalidateQueries({ queryKey:['notes'] }); setTab('notes')
    } catch (reason) { setError(reason instanceof ApiError ? reason.message : 'Note impossible.') }
    finally { setBusy(false) }
  }
  async function highlight() {
    if (!chunk || !slug) return
    const selected = window.getSelection()?.toString().trim() || ''
    const pageText = (current?.blocks ?? []).filter((block): block is Extract<Block, {type:'text'}> => block.type === 'text').map(block => block.content).join('\n\n')
    const excerpt = selected && chunk.content.includes(selected)
      ? selected
      : pageText && chunk.content.includes(pageText.slice(0, 180))
        ? pageText.slice(0, 180)
        : chunk.content.slice(0, 180)
    setBusy(true); setError(''); setFeedback('')
    try {
      await apiRequest(`/api/books/${slug}/highlights`, { method:'POST', body: JSON.stringify({ chunkId: chunk.id, excerpt }) }, token)
      setFeedback('Surlignage enregistré.')
    } catch (reason) { setError(reason instanceof ApiError ? reason.message : 'Surlignage impossible.') }
    finally { setBusy(false) }
  }

  const title = book.data?.book.title ?? 'Livre'
  return <main className="workspace-page reader-page">
    <div className="page-title"><div><span className="eyebrow">Bibliothèque</span><h1>{title}</h1></div>
      <div className="page-title-tools">
        {(library.data?.length ?? 0) > 0 && <SearchableSelect
          value={slug}
          onChange={next => { setParams({ slug: next, page: '1' }); setPage(1) }}
          options={(library.data ?? []).map(item => ({ value: item.slug, label: item.title }))}
          placeholder="Choisir un livre"
        />}
        <div className="reader-position">{currentPage && total ? `Page ${currentPage} / ${total}` : '—'}</div>
      </div>
    </div>
    {error && <div className="form-error" role="alert">{error}</div>}
    {feedback && <div className="form-success" role="status">{feedback}</div>}
    {book.isError && <div className="form-error">Le livre publié n’est pas encore disponible.</div>}
    <PageTabs tabs={[{id:'read',label:'Lire'},{id:'contents',label:'Sommaire',count:toc.length},{id:'notes',label:'Notes',count:notes.data?.length}]} active={tab} onChange={setTab}/>
    {tab==='read'&&<div className="reader-layout">
      <aside className="chapter-rail">
        <label className="compact-search"><span>⌕</span><input placeholder="Rechercher un chapitre" value={query} onChange={e=>setQuery(e.target.value)}/></label>
        {matches.map(n=><button key={n.id} className={currentPage>=n.start_page && currentPage<=n.end_page ? 'active' : undefined} onClick={()=>openAtPage(n.start_page)}><strong>{n.title}</strong><span>Pages {n.start_page}–{n.end_page}</span></button>)}
        {matches.length===0 && <p className="empty-inline">Sommaire indisponible pour cette version.</p>}
      </aside>
      <article className="reading-sheet">
        {(leaf.isPending || page === null) && <p>Chargement de la page…</p>}
        {leaf.isError && <div className="form-error" role="alert">{leaf.error instanceof ApiError ? leaf.error.message : 'Cette page n’a pas pu être ouverte.'}</div>}
        {current && <>
          <header><small>Page {current.page_number} / {current.page_count}</small><h2>Page {current.page_number}</h2></header>
          <div className="page-flow">
            {current.blocks.map((block, index) => block.type === 'image'
              ? <figure key={`img-${index}`} className="page-figure"><AuthImage src={block.url} alt={`Illustration de la page ${current.page_number}`} token={token??''}/></figure>
              : <p key={`txt-${index}`} style={{whiteSpace:'pre-wrap'}}>{block.content}</p>)}
            {current.blocks.length===0 && <p>Cette page n’a pas de texte extractible.</p>}
          </div>
          <div className="reader-tools">
            <button className="secondary" disabled={busy || !chunk} onClick={()=>void bookmark()}>Ajouter aux favoris</button>
            <button className="secondary" disabled={busy || !chunk || note.trim().length<2} onClick={()=>void saveNote()}>Enregistrer la note</button>
            <button className="secondary" disabled={busy || !chunk} onClick={()=>void highlight()}>Surligner la sélection</button>
          </div>
          <label>Note sur cette page<textarea value={note} onChange={e=>setNote(e.target.value)} rows={3} /></label>
          <footer>
            <button className="secondary" disabled={currentPage<=1} onClick={()=>go(-1)}>Page précédente</button>
            <button className="primary" disabled={!total || currentPage>=total} onClick={()=>go(1)}>Page suivante</button>
          </footer>
        </>}
      </article>
    </div>}
    {tab==='contents'&&<div className="content-cards">{toc.map((c,i)=><button key={c.id} onClick={()=>openAtPage(c.start_page)}><span>{String(i+1).padStart(2,'0')}</span><div><strong>{c.title}</strong><small>Pages {c.start_page}–{c.end_page}</small></div><b>→</b></button>)}{toc.length===0&&<div className="empty-panel"><strong>Sommaire en construction</strong><p>Les parties et chapitres détectés lors de l’ingestion apparaîtront ici.</p></div>}</div>}
    {tab==='notes'&&<div>{notes.data?.length ? notes.data.map(n=><article className="note-card" key={n.id}><small>Page {n.start_page}</small><p>{n.body}</p></article>) : <div className="empty-panel"><strong>Aucune note personnelle</strong><p>Vos notes seront rattachées au passage et à la version du livre.</p></div>}</div>}
  </main>
}
