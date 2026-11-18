import {CalendarDays,Mail,MapPin,Phone,Ticket,UserRound} from 'lucide-react';
import {Button} from '../components/ui/Button';
import {DashboardCard,DashboardHeader,DashboardPage,EmptyState,IconTile} from '../components/ui/Dashboard';
import type {BookingSummary} from '../types/bookings';

type AccountProps={name:string;email?:string;phone?:string;location?:string;bookings:BookingSummary[];bookingsUrl:string;editUrl:string};

export function AccountPage({props}:{props:AccountProps}){
  return <DashboardPage>
    <DashboardHeader title={`Welcome back, ${props.name}`} intro="Manage your details and bookings." action={<Button asChild><a href={props.editUrl}>Edit profile</a></Button>}/>
    <section className="section shell">
      <div className="grid gap-6 lg:grid-cols-[.72fr_1.28fr]">
        <DashboardCard>
          <IconTile accent><UserRound size={20}/></IconTile>
          <h2 className="display mt-5 text-2xl">Account details</h2>
          <dl className="mt-6 grid gap-4 text-sm">
            {props.email&&<div><dt className="flex items-center gap-2 font-semibold"><Mail size={16} className="text-primary"/> Email</dt><dd className="mt-1 break-words text-soft">{props.email}</dd></div>}
            {props.phone&&<div><dt className="flex items-center gap-2 font-semibold"><Phone size={16} className="text-primary"/> Phone</dt><dd className="mt-1 text-soft">{props.phone}</dd></div>}
            {props.location&&<div><dt className="flex items-center gap-2 font-semibold"><MapPin size={16} className="text-primary"/> Location</dt><dd className="mt-1 text-soft">{props.location}</dd></div>}
          </dl>
        </DashboardCard>
        <div>
          <div className="flex items-center justify-between gap-4"><div className="flex items-center gap-3"><IconTile><Ticket size={20}/></IconTile><h2 className="display text-2xl sm:text-3xl">Recent bookings</h2></div><a href={props.bookingsUrl} className="hidden font-semibold text-primary sm:block">View all</a></div>
          {props.bookings.length?<div className="mt-5 grid gap-3">{props.bookings.map(booking=><article className="grid gap-4 rounded-xl border border-line bg-white p-5 sm:grid-cols-[1fr_auto] sm:items-center" key={booking.id}><div className="min-w-0"><a href={booking.eventUrl} className="break-words text-lg font-semibold text-navy">{booking.title}</a><p className="mt-2 flex items-center gap-2 text-sm text-soft"><CalendarDays size={16} className="shrink-0 text-orange"/>{booking.eventDate}</p><p className="mt-2 text-xs font-semibold text-primary">Booking #{booking.reference}</p></div><Button asChild size="sm"><a href={booking.detailsUrl}>View booking</a></Button></article>)}</div>:<div className="mt-5"><EmptyState icon={<Ticket/>} title="No bookings yet" body="Your upcoming plans will appear here."/></div>}
        </div>
      </div>
    </section>
  </DashboardPage>;
}
