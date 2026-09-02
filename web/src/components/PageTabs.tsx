export type Tab<T extends string> = { id:T; label:string; count?:number }

export function PageTabs<T extends string>({ tabs, active, onChange }: { tabs:Tab<T>[]; active:T; onChange:(tab:T)=>void }) {
  return <div className="page-tabs" role="tablist">{tabs.map(tab=><button key={tab.id} role="tab" aria-selected={active===tab.id} className={active===tab.id?'active':''} onClick={()=>onChange(tab.id)}>{tab.label}{tab.count!==undefined&&<span>{tab.count}</span>}</button>)}</div>
}
