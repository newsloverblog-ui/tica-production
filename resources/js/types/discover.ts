export type DiscoverEvent = {
  id: number;
  title: string;
  url: string;
  image: string;
  day: string;
  dateNumber: string;
  month: string;
  dateLabel: string;
  time: string;
  location: string;
  price: string;
  category: string;
};

export type DiscoverProps = {
  action: string;
  clearUrl: string;
  totalLabel: string;
  events: DiscoverEvent[];
  categories: Array<{ slug: string; name: string }>;
  filters: { search: string; location: string; category: string; event: string; dates: string };
  activeCategory: string;
  paginationHtml: string;
};
