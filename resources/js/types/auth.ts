export type AuthField = { name: string; label: string; type?: string; autocomplete?: string; value?: string; error?: string };
export type AuthProps = {
  mode: 'login' | 'signup' | 'forgot' | 'reset' | 'verify';
  account: 'customer' | 'organiser';
  action: string;
  csrf: string;
  fields: AuthField[];
  loginUrl: string;
  signupUrl: string;
  forgotUrl: string;
  homeUrl: string;
  status?: string;
  alert?: string;
  captchaHtml?: string;
  hiddenFields?: Record<string, string>;
};
