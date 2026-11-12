import {Bookmark,MapPin} from 'lucide-react';
import {useState} from 'react';
import type {EventSummary} from '../../types/models';
import {formatMoney} from '../../lib/utils';

export function EventCard({event}:{event:EventSummary}){
  const [saved,setSaved]=useState(false);
  return <article className="group flex min-w-0 flex-col overflow-hidden rounded-2xl bg-white shadow-card transition-shadow hover:shadow-float">
    <div className="relative aspect-[4/5] overflow-hidden bg-tint">
      <a href={event.url} aria-label={`View ${event.name}`}><img className="size-full object-cover transition-transform duration-500 motion-safe:group-hover:scale-[1.03]" src={event.image} alt={`${event.name} event`} loading="lazy"/></a>
      <button type="button" aria-label={saved?'Remove saved event':'Save event'} aria-pressed={saved} onClick={()=>setSaved(value=>!value)} className="focus-ring absolute right-3 top-3 grid size-11 place-items-center rounded-full bg-white/95 text-navy shadow-card"><Bookmark size={20} className={saved?'fill-orange text-orange':''}/></button>
      <div className="absolute bottom-0 left-4 translate-y-3 rounded-t-full rounded-b-lg bg-white px-3 py-2 text-center text-navy shadow-card"><b className="block text-xl leading-none">{event.date}</b><span className="text-[11px] font-extrabold">{event.month}</span></div>
    </div>
    <div className="flex flex-1 flex-col p-4 pt-7">
      <span className="mb-2 w-fit rounded-full bg-peach px-2.5 py-1 text-xs font-bold text-[#8f3d05]">{event.category}</span>
      <h3 className="text-lg font-bold leading-tight"><a href={event.url}>{event.name}</a></h3>
      <p className="mt-2 flex items-center gap-1.5 text-sm text-soft"><MapPin size={15}/>{event.venue}{event.city?`, ${event.city}`:''}</p>
      <p className="mt-1 text-xs text-soft">By {event.organiser}</p>
      <div className="mt-auto flex items-end justify-between gap-3 pt-5"><div><span className="block text-[11px] text-soft">From</span><b>{event.priceLabel||formatMoney(event.priceKobo)}</b></div><a className="focus-ring rounded-full bg-navy px-4 py-3 text-sm font-bold text-white" href={event.url}>Get tickets</a></div>
    </div>
  </article>;
}
