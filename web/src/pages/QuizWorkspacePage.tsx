import { useState, type FormEvent } from 'react'
import { useQuery } from '@tanstack/react-query'
import { PageTabs } from '../components/PageTabs'
import { SearchableSelect } from '../components/SearchableSelect'
import { apiRequest, ApiError } from '../api/client'
import { useAuth } from '../auth/AuthContext'

type Quiz = { id:string; title:string; description:string|null; book_title:string; question_count:number|string }
type Question = { id:string; prompt:string; question_type:string; choices:{id:string;label:string}[] }
type Attempt = { id:string; quiz_id:string; title:string; status:string; score:number|string|null; completed_at:string|null }
type Rank = { display_name:string; best_score:number|string; attempts:number|string }
type Result = { attemptId:string; score:number; correct:number; total:number }

export function QuizWorkspacePage(){
  const { token } = useAuth()
  const [tab,setTab]=useState<'available'|'history'|'ranking'>('available')
  const [active,setActive]=useState<{quiz:Quiz; attemptId:string; questions:Question[]; index:number; answers:Record<string,string>}|null>(null)
  const [result,setResult]=useState<Result|null>(null)
  const [rankingQuiz,setRankingQuiz]=useState<string>('')
  const [error,setError]=useState('')
  const [busy,setBusy]=useState(false)

  const quizzes = useQuery({ queryKey:['quizzes'], queryFn:()=>apiRequest<{data:{items:Quiz[]}}>('/api/quizzes', {}, token).then(r=>r.data.items) })
  const history = useQuery({ queryKey:['quiz-history'], queryFn:()=>apiRequest<{data:{items:Attempt[]}}>('/api/me/quiz-attempts', {}, token).then(r=>r.data.items) })
  const ranking = useQuery({
    queryKey:['quiz-ranking', rankingQuiz],
    enabled: Boolean(rankingQuiz),
    queryFn:()=>apiRequest<{data:{items:Rank[]}}>(`/api/quizzes/${rankingQuiz}/ranking`, {}, token).then(r=>r.data.items),
  })

  async function start(quiz: Quiz) {
    setBusy(true); setError(''); setResult(null)
    try {
      const started = await apiRequest<{data:{attemptId:string; questions:Question[]}}>(`/api/quizzes/${quiz.id}/attempts`, { method:'POST' }, token)
      setActive({ quiz, attemptId: started.data.attemptId, questions: started.data.questions, index: 0, answers: {} })
    } catch (reason) { setError(reason instanceof ApiError ? reason.message : 'Le quiz n’a pas pu démarrer.') }
    finally { setBusy(false) }
  }

  async function submit(event: FormEvent) {
    event.preventDefault()
    if (!active) return
    setBusy(true); setError('')
    try {
      const answers = active.questions.map(q=>({ questionId: q.id, choiceId: active.answers[q.id] }))
      const graded = await apiRequest<{data:Result}>(`/api/quiz-attempts/${active.attemptId}`, { method:'POST', body: JSON.stringify({ answers }) }, token)
      setResult(graded.data); setActive(null); setTab('history')
    } catch (reason) { setError(reason instanceof ApiError ? reason.message : 'L’envoi des réponses a échoué.') }
    finally { setBusy(false) }
  }

  const question = active?.questions[active.index]
  return <main className="workspace-page">
    <div className="page-title"><div><span className="eyebrow">Apprentissage</span><h1>Quiz du livre</h1></div></div>
    {error && <div className="form-error" role="alert">{error}</div>}
    {result && <div className="form-success" role="status">Score {result.score} % · {result.correct}/{result.total} réponses justes.</div>}
    <PageTabs tabs={[
      {id:'available',label:'À faire',count:quizzes.data?.length},
      {id:'history',label:'Mes résultats',count:history.data?.length},
      {id:'ranking',label:'Classement'},
    ]} active={tab} onChange={setTab}/>

    {tab==='available'&&!active&&<div className="quiz-grid">
      {(quizzes.data??[]).map((quiz,index)=><article className="quiz-card" key={quiz.id}>
        <div className="quiz-top"><span className="quiz-number">{String(index+1).padStart(2,'0')}</span></div>
        <small>{quiz.book_title}</small><h2>{quiz.title}</h2>
        <div className="quiz-meta"><span>{Number(quiz.question_count)} questions</span><span>Validé par un administrateur</span></div>
        <div className="row-actions compact">
          <button className="primary" disabled={busy} onClick={()=>void start(quiz)}>Commencer le quiz</button>
          <button type="button" className="secondary" onClick={()=>{setRankingQuiz(quiz.id); setTab('ranking')}}>Classement</button>
        </div>
      </article>)}
      {quizzes.data?.length===0 && <div className="empty-panel"><strong>Aucun quiz publié</strong><p>Les quiz apparaissent ici après une revue humaine. Aucune question générée n’est publiée automatiquement.</p></div>}
    </div>}

    {tab==='available'&&active&&question&&<form className="quiz-play" onSubmit={submit}>
      <header><small>{active.quiz.title}</small><h2>Question {active.index+1} / {active.questions.length}</h2><p>{question.prompt}</p></header>
      <fieldset>{question.choices.map(choice=><label key={choice.id}><input type="radio" name={question.id} checked={active.answers[question.id]===choice.id} onChange={()=>setActive({...active, answers:{...active.answers, [question.id]:choice.id}})}/>{choice.label}</label>)}</fieldset>
      <footer>
        <button type="button" className="secondary" disabled={active.index===0} onClick={()=>setActive({...active, index:active.index-1})}>Précédent</button>
        {active.index < active.questions.length-1
          ? <button type="button" className="primary" disabled={!active.answers[question.id]} onClick={()=>setActive({...active, index:active.index+1})}>Question suivante</button>
          : <button className="primary" disabled={busy || Object.keys(active.answers).length!==active.questions.length}>Envoyer et voir le score</button>}
      </footer>
    </form>}

    {tab==='history'&&<div>{history.data?.length ? history.data.map(item=><article className="note-card" key={item.id}><small>{item.status==='COMPLETED'?'Terminé':'En cours'}</small><h2>{item.title}</h2><p>{item.score==null?'Score en attente':`${item.score} %`}</p></article>) : <div className="empty-panel"><strong>Aucune tentative enregistrée</strong><p>Vos scores et corrections apparaîtront ici.</p></div>}</div>}

    {tab==='ranking'&&<div className="ranking-panel">
      <SearchableSelect
        label="Quiz"
        value={rankingQuiz}
        onChange={setRankingQuiz}
        options={(quizzes.data??[]).map(q=>({ value:q.id, label:q.title, hint:q.book_title }))}
        placeholder="Choisir un quiz"
        allowEmpty
        emptyLabel="Choisir un quiz"
      />
      {ranking.data?.length ? ranking.data.map((row,index)=><article className="note-card" key={`${row.display_name}-${index}`}><small>#{index+1}</small><h2>{row.display_name}</h2><p>Meilleur score {row.best_score} % · {row.attempts} tentative(s)</p></article>) : <div className="empty-panel"><strong>Classement indisponible</strong><p>Le classement apparaît après les premières tentatives terminées.</p></div>}
    </div>}
  </main>
}
