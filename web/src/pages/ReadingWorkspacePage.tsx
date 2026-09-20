import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { useQuery, useQueryClient, keepPreviousData } from '@tanstack/react-query'
import { AuthImage } from '../components/AuthImage'
import { SearchableSelect } from '../components/SearchableSelect'
import { apiRequest } from '../api/client'
import { useAuth } from '../auth/AuthContext'
import { useAppError } from '../lib/AppError'

type Toc = { id: string; parent_id: string | null; node_type: string; title: string; start_page: number; end_page: number }
type Note = { id: string; body: string; start_page: number }
type Chunk = { id: string; position: number; start_page: number; end_page: number; content: string }
type Reading = {
  passage: {
    id: string
    position: number
    page_number: number
    kind: 'TEXT' | 'IMAGE'
    body: string
    chapter_title: string | null
    figure: { id: string; url: string; width_px: number; height_px: number } | null
    chunk: Chunk | null
  }
  status: 'IN_PROGRESS' | 'READ'
  estimatedSeconds: number
  total: number
  frontier: { id: string; position: number; page_number: number }
  previousId: string | null
  nextId: string | null
  canAdvance: boolean
  complete: boolean
}
type BookPayload = {
  book: { slug: string; title: string }
  toc: Toc[]
}
type Panel = 'menu' | 'toc' | 'notes' | 'tools' | 'book' | null

