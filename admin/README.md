# DevelopmentTranslation Bridge - Visual Interface

## Phase 1: Architecture & Foundation ✅

**Status**: Complete
**Version**: 3.2.1
**Built**: November 2025

---

## Overview

The Visual Interface is a modern, React-based editor that brings DevelopmentTranslation Bridge's Translation Bridge™ into a powerful visual environment. Built with enterprise-grade architecture, this interface provides side-by-side editing, live preview, AI-powered corrections, and intelligent tooltips for seamless page builder translation.

## Technology Stack

- **React 19** - Latest React for optimal performance
- **TypeScript 6** - Type-safe development
- **Vite 8** - Lightning-fast build tool
- **Monaco Editor** - VS Code's editor engine
- **TailwindCSS v4** - Modern utility-first CSS
- **Zustand** - Lightweight state management
- **React Query** - Server state management
- **Allotment** - Resizable split panes

## Quick Start

### Development

```bash
npm ci
npm run dev
```

The Vite dev server runs on `http://localhost:3000` with hot module replacement.

### Production Build

```bash
npm ci
npm run lint
npm run build
```

Output goes to `dist/` directory.

## Features Implemented ✅

### Core Components

1. **Side-by-Side Editor** - Resizable panels with source and translated code
2. **Monaco Editor** - VS Code-like editing with syntax highlighting for 10 frameworks
3. **Live Preview** - Sandboxed iframe rendering of translated HTML
4. **Correction Panel** - AI-powered corrections with one-click fixes
5. **Toolbar** - Translate, save, export, and theme controls
6. **Framework Selector** - Support for all 10 page builders

### Frameworks Supported

- Elementor
- Bricks Builder
- Oxygen Builder
- WPBakery
- Divi Builder
- Beaver Builder
- Gutenberg
- Avada Fusion
- Cornerstone
- Zion Builder

## Architecture

### Directory Structure

```
src/
├── components/          # React components
│   ├── SideBySideEditor.tsx
│   ├── Monaco/
│   ├── Preview/
│   ├── Corrections/
│   └── Layout/
├── store/              # Zustand state management
├── types/              # TypeScript definitions
├── services/           # API services (future)
├── features/           # Feature modules (future)
└── hooks/              # Custom React hooks
```

### State Management

Uses Zustand for lightweight, performant state:

- Editor state (frameworks, code)
- Corrections and tooltips
- User preferences
- Loading states
- Persistent storage

## WordPress Integration

### Accessing the Interface

1. Log into WordPress admin
2. Navigate to **Visual Interface** menu
3. React app loads in full-screen mode

### Dev vs Production

**Development** (`WP_DEBUG` enabled):
- Loads from Vite dev server
- Hot Module Replacement
- Fast refresh

**Production**:
- Loads from built assets in `dist/`
- Optimized bundles
- Code splitting

### API Integration

REST API base: `/wp-json/devtb/v2/`

Endpoints:
- `POST /translate` - Translate between frameworks
- `POST /corrections` - Get AI corrections (future)

## Performance

- Bundle: ~303 KB (gzipped: ~95 KB)
- CSS: ~19 KB (gzipped: ~5 KB)
- Build: <1 second
- Load: <500ms

## Next Steps (Phase 2+)

- AI Correction Engine integration
- Intelligent tooltip system
- Real-time collaboration (WebSocket)
- Project management
- Template library

## License

GPL-2.0+ - Same as DevelopmentTranslation Bridge

---

**DevelopmentTranslation Bridge** - Translation Bridge™ Visual Interface
Phase 1 Complete ✅
