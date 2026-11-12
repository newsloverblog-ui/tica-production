export type ContentPageName = 'about' | 'contact' | 'pricing';
export type ContentPageProps = {
  page: ContentPageName;
  contact?: {
    action: string;
    csrf: string;
    address: string;
    email: string;
    phone: string;
    success?: string;
    error?: string;
    errors?: Record<string, string>;
    old?: Record<string, string>;
    captchaHtml?: string;
  };
};
