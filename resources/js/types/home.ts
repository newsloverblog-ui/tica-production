import type {EventSummary} from './models';
export type HomeProps = {
  events: EventSummary[];
  heroImages: string[];
  categories: {name:string;slug:string}[];
  cities: string[];
  routes: { discover:string; create:string; pricing:string; about:string };
};
