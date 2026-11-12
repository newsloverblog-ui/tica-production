import {Search,SlidersHorizontal} from 'lucide-react';
import {EventCard} from '../components/app/EventCard';
import {Button} from '../components/ui/Button';
import {Dialog} from '../components/ui/Dialog';
import type {DiscoverProps} from '../types/discover';

function Filters({props}:{props:DiscoverProps}){return <div className="grid gap-4"><label className="text-sm font-bold">City<input name="location" defaultValue={props.filters.location} placeholder="All cities" className="mt-2 min-h-12 w-full rounded-xl border border-line bg-white px-3"/></label><label className="text-sm font-bold">Category<select name="category" defaultValue={props.filters.category} className="mt-2 min-h-12 w-full rounded-xl border border-line bg-white px-3"><option value="">All categories</option>{props.categories.map(item=><option value={item.slug} key={item.slug}>{item.name}</option>)}</select></label><label className="text-sm font-bold">Event type<select name="event" defaultValue={props.filters.event} className="mt-2 min-h-12 w-full rounded-xl border border-line bg-white px-3"><option value="">All events</option><option value="venue">In person</option><option value="online">Online</option></select></label><label className="text-sm font-bold">Dates<input name="dates" defaultValue={props.filters.dates} placeholder="YYYY-MM-DD - YYYY-MM-DD" className="mt-2 min-h-12 w-full rounded-xl border border-line bg-white px-3"/></label></div>;}

export function DiscoverPage({props}:{props:DiscoverProps}){
  const events=props.events.map(event=>({id:event.id,slug:String(event.id),name:event.title,date:event.dateNumber,month:event.month,venue:event.location,city:'',image:event.image,priceKobo:0,priceLabel:event.price,category:event.category||props.activeCategory||'Event',organiser:'TICA organiser',url:event.url}));
  return <main>
    <section className="bg-tint"><div className="shell py-14 sm:py-20"><h1 className="display text-[clamp(42px,6vw,76px)]">Discover events</h1><p className="mt-4 max-w-xl text-lg text-soft">Find your next story in Lagos, Abuja and beyond.</p></div></section>
    <section className="section shell">
      <form action={props.action} method="GET" className="mb-8 grid gap-3 rounded-2xl bg-white p-3 shadow-card md:grid-cols-[1.7fr_repeat(3,1fr)]">
        <label className="flex min-h-12 items-center gap-3 rounded-xl bg-tint px-4"><Search size={18}/><input name="search-input" className="w-full bg-transparent outline-none" placeholder="Search events or places" defaultValue={props.filters.search}/></label>
        <label className="hidden md:block"><span className="sr-only">City</span><input name="location" defaultValue={props.filters.location} placeholder="Lagos" className="min-h-12 w-full rounded-xl bg-white px-4 font-semibold outline-none"/></label>
        <label className="hidden md:block"><span className="sr-only">Dates</span><input name="dates" defaultValue={props.filters.dates} placeholder="This month" className="min-h-12 w-full rounded-xl bg-white px-4 font-semibold outline-none"/></label>
        <label className="hidden md:block"><span className="sr-only">Category</span><select name="category" defaultValue={props.filters.category} className="min-h-12 w-full rounded-xl bg-white px-4 font-semibold outline-none"><option value="">All categories</option>{props.categories.map(item=><option value={item.slug} key={item.slug}>{item.name}</option>)}</select></label>
        <button className="sr-only" type="submit">Search</button>
        <div className="md:hidden"><Dialog title="Filter events" trigger={<Button className="w-full" variant="outline"><SlidersHorizontal size={17}/> Filter events</Button>}><form action={props.action} method="GET"><input type="hidden" name="search-input" value={props.filters.search}/><Filters props={props}/><Button className="mt-5 w-full" type="submit">Apply filters</Button></form></Dialog></div>
      </form>
      <div className="mb-6 flex items-baseline justify-between"><h2 className="text-xl font-bold">All events</h2><span className="text-sm text-soft">{props.totalLabel}</span></div>
      {events.length?<><div className="grid grid-cols-2 gap-x-3 gap-y-5 md:grid-cols-3 lg:grid-cols-4 lg:gap-6">{events.map(event=><EventCard event={event} key={event.id}/>)}</div><div className="tica-pagination" dangerouslySetInnerHTML={{__html:props.paginationHtml}}/></>:<div className="rounded-2xl bg-tint p-10 text-center"><h2 className="text-xl font-bold">No events match that search</h2><p className="mt-2 text-soft">Try another name or clear your filters.</p><Button asChild className="mt-5"><a href={props.clearUrl}>Clear filters</a></Button></div>}
    </section>
  </main>;
}
