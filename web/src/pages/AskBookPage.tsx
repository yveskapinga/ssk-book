import { useEffect, useState, type FormEvent } from 'react'
import { useQuery } from '@tanstack/react-query'
import { ApiError, apiRequest } from '../api/client'
import { BusyButton } from '../components/BusyButton'
import { OperationProgress } from '../components/OperationProgress'
import { SearchableSelect } from '../components/SearchableSelect'
import { useAuth } from '../auth/AuthContext'
import { useAppError } from '../lib/AppError'

type Source = { id:string; start_page:number; end_page:number; content:string; score:number }
type Answer = { conversationId:string; answer:string; insufficientEvidence:boolean; sources:Source[] }
type Book = { slug:string; title:string }

function formatAskError(reason: unknown, fallback: string): string {
  if (!(reason instanceof ApiError)) return fallback
  return reason.correlationId ? `${reason.message} (réf. ${reason.correlationId})` : reason.message
}

export function AskBookPage() {
  const { token } = useAuth()
  const { report } = useAppError()
  const library = useQuery({ queryKey:['library'], queryFn:()=>apiRequest<{data:{items:Book[]}}>('/api/library', {}, token).then(r=>r.data.items) })
  const [slug,setSlug]=useState('')
  const [question,setQuestion]=useState('')
  const [conversationId,setConversationId]=useState<string>()
  const [answer,setAnswer]=useState<Answer>()
  const [error,setError]=useState('')
  const [busy,setBusy]=useState(false)
  const selected = slug || library.data?.[0]?.slug || ''

  useEffect(() => {
    if (!library.error) return
    report(library.error, 'La bibliothèque n’a pas pu être chargée.')
    setError(formatAskError(library.error, 'La bibliothèque n’a pas pu être chargée.'))
  }, [library.error, report])

  async function submit(event:FormEvent){
    event.preventDefault()
    if (!selected) return setError('Aucun livre publié n’est disponible.')
    setBusy(true); setError('')
    try {
      const result=await apiRequest<{data:Answer}>(`/api/books/${selected}/questions`,{method:'POST',body:JSON.stringify({question,conversationId})},token)
      setAnswer(result.data); setConversationId(result.data.conversationId)
    } catch(reason){
      report(reason, 'La question n’a pas pu être traitée.')
      setError(formatAskError(reason, 'La question n’a pas pu être traitée.'))
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
      <textarea value={question} onChange={e=>setQuestion(e.target.value)} placeholder="Par exemple : quel aliment le SSK aimait-il manger ?" minLength={3} maxLength={1000} required disabled={busy}/>
      {busy && <OperationProgress title="Recherche dans le livre" detail="Embedding de la question et sélection des passages sources…" indeterminate />}
      <BusyButton className="primary" type="submit" busy={busy} busyLabel="Recherche…" disabled={!selected}>Poser la question</BusyButton>
    </form>
    {error&&<div className="form-error" role="alert">{error}</div>}
    {answer&&<section className="answer-card">
      <span className="eyebrow">{answer.insufficientEvidence?'Preuve insuffisante':'Réponse'}</span>
      <p className="answer-text">{answer.answer}</p>
      {answer.sources.length>0&&<div className="sources"><h2>Passages utilisés</h2>{answer.sources.map((s,i)=><details key={s.id}><summary>Source {i+1} · pages {s.start_page}{s.end_page!==s.start_page?`–${s.end_page}`:''}</summary><p>{s.content}</p></details>)}</div>}
    </section>}
  </main>
}
