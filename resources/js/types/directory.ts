export type DirectoryCard = { title: string; eyebrow?: string; summary?: string; image?: string; href?: string; meta?: string };
export type FaqItem = { question: string; answer: string };
export type DirectoryProps = {
  variant: 'faq' | 'blogs' | 'organisers' | 'article';
  title: string;
  intro: string;
  cards?: DirectoryCard[];
  faqs?: FaqItem[];
  articleHtml?: string;
  articleImage?: string;
  previousUrl?: string | null;
  nextUrl?: string | null;
  search?: { action: string; value?: string; name?: string; placeholder?: string };
};
