/**
 * Preview Pane Component
 * Renders live preview of translated code in an iframe
 */

import React, { useRef, useEffect } from 'react';

interface PreviewPaneProps {
  code: string;
}

function PreviewPane({ code }: PreviewPaneProps) {
  const iframeRef = useRef<HTMLIFrameElement>(null);

  useEffect(() => {
    if (!iframeRef.current) return;

    const iframe = iframeRef.current;
    const iframeDoc = iframe.contentDocument || iframe.contentWindow?.document;

    if (!iframeDoc) return;

    // Create preview HTML with WordPress styling
    const previewHTML = `
      <!DOCTYPE html>
      <html lang="en">
      <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Preview</title>
        <style>
          * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
          }

          body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, 'Helvetica Neue', sans-serif;
            padding: 20px;
            background: #f0f0f1;
            color: #1e1e1e;
          }

          .preview-container {
            background: white;
            padding: 20px;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
          }

          .preview-notice {
            background: #e7f5fe;
            border-left: 4px solid #0073aa;
            padding: 12px;
            margin-bottom: 20px;
            color: #0073aa;
          }

          img {
            max-width: 100%;
            height: auto;
          }

          /* Basic element styling */
          h1, h2, h3, h4, h5, h6 {
            margin-bottom: 0.5em;
            color: #1e1e1e;
          }

          p {
            margin-bottom: 1em;
            line-height: 1.6;
          }

          a {
            color: #0073aa;
            text-decoration: none;
          }

          a:hover {
            color: #005177;
            text-decoration: underline;
          }

          button {
            background: #0073aa;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 14px;
          }

          button:hover {
            background: #005177;
          }
        </style>
      </head>
      <body>
        <div class="preview-container">
          <div class="preview-notice">
            <strong>Live Preview</strong> - This is a simplified preview. Actual rendering may vary in WordPress.
          </div>
          <div id="preview-content">
            ${code || '<p style="color: #999; text-align: center;">No content to preview yet. Start coding in the editor!</p>'}
          </div>
        </div>

        <script>
          // Prevent links from navigating in preview
          document.addEventListener('click', function(e) {
            if (e.target.tagName === 'A') {
              e.preventDefault();
              console.log('Link clicked:', e.target.href);
            }
          });
        </script>
      </body>
      </html>
    `;

    iframeDoc.open();
    iframeDoc.write(previewHTML);
    iframeDoc.close();
  }, [code]);

  return (
    <div className="h-full w-full bg-gray-100 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700">
      <div className="h-full p-2">
        <iframe
          ref={iframeRef}
          className="w-full h-full bg-white rounded border border-gray-200 dark:border-gray-700"
          title="Preview"
          sandbox="allow-scripts allow-same-origin"
        />
      </div>
    </div>
  );
}

export default PreviewPane;
