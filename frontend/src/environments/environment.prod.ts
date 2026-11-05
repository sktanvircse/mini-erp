export const environment = {
  production: true,
  // By default uses '/api' so Vercel rewrites proxy seamlessly to your backend without CORS issues.
  // Can also be set dynamically via window.__env.apiUrl in index.html if needed.
  apiUrl: (typeof window !== 'undefined' && (window as any).__env?.apiUrl) || '/api'
};
