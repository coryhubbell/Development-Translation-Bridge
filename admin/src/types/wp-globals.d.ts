// Ambient typings for WordPress data injected by the PHP visual interface.
// Must match the shape echoed in includes/class-devtb-visual-interface.php::render_page.

export {};

declare global {
  interface Window {
    devtbData?: {
      restUrl: string;
      nonce: string;
      userId: number;
      siteUrl: string;
      adminUrl: string;
      version: string;
    };
  }
}
