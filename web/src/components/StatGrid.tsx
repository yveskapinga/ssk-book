export function StatGrid({ items }: { items:{label:string;value:string|number;detail?:string;tone?:'good'|'warn'|'neutral'}[] }) {
  return <div className="stat-grid">{items.map(item=><article className={`stat-card ${item.tone??'neutral'}`} key={item.label}><small>{item.label}</small><strong>{item.value}</strong>{item.detail&&<span>{item.detail}</span>}</article>)}</div>
}
