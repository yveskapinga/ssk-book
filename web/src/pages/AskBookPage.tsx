import { useState, type FormEvent } from 'react'
import { useQuery } from '@tanstack/react-query'
import { ApiError, apiRequest } from '../api/client'
import { SearchableSelect } from '../components/SearchableSelect'
import { useAuth } from '../auth/AuthContext'

type Source = { id:string; start_page:number; end_page:number; content:string; score:number }
type Answer = { conversationId:string; answer:string; insufficientEvidence:boolean; sources:Source[] }
type Book = { slug:string; title:string }

export function AskBookPage() {
  const { token } = useAuth()
  const library = useQuery({ queryKey:['library'], queryFn:()=>apiRequest<{data:{items:Book[]}}>('/api/library', {}, token).then(r=>r.data.items) })
  const [slug,setSlug]=useState('')
  const [question,setQuestion]=useState('')
  const [conversationId,setConversationId]=useState<string>()
  const [answer,setAnswer]=useState<Answer>()
  const [error,setError]=useState('')
  const [busy,setBusy]=useState(false)
  const selected = slug || library.data?.[0]?.slug || ''

  async function submit(event:FormEvent){
    event.preventDefault()
    if (!selected) return setError('Aucun livre publié n’est disponible.')
    setBusy(true); setError('')
    try {
      const result=await apiRequest<{data:Answer}>(`/api/books/${selected}/questions`,{method:'POST',body:JSON.stringify({question,conversationId})},token)
      setAnswer(result.data); setConversationId(result.data.conversationId)
    } catch(reason){
      setError(reason instanceof ApiError?reason.message:'La question n’a pas pu être traitée.')
    } finally { setBusy(false) }
  }

  return <main className="workspace-page ask-page">
    <div className="ask-heading"><span className="eyebrow">Recherche intelligente</span><h1>Demander au livre</h1><p>Chaque réponse est produite à partir des passages publiés et affiche ses pages sources.</p></div>
    <form className="ask-form" onSubmit={submit}>
      {(library.data?.length ?? 0) > 0 && <SearchableSelect
        label="Livre"
        value={selected}
        onChange={next => { setSlug(next); setConversationId(undefined); setAnswer(undefined) }}
        options={(library.data ?? []).map(book => ({ value: book.slug, label: book.title }))}
        placeholder="Choisir un livre"
        required
      />}
      <textarea value={question} onChange={e=>setQuestion(e.target.value)} placeholder="Par exemple : quel aliment le SSK aimait-il manger ?" minLength={3} maxLength={1000} required/>
      <button className="primary" disabled={busy || !selected}>{busy?'Recherche…':'Poser la question'}</button>
    </form>
    {error&&<div className="form-error" role="alert">{error}</div>}
    {answer&&<section className="answer-card">
      <span className="eyebrow">{answer.insufficientEvidence?'Preuve insuffisante':'Réponse'}</span>
      <p className="answer-text">{answer.answer}</p>
      {answer.sources.length>0&&<div className="sources"><h2>Passages utilisés</h2>{answer.sources.map((s,i)=><details key={s.id}><summary>Source {i+1} · pages {s.start_page}{s.end_page!==s.start_page?`–${s.end_page}`:''}</summary><p>{s.content}</p></details>)}</div>}
    </section>}
  </main>
}
