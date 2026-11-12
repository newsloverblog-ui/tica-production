export interface EventSummary {
  id:number;
  slug:string;
  name:string;
  date:string;
  month:string;
  venue:string;
  city:string;
  image:string;
  priceKobo:number;
  priceLabel?:string;
  category:string;
  organiser:string;
  url:string;
  remaining?:number;
}

export interface TicketTier { id:number; name:string; description:string; priceKobo:number; remaining:number; }
export interface DashboardMetric { label:string; value:string; change:string; }
