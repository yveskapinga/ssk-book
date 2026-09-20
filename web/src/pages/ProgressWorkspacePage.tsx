import { Link } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { PageTabs } from '../components/PageTabs'
import { StatGrid } from '../components/StatGrid'
import { BusyButton } from '../components/BusyButton'
import { apiRequest, ApiError } from '../api/client'
import { useAuth } from '../auth/AuthContext'

type Dashboard = { stats:{ reading_percent:number|string; bookmark_count:number|string; note_count:number|string; quiz_count:number|string } }
type Bookmark = { id:string; slug:string; title:string; start_page:number; excerpt:string }
type Note = { id:string; slug:string; title:string; start_page:number; body:string }
type Highlight = { id:string; slug:string; start_page:number; excerpt:string }

export function ProgressWorkspacePage(){
  const { token } = useAuth(); const cache = useQueryClient()
  const [tab,setTab]=useState<'progress'|'bookmarks'|'notes'>('progress')
  const [error,setError]=useState('')
  const [busyId,setBusyId]=useState<string|null>(null)
  const dash = useQuery({ queryKey:['dashboard'], queryFn:()=>apiRequest<{data:Dashboard}>('/api/me/dashboard', {}, token).then(r=>r.data) })
  const bookmarks = useQuery({ queryKey:['bookmarks'], queryFn:()=>apiRequest<{data:{items:Bookmark[]}}>('/api/me/bookmarks', {}, token).then(r=>r.data.items) })
  const notes = useQuery({ queryKey:['notes'], queryFn:()=>apiRequest<{data:{items:Note[]}}>('/api/me/notes', {}, token).then(r=>r.data.items) })
  const highlights = useQuery({ queryKey:['highlights'], queryFn:()=>apiRequest<{data:{items:Highlight[]}}>('/api/me/highlights', {}, token).then(r=>r.data.items) })
  const stats = dash.data?.stats
  async function remove(kind:'bookmarks'|'notes'|'highlights', id:string) {
    setError(''); setBusyId(`${kind}:${id}`)
    try {
      await apiRequest(`/api/${kind}/${id}`, { method:'DELETE' }, token)
      await Promise.all([
        cache.invalidateQueries({ queryKey:[kind] }),
        cache.invalidateQueries({ queryKey:['dashboard'] }),
      ])
    } catch (reason) { setError(reason instanceof ApiError ? reason.message : 'Suppression impossible.') }
    finally { setBusyId(null) }
  }
  return <main className="workspace-page">
    <div className="page-title"><div><span className="eyebrow">Mon activité</span><h1>Progression</h1></div></div>
    {error && <div className="form-error" role="alert">{error}</div>}
    <StatGrid items={[
      {label:'Livre lu',value:`${stats?.reading_percent ?? 0} %`},
      {label:'Passages favoris',value:Number(stats?.bookmark_count ?? 0)},
      {label:'Notes personnelles',value:Number(stats?.note_count ?? 0)},
      {label:'Quiz terminés',value:Number(stats?.quiz_count ?? 0)},
    ]}/>
    <PageTabs tabs={[
      {id:'progress',label:'Parcours'},
      {id:'bookmarks',label:'Favoris',count:bookmarks.data?.length},
      {id:'notes',label:'Notes',count:notes.data?.length},
    ]} active={tab} onChange={setTab}/>
    {tab==='progress'&&<div className="progress-stack">
      {highlights.data?.length ? highlights.data.map(h=><article className="note-card" key={h.id}><small>Surlignage · page {h.start_page}</small><p>{h.excerpt}</p><div className="row-actions compact"><BusyButton className="danger" busy={busyId===`highlights:${h.id}`} busyLabel="Suppression…" onClick={()=>void remove('highlights', h.id)}>Supprimer</BusyButton></div></article>) : <div className="empty-panel"><strong>Votre parcours commence ici</strong><p>La reprise de lecture, les surlignages et les scores apparaîtront au fil de votre activité.</p></div>}
    </div>}
    {tab==='bookmarks'&&<div>{bookmarks.data?.length ? bookmarks.data.map(b=><article className="note-card" key={b.id}><small>{b.title} · page {b.start_page}</small><p>{b.excerpt}</p><div className="row-actions compact"><Link className="secondary" to={`/lecture?slug=${b.slug}`}>Ouvrir</Link><BusyButton className="danger" busy={busyId===`bookmarks:${b.id}`} busyLabel="Suppression…" onClick={()=>void remove('bookmarks', b.id)}>Retirer</BusyButton></div></article>) : <div className="empty-panel"><strong>Aucun favori</strong><p>Marquez un passage pendant la lecture pour le retrouver ici.</p></div>}</div>}
    {tab==='notes'&&<div>{notes.data?.length ? notes.data.map(n=><article className="note-card" key={n.id}><small>{n.title} · page {n.start_page}</small><p>{n.body}</p><div className="row-actions compact"><Link className="secondary" to={`/lecture?slug=${n.slug}`}>Ouvrir</Link><BusyButton className="danger" busy={busyId===`notes:${n.id}`} busyLabel="Suppression…" onClick={()=>void remove('notes', n.id)}>Supprimer</BusyButton></div></article>) : <div className="empty-panel"><strong>Aucune note</strong><p>Les notes personnelles restent rattachées au passage et à la version du livre.</p></div>}</div>}
  </main>
}