export function ReadingWorkspacePage() {
  const { token } = useAuth()
  const { report } = useAppError()
  const cache = useQueryClient()
  const [params, setParams] = useSearchParams()
  const [passageId, setPassageId] = useState<string | null>(params.get('passage'))
  const pageHint = passageId ? 0 : (Number(params.get('page')) || 0)
  const [panel, setPanel] = useState<Panel>(null)
  const [query, setQuery] = useState('')
  const [note, setNote] = useState('')
  const [busy, setBusy] = useState(false)
  const lastTick = useRef(Date.now())
  const advancing = useRef(false)

  const library = useQuery({
    queryKey: ['library'],
    queryFn: () => apiRequest<{ data: { items: { slug: string; title: string }[] } }>('/api/library', {}, token).then(r => r.data.items),
  })
  const slug = params.get('slug') || library.data?.[0]?.slug || ''
  const book = useQuery({
    queryKey: ['book', slug],
    enabled: Boolean(slug),
    queryFn: () => apiRequest<{ data: BookPayload }>(`/api/books/${slug}`, {}, token).then(r => r.data),
  })
  const reading = useQuery({
    queryKey: ['reading', slug, passageId, pageHint],
    enabled: Boolean(slug),
    placeholderData: keepPreviousData,
    queryFn: () => {
      const search = new URLSearchParams()
      if (passageId) search.set('passage', passageId)
      else if (pageHint > 0) search.set('page', String(pageHint))
      const suffix = search.toString() ? `?${search}` : ''
      return apiRequest<{ data: Reading }>(`/api/books/${slug}/reading${suffix}`, {}, token).then(r => r.data)
    },
  })
  const notes = useQuery({
    queryKey: ['notes'],
    queryFn: () => apiRequest<{ data: { items: Note[] } }>('/api/me/notes', {}, token).then(r => r.data.items),
  })

  const current = reading.data
  const passage = current?.passage
  const toc = book.data?.toc ?? []
  const matches = useMemo(() => toc.filter(n => n.title.toLowerCase().includes(query.toLowerCase())), [toc, query])
  const chunk = passage?.chunk ?? null

  useEffect(() => {
    if (library.isError) report(library.error, 'La bibliothèque n’a pas pu être chargée.')
  }, [library.isError, library.error, report])

  useEffect(() => {
    if (book.isError) report(book.error, 'Le livre publié n’est pas encore disponible.')
  }, [book.isError, book.error, report])

  useEffect(() => {
    if (reading.isError) report(reading.error, 'Ce passage n’a pas pu être ouvert.')
  }, [reading.isError, reading.error, report])

  useEffect(() => {
    if (!slug || !passage?.id || reading.isPlaceholderData) return
    if (passageId && passage.id !== passageId) return
    if (params.get('slug') !== slug || params.get('passage') !== passage.id) {
      setParams({ slug, passage: passage.id }, { replace: true })
    }
    if (!passageId) setPassageId(passage.id)
  }, [passage?.id, slug, params, passageId, setParams, reading.isPlaceholderData])

  useEffect(() => {
    if (!reading.isError || !reading.data?.passage.id || !passageId) return
    if (passageId === reading.data.passage.id) return
    setPassageId(reading.data.passage.id)
  }, [reading.isError, reading.data?.passage.id, passageId])

  useEffect(() => {
    if (!slug || !passage?.id || !token) return
    const id = passage.id
    lastTick.current = Date.now()
    const send = async (hidden: boolean) => {
      if (advancing.current) return
      if (!hidden && document.hidden) return
      const now = Date.now()
      const delta = now - lastTick.current
      lastTick.current = now
      if (delta < 250) return
      try {
        const next = await apiRequest<{ data: Reading }>(`/api/books/${slug}/reading/progress`, {
          method: 'POST',
          body: JSON.stringify({ passageId: id, displayedMs: delta }),
        }, token)
        cache.setQueryData(['reading', slug, id, 0], next.data)
        if (next.data.status === 'READ') {
          await cache.invalidateQueries({ queryKey: ['dashboard'] })
        }
      } catch (reason) {
        report(reason, 'La progression n’a pas pu être enregistrée.')
      }
    }
    const tick = window.setInterval(() => { void send(false) }, 4000)
    const onVisibility = () => { void send(document.hidden) }
    document.addEventListener('visibilitychange', onVisibility)
    return () => {
      window.clearInterval(tick)
      document.removeEventListener('visibilitychange', onVisibility)
      void send(true)
    }
  }, [slug, passage?.id, token, cache, report])

  useEffect(() => {
    document.body.style.overflow = panel ? 'hidden' : ''
    return () => { document.body.style.overflow = '' }
  }, [panel])

  const goToPassage = useCallback((id: string) => {
    if (!slug || !id) return
    setPassageId(id)
    setParams({ slug, passage: id }, { replace: true })
  }, [slug, setParams])

  const goNext = useCallback(async () => {
    if (!slug || !passage || !current?.nextId || advancing.current || busy) return
    advancing.current = true
    setBusy(true)
    try {
      const now = Date.now()
      const delta = Math.max(0, now - lastTick.current)
      lastTick.current = now
      const next = await apiRequest<{ data: Reading }>(`/api/books/${slug}/reading/progress`, {
        method: 'POST',
        body: JSON.stringify({ passageId: passage.id, displayedMs: delta, advance: true }),
      }, token)
      cache.setQueryData(['reading', slug, next.data.passage.id, 0], next.data)
      if (next.data.status === 'READ' || next.data.passage.id !== passage.id) {
        await cache.invalidateQueries({ queryKey: ['dashboard'] })
      }
      if (next.data.passage.id !== passage.id) goToPassage(next.data.passage.id)
    } catch (reason) {
      report(reason, 'Le passage suivant n’a pas pu être ouvert.')
    } finally {
      advancing.current = false
      setBusy(false)
    }
  }, [busy, cache, current?.nextId, goToPassage, passage, report, slug, token])

  useEffect(() => {
    function onKey(event: KeyboardEvent) {
      if (panel) {
        if (event.key === 'Escape') setPanel(null)
        return
      }
      if (event.key === 'ArrowLeft' && current?.previousId) {
        event.preventDefault()
        goToPassage(current.previousId)
      }
      if (event.key === 'ArrowRight' && current?.nextId) {
        event.preventDefault()
        void goNext()
      }
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [current, goNext, goToPassage, panel])

  async function bookmark() {
    if (!chunk || !slug) return
    setBusy(true)
    try {
      await apiRequest(`/api/books/${slug}/bookmarks`, { method: 'POST', body: JSON.stringify({ chunkId: chunk.id }) }, token)
      await cache.invalidateQueries({ queryKey: ['dashboard'] })
      setPanel(null)
    } catch (reason) {
      report(reason, 'Favori impossible.')
    } finally { setBusy(false) }
  }

  async function saveNote() {
    if (!chunk || !slug || note.trim().length < 2) return
    setBusy(true)
    try {
      await apiRequest(`/api/books/${slug}/notes`, { method: 'POST', body: JSON.stringify({ chunkId: chunk.id, body: note }) }, token)
      setNote('')
      await cache.invalidateQueries({ queryKey: ['notes'] })
      setPanel('notes')
    } catch (reason) {
      report(reason, 'Note impossible.')
    } finally { setBusy(false) }
  }

  async function highlight() {
    if (!chunk || !slug || !passage) return
    const selected = window.getSelection()?.toString().trim() || ''
    const excerpt = selected && chunk.content.includes(selected)
      ? selected
      : passage.body && chunk.content.includes(passage.body.slice(0, 180))
        ? passage.body.slice(0, 180)
        : chunk.content.slice(0, 180)
    setBusy(true)
    try {
      await apiRequest(`/api/books/${slug}/highlights`, { method: 'POST', body: JSON.stringify({ chunkId: chunk.id, excerpt }) }, token)
      setPanel(null)
    } catch (reason) {
      report(reason, 'Surlignage impossible.')
    } finally { setBusy(false) }
  }

  function openChapter(startPage: number) {
    if (!slug) return
    setPanel(null)
    const search = new URLSearchParams({ page: String(startPage) })
    void apiRequest<{ data: Reading }>(`/api/books/${slug}/reading?${search}`, {}, token)
      .then(result => {
        goToPassage(result.data.passage.id)
        cache.setQueryData(['reading', slug, result.data.passage.id, 0], result.data)
      })
      .catch(reason => {
        report(reason, 'Ce chapitre n’est pas encore déverrouillé.')
      })
  }

  const statusLabel = current?.complete
    ? 'Livre terminé'
    : current?.status === 'READ'
      ? 'Lu'
      : 'En cours'
  const positionLabel = passage && current ? `${passage.position + 1} / ${current.total}` : '—'

  return <main className="reader-immersive">
    <header className="reader-topbar">
      <Link className="ghost reader-exit" to="/espace">Quitter</Link>
      <div className="reader-topbar-title">
        <strong>{passage?.chapter_title || book.data?.book.title || 'Lecture'}</strong>
        <span>{positionLabel}</span>
      </div>
      <button type="button" className="ghost reader-menu" aria-label="Menu de lecture" aria-expanded={panel === 'menu'} onClick={() => setPanel(panel ? null : 'menu')}>⋯</button>
    </header>

    <section className="reader-stage" aria-live="polite">
      {(reading.isPending && !passage) && <p className="reader-loading">Chargement du passage…</p>}
      {passage?.kind === 'IMAGE' && passage.figure && (
        <figure className="reader-figure">
          <AuthImage src={passage.figure.url} alt={`Illustration de la page ${passage.page_number}`} token={token ?? ''} />
        </figure>
      )}
      {passage?.kind === 'TEXT' && <p className="reader-passage">{passage.body}</p>}
      {reading.isSuccess && !passage && <p className="reader-loading">Aucun passage n’est disponible.</p>}
    </section>

    <p className="reader-status">{current ? statusLabel : ' '}</p>

    <footer className="reader-dock">
      <button type="button" className="secondary" disabled={!current?.previousId || busy} onClick={() => current?.previousId && goToPassage(current.previousId)}>Précédent</button>
      <button type="button" className="primary" disabled={busy || !current?.nextId} onClick={() => void goNext()}>
        {current?.complete && !current.nextId ? 'Terminé' : busy ? 'Suite…' : 'Suivant'}
      </button>
    </footer>

    {panel && <div className="reader-overlay" onClick={() => setPanel(null)}>
      <aside className="reader-sheet" role="dialog" aria-modal="true" onClick={event => event.stopPropagation()}>
        <header>
          <strong>{panel === 'toc' ? 'Sommaire' : panel === 'notes' ? 'Notes' : panel === 'tools' ? 'Outils' : panel === 'book' ? 'Livre' : 'Lecture'}</strong>
          <button type="button" className="ghost" onClick={() => setPanel(null)} aria-label="Fermer">×</button>
        </header>
        {panel === 'menu' && <nav className="reader-sheet-nav">
          <button type="button" onClick={() => setPanel('toc')}>Sommaire</button>
          <button type="button" onClick={() => setPanel('notes')}>Notes</button>
          <button type="button" onClick={() => setPanel('tools')}>Favoris et surlignage</button>
          <button type="button" onClick={() => setPanel('book')}>Choisir un livre</button>
        </nav>}
        {panel === 'toc' && <>
          <label className="compact-search"><span>⌕</span><input placeholder="Rechercher un chapitre" value={query} onChange={e => setQuery(e.target.value)} /></label>
          <div className="reader-toc">
            {matches.map(node => {
              const unlocked = Boolean(current?.complete || (current && node.start_page <= current.frontier.page_number))
              return <button
                key={node.id}
                type="button"
                className={passage && passage.page_number >= node.start_page && passage.page_number <= node.end_page ? 'active' : undefined}
                disabled={!unlocked}
                onClick={() => openChapter(node.start_page)}
              >
                <strong>{node.title}</strong>
                <span>Pages {node.start_page}–{node.end_page}</span>
              </button>
            })}
            {matches.length === 0 && <p className="empty-inline">Sommaire indisponible pour cette version.</p>}
          </div>
        </>}
        {panel === 'notes' && <div className="reader-notes">
          {notes.data?.length ? notes.data.map(item => <article key={item.id} className="note-card"><small>Page {item.start_page}</small><p>{item.body}</p></article>) : <p className="empty-inline">Aucune note personnelle.</p>}
        </div>}
        {panel === 'tools' && <div className="reader-tools-panel">
          <button type="button" className="secondary" disabled={busy || !chunk} onClick={() => void bookmark()}>Ajouter aux favoris</button>
          <button type="button" className="secondary" disabled={busy || !chunk} onClick={() => void highlight()}>Surligner la sélection</button>
          <label>Note sur ce passage<textarea value={note} onChange={e => setNote(e.target.value)} rows={4} /></label>
          <button type="button" className="primary" disabled={busy || !chunk || note.trim().length < 2} onClick={() => void saveNote()}>Enregistrer la note</button>
        </div>}
        {panel === 'book' && (library.data?.length ?? 0) > 0 && <SearchableSelect
          value={slug}
          onChange={next => { setPanel(null); setPassageId(null); setParams({ slug: next }) }}
          options={(library.data ?? []).map(item => ({ value: item.slug, label: item.title }))}
          placeholder="Choisir un livre"
        />}
      </aside>
    </div>}
  </main>
}
