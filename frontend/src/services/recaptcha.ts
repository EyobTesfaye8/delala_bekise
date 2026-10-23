declare global {
  interface Window {
    grecaptcha?: {
      ready: (callback: () => void) => void;
      execute: (siteKey: string, options: { action: string }) => Promise<string>;
    };
  }
}

/**
 * Retrieves a Google reCAPTCHA v3 token.
 * Defaults to "test_token" in local/dev environments per Delala Backend spec.
 */
export async function getRecaptchaToken(action: string = 'send_otp'): Promise<string> {
  const siteKey = import.meta.env.VITE_RECAPTCHA_SITE_KEY;

  // In development mode or if site key is not configured, use backend bypass token
  if (import.meta.env.DEV || !siteKey) {
    return 'test_token';
  }

  // Load Google reCAPTCHA v3 script if not present
  if (!window.grecaptcha) {
    await new Promise<void>((resolve, reject) => {
      const script = document.createElement('script');
      script.src = `https://www.google.com/recaptcha/api.js?render=${siteKey}`;
      script.async = true;
      script.defer = true;
      script.onload = () => resolve();
      script.onerror = () => reject(new Error('Failed to load Google reCAPTCHA script'));
      document.head.appendChild(script);
    });
  }

  return new Promise((resolve) => {
    window.grecaptcha?.ready(async () => {
      try {
        const token = await window.grecaptcha?.execute(siteKey, { action });
        resolve(token || 'test_token');
      } catch {
        resolve('test_token');
      }
    });
  });
}
