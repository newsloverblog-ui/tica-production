import {Bookmark,MapPin} from 'lucide-react';
import {useState} from 'react';
import type {EventSummary} from '../../types/models';
import {formatMoney} from '../../lib/utils';

export function EventCard({event}:{event:EventSummary}){
  const [saved,setSaved]=useState(false);
  return <article className="group flex min-w-0 flex-col overflow-hidden rounded-xl border border-line bg-white">
    <div className="relative aspect-[4/5] overflow-hidden bg-tint">
      <a href={event.url} aria-label={`View ${event.name}`}><img className="size-full object-cover transition-transform duration-500 motion-safe:group-hover:scale-[1.03]" src={event.image} alt={`${event.name} event`} loading="lazy"/></a>
      <button type="button" aria-label={saved?'Remove saved event':'Save event'} aria-pressed={saved} onClick={()=>setSaved(value=>!value)} className="focus-ring absolute right-3 top-3 grid size-10 place-items-center rounded-full border border-line bg-white text-navy"><Bookmark size={19} className={saved?'fill-orange text-orange':''}/></button>
    </div>
    <div className="flex flex-1 flex-col p-4">
      <p className="mb-2 text-xs font-semibold text-primary">{event.date} {event.month} · {event.category}</p>
      <h3 className="text-lg font-semibold leading-tight"><a href={event.url}>{event.name}</a></h3>
      <p className="mt-2 flex items-center gap-1.5 text-sm text-soft"><MapPin size={15}/>{event.venue}{event.city?`, ${event.city}`:''}</p>
      <p className="mt-1 text-xs text-soft">By {event.organiser}</p>
      <div className="mt-auto flex items-end justify-between gap-3 pt-5"><b>{event.priceLabel||formatMoney(event.priceKobo)}</b><a className="focus-ring rounded-lg border border-line px-4 py-2.5 text-sm font-semibold text-navy hover:border-primary hover:text-primary" href={event.url}>View event</a></div>
    </div>
  </article>;
}
