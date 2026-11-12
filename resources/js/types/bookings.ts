export type BookingSummary={id:number;title:string;eventUrl:string;detailsUrl:string;reference:string;eventDate:string;bookedAt:string};
export type HeldTicket={id:number;title:string;sequence:number;holder:string;variation?:string;qrUrl:string;pendingEmail?:string;cancelUrl?:string;transferUrl?:string};
export type BookingsProps={bookings:BookingSummary[];heldTickets:HeldTicket[];csrf:string;paginationHtml?:string;dashboardUrl:string};
