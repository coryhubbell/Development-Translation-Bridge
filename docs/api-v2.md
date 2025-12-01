# REST API v2 Documentation

**DevelopmentTranslation Bridge Translation Bridge**
**Version:** 3.2.0
**API Version:** 2.0
**Base URL:** `/wp-json/devtb/v2`

---

## Table of Contents

- [Overview](#overview)
- [Authentication](#authentication)
- [Rate Limiting](#rate-limiting)
- [Endpoints](#endpoints)
- [Webhooks](#webhooks)
- [Error Handling](#error-handling)
- [Code Examples](#code-examples)

---

## Overview

The DevelopmentTranslation Bridge REST API v2 provides programmatic access to the Translation Bridge, allowing you to:

- Convert content between any of the 10 supported frameworks
- Process batch translations asynchronously
- Validate framework content
- Manage API keys
- Track job status in real-time
- Receive webhook notifications

### Supported Frameworks

1. Bootstrap 5.3.3
2. DIVI Builder
3. Elementor
4. Avada Fusion
5. Bricks Builder
6. WPBakery Page Builder
7. Beaver Builder
8. Gutenberg Block Editor
9. Oxygen Builder
10. Claude AI-Optimized HTML

**Total Translation Pairs:** 90 (10 frameworks × 9 possible targets)

---

## Authentication

The API supports multiple authentication methods:

### 1. API Key Authentication (Recommended)

Include your API key in the request header:

```bash
X-API-Key: devtb_your_api_key_here
```

Or as a query parameter:

```bash
?api_key=devtb_your_api_key_here
```

Or in the Authorization header:

```bash
Authorization: Bearer devtb_your_api_key_here
```

### 2. WordPress Session Authentication

If you're logged into WordPress, you can use cookie-based authentication. Requires `edit_posts` capability.

### Generating API Keys

**Via REST API:**
```bash
curl -X POST https://yoursite.com/wp-json/devtb/v2/api-keys \
  -u username:password \
  -H "Content-Type: application/json" \
  -d '{
    "name": "My Integration",
    "tier": "premium",
    "permissions": ["read", "write"]
  }'
```

**Response:**
```json
{
  "success": true,
  "message": "API key created successfully. Store it securely - it won't be shown again.",
  "key": "devtb_a1b2c3d4e5f6...",
  "name": "My Integration",
  "tier": "premium",
  "permissions": ["read", "write"],
  "created_at": "2025-01-17 10:30:00"
}
```

⚠️ **Important:** Store your API key securely. It will only be shown once.

---

## Rate Limiting

API requests are rate-limited by tier to prevent abuse.

### Rate Limit Tiers

| Tier | Requests/Hour | Requests/Minute | Burst Limit | Cost |
|------|---------------|-----------------|-------------|------|
| **Free** | 100 | 20 | 5 | Free |
| **Basic** | 500 | 50 | 10 | Contact for pricing |
| **Premium** | 2,000 | 100 | 20 | Contact for pricing |
| **Enterprise** | 10,000 | 500 | 50 | Contact for pricing |

### Rate Limit Headers

Every API response includes rate limit information:

```
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 95
X-RateLimit-Reset: 1705492800
```

### Rate Limit Exceeded Response

**Status Code:** `429 Too Many Requests`

```json
{
  "code": "devtb_rate_limit_exceeded",
  "message": "Rate limit exceeded. Please retry after 45 seconds.",
  "data": {
    "status": 429,
    "headers": {
      "Retry-After": "45"
    }
  }
}
```

---

## Endpoints

### 1. API Status

Get API status and available features.

**Endpoint:** `GET /wp-json/devtb/v2/status`
**Authentication:** None required

**Request:**
```bash
curl https://yoursite.com/wp-json/devtb/v2/status
```

**Response:**
```json
{
  "success": true,
  "version": "2.0",
  "status": "operational",
  "features": {
    "single_translation": true,
    "batch_translation": true,
    "async_processing": true,
    "validation": true,
    "webhooks": true,
    "api_key_auth": true,
    "rate_limiting": true
  },
  "timestamp": "2025-01-17 10:30:00"
}
```

---

### 2. List Frameworks

Get all supported frameworks with metadata.

**Endpoint:** `GET /wp-json/devtb/v2/frameworks`
**Authentication:** None required

**Request:**
```bash
curl https://yoursite.com/wp-json/devtb/v2/frameworks
```

**Response:**
```json
{
  "success": true,
  "total_frameworks": 10,
  "translation_pairs": 90,
  "frameworks": {
    "bootstrap": {
      "name": "Bootstrap 5.3.3",
      "type": "HTML/CSS",
      "extension": "html",
      "description": "Clean HTML/CSS framework, perfect for Claude AI"
    },
    "gutenberg": {
      "name": "Gutenberg Block Editor",
      "type": "HTML Comments",
      "extension": "html",
      "description": "WordPress native block editor with 50+ core blocks"
    }
    // ... other frameworks
  }
}
```

---

### 3. Single Translation

Convert content from one framework to another.

**Endpoint:** `POST /wp-json/devtb/v2/translate`
**Authentication:** Required

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `source` | string | Yes | Source framework name |
| `target` | string | Yes | Target framework name |
| `content` | string | Yes | Content to translate |
| `options` | object | No | Additional options |

**Request:**
```bash
curl -X POST https://yoursite.com/wp-json/devtb/v2/translate \
  -H "X-API-Key: devtb_your_key" \
  -H "Content-Type: application/json" \
  -d '{
    "source": "bootstrap",
    "target": "elementor",
    "content": "<div class=\"container\"><h1>Hello World</h1></div>"
  }'
```

**Response:**
```json
{
  "success": true,
  "source": "bootstrap",
  "target": "elementor",
  "result": "[{\"id\":\"abc123\",\"elType\":\"container\"...}]",
  "elapsed_time": 0.234,
  "stats": {
    "components_parsed": 2,
    "components_converted": 2
  },
  "timestamp": "2025-01-17 10:30:00"
}
```

---

### 4. Batch Translation

Translate content to multiple frameworks at once.

**Endpoint:** `POST /wp-json/devtb/v2/batch-translate`
**Authentication:** Required

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `source` | string | Yes | Source framework name |
| `targets` | array | Yes | Array of target framework names |
| `content` | string | Yes | Content to translate |
| `async` | boolean | No | Process asynchronously (default: false) |

**Synchronous Request:**
```bash
curl -X POST https://yoursite.com/wp-json/devtb/v2/batch-translate \
  -H "X-API-Key: devtb_your_key" \
  -H "Content-Type: application/json" \
  -d '{
    "source": "bootstrap",
    "targets": ["elementor", "divi", "gutenberg"],
    "content": "<div class=\"container\">...</div>",
    "async": false
  }'
```

**Synchronous Response:**
```json
{
  "success": true,
  "source": "bootstrap",
  "total": 3,
  "successful": 3,
  "failed": 0,
  "results": {
    "elementor": {
      "success": true,
      "result": "[{...}]",
      "stats": {...}
    },
    "divi": {
      "success": true,
      "result": "[et_pb_section...]",
      "stats": {...}
    },
    "gutenberg": {
      "success": true,
      "result": "<!-- wp:group -->...",
      "stats": {...}
    }
  },
  "elapsed_time": 1.456,
  "timestamp": "2025-01-17 10:30:00"
}
```

**Async Request:**
```bash
curl -X POST https://yoursite.com/wp-json/devtb/v2/batch-translate \
  -H "X-API-Key: devtb_your_key" \
  -H "Content-Type: application/json" \
  -d '{
    "source": "gutenberg",
    "targets": ["elementor", "divi", "bricks", "oxygen"],
    "content": "<!-- wp:paragraph -->...",
    "async": true
  }'
```

**Async Response:**
```json
{
  "success": true,
  "job_id": "devtb_1705492800_abc123",
  "status": "queued",
  "message": "Batch translation job created. Check status at /wp-json/devtb/v2/job/devtb_1705492800_abc123"
}
```

---

### 5. Job Status

Get the status of an async job.

**Endpoint:** `GET /wp-json/devtb/v2/job/{job_id}`
**Authentication:** Required

**Request:**
```bash
curl https://yoursite.com/wp-json/devtb/v2/job/devtb_1705492800_abc123 \
  -H "X-API-Key: devtb_your_key"
```

**Response (Processing):**
```json
{
  "job_id": "devtb_1705492800_abc123",
  "status": "processing",
  "source": "gutenberg",
  "targets": ["elementor", "divi", "bricks", "oxygen"],
  "total": 4,
  "progress": 50,
  "successful": 2,
  "failed": 0,
  "results": {
    "elementor": {
      "success": true,
      "result": "[{...}]"
    },
    "divi": {
      "success": true,
      "result": "[et_pb_section...]"
    }
  },
  "created_at": "2025-01-17 10:30:00",
  "updated_at": "2025-01-17 10:30:15"
}
```

**Response (Completed):**
```json
{
  "job_id": "devtb_1705492800_abc123",
  "status": "completed",
  "source": "gutenberg",
  "total": 4,
  "successful": 4,
  "failed": 0,
  "results": {
    "elementor": {...},
    "divi": {...},
    "bricks": {...},
    "oxygen": {...}
  },
  "elapsed_time": 2.345,
  "created_at": "2025-01-17 10:30:00",
  "completed_at": "2025-01-17 10:30:30"
}
```

**Job Status Values:**
- `queued` - Job is waiting to be processed
- `processing` - Job is currently being processed
- `completed` - Job finished successfully
- `failed` - Job failed with errors
- `cancelled` - Job was cancelled

---

### 6. Validate Content

Validate framework content before translation.

**Endpoint:** `POST /wp-json/devtb/v2/validate`
**Authentication:** Required

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `framework` | string | Yes | Framework to validate against |
| `content` | string | Yes | Content to validate |

**Request:**
```bash
curl -X POST https://yoursite.com/wp-json/devtb/v2/validate \
  -H "X-API-Key: devtb_your_key" \
  -H "Content-Type: application/json" \
  -d '{
    "framework": "elementor",
    "content": "[{\"id\":\"abc\",\"elType\":\"container\"}]"
  }'
```

**Response:**
```json
{
  "success": true,
  "valid": true,
  "framework": "elementor",
  "component_count": 5,
  "component_types": {
    "container": 2,
    "heading": 1,
    "text": 1,
    "button": 1
  },
  "timestamp": "2025-01-17 10:30:00"
}
```

---

### 7. List API Keys

Get all API keys for the current user.

**Endpoint:** `GET /wp-json/devtb/v2/api-keys`
**Authentication:** Required (Admin)

**Request:**
```bash
curl https://yoursite.com/wp-json/devtb/v2/api-keys \
  -u username:password
```

**Response:**
```json
{
  "success": true,
  "keys": [
    {
      "key_preview": "devtb_a1b2c3d4...e5f6",
      "name": "My Integration",
      "tier": "premium",
      "permissions": ["read", "write"],
      "status": "active",
      "created_at": "2025-01-17 10:00:00",
      "last_used": "2025-01-17 10:30:00"
    }
  ],
  "total": 1
}
```

---

### 8. Create API Key

Generate a new API key.

**Endpoint:** `POST /wp-json/devtb/v2/api-keys`
**Authentication:** Required (Admin)

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `name` | string | No | Descriptive name for the key |
| `permissions` | array | No | Permissions (default: ["read", "write"]) |
| `tier` | string | No | Rate limit tier (default: "free") |

**Request:**
```bash
curl -X POST https://yoursite.com/wp-json/devtb/v2/api-keys \
  -u username:password \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Production API",
    "tier": "premium",
    "permissions": ["read", "write"]
  }'
```

**Response:**
```json
{
  "success": true,
  "message": "API key created successfully. Store it securely - it won't be shown again.",
  "key": "devtb_a1b2c3d4e5f6g7h8i9j0...",
  "name": "Production API",
  "tier": "premium",
  "permissions": ["read", "write"],
  "created_at": "2025-01-17 10:30:00"
}
```

---

### 9. Revoke API Key

Revoke an existing API key.

**Endpoint:** `DELETE /wp-json/devtb/v2/api-keys/{key}`
**Authentication:** Required (Admin)

**Request:**
```bash
curl -X DELETE https://yoursite.com/wp-json/devtb/v2/api-keys/devtb_a1b2c3d4... \
  -u username:password
```

**Response:**
```json
{
  "success": true,
  "message": "API key revoked successfully"
}
```

---

## Webhooks

Receive real-time notifications when jobs complete.

### Setup

Configure your webhook URL in WordPress settings:

```php
update_option('devtb_webhook_url', 'https://yoursite.com/webhook-endpoint');
```

### Webhook Events

Currently supported event:
- `job.completed` - Triggered when a batch translation job completes

### Webhook Payload

**Headers:**
```
Content-Type: application/json
X-DEVTB-Event: job.completed
X-DEVTB-Signature: sha256=abc123...
```

**Payload:**
```json
{
  "event": "job.completed",
  "job_id": "devtb_1705492800_abc123",
  "status": "completed",
  "source": "bootstrap",
  "total": 3,
  "successful": 3,
  "failed": 0,
  "elapsed_time": 2.45,
  "completed_at": "2025-01-17 10:30:30",
  "site_url": "https://yoursite.com",
  "timestamp": "2025-01-17 10:30:30"
}
```

### Signature Verification

Verify webhook authenticity using HMAC-SHA256:

```php
$secret = get_option('devtb_webhook_secret');
$payload_json = file_get_contents('php://input');
$signature = hash_hmac('sha256', $payload_json, $secret);

$expected_signature = $_SERVER['HTTP_X_DEVTB_SIGNATURE'];
if (!hash_equals('sha256=' . $signature, $expected_signature)) {
    // Invalid signature
    http_response_code(403);
    exit;
}
```

### Retry Logic

Failed webhooks are automatically retried with exponential backoff:
- **Retry 1:** After 1 minute
- **Retry 2:** After 2 minutes
- **Retry 3:** After 4 minutes
- **Max Retries:** 3

---

## Error Handling

### Error Response Format

```json
{
  "code": "error_code",
  "message": "Human-readable error message",
  "data": {
    "status": 400
  }
}
```

### Common Error Codes

| Code | HTTP Status | Description |
|------|-------------|-------------|
| `devtb_auth_required` | 401 | Authentication required |
| `devtb_auth_invalid_key` | 401 | Invalid or expired API key |
| `devtb_forbidden` | 403 | Insufficient permissions |
| `devtb_rate_limit_exceeded` | 429 | Rate limit exceeded |
| `translation_failed` | 400 | Translation failed |
| `invalid_targets` | 400 | Invalid target frameworks |
| `job_not_found` | 404 | Job ID not found |
| `validation_error` | 500 | Content validation error |

---

## Code Examples

### JavaScript (Node.js)

```javascript
const axios = require('axios');

const API_KEY = 'devtb_your_api_key';
const BASE_URL = 'https://yoursite.com/wp-json/devtb/v2';

async function translateContent() {
  try {
    const response = await axios.post(`${BASE_URL}/translate`, {
      source: 'bootstrap',
      target: 'elementor',
      content: '<div class="container">...</div>'
    }, {
      headers: {
        'X-API-Key': API_KEY,
        'Content-Type': 'application/json'
      }
    });

    console.log('Translation result:', response.data.result);
  } catch (error) {
    console.error('Error:', error.response.data);
  }
}

translateContent();
```

### Python

```python
import requests

API_KEY = 'devtb_your_api_key'
BASE_URL = 'https://yoursite.com/wp-json/devtb/v2'

def translate_content():
    headers = {
        'X-API-Key': API_KEY,
        'Content-Type': 'application/json'
    }

    data = {
        'source': 'bootstrap',
        'target': 'elementor',
        'content': '<div class="container">...</div>'
    }

    response = requests.post(
        f'{BASE_URL}/translate',
        json=data,
        headers=headers
    )

    if response.status_code == 200:
        result = response.json()
        print('Translation:', result['result'])
    else:
        print('Error:', response.json())

translate_content()
```

### PHP

```php
<?php
$api_key = 'devtb_your_api_key';
$base_url = 'https://yoursite.com/wp-json/devtb/v2';

$data = [
    'source' => 'bootstrap',
    'target' => 'elementor',
    'content' => '<div class="container">...</div>'
];

$response = wp_remote_post($base_url . '/translate', [
    'headers' => [
        'X-API-Key' => $api_key,
        'Content-Type' => 'application/json'
    ],
    'body' => json_encode($data)
]);

if (!is_wp_error($response)) {
    $body = json_decode(wp_remote_retrieve_body($response), true);
    echo $body['result'];
} else {
    echo 'Error: ' . $response->get_error_message();
}
```

### cURL

```bash
# Single Translation
curl -X POST https://yoursite.com/wp-json/devtb/v2/translate \
  -H "X-API-Key: devtb_your_key" \
  -H "Content-Type: application/json" \
  -d '{
    "source": "bootstrap",
    "target": "elementor",
    "content": "<div class=\"container\">...</div>"
  }'

# Batch Translation (Async)
curl -X POST https://yoursite.com/wp-json/devtb/v2/batch-translate \
  -H "X-API-Key: devtb_your_key" \
  -H "Content-Type: application/json" \
  -d '{
    "source": "gutenberg",
    "targets": ["elementor", "divi", "bricks"],
    "content": "<!-- wp:paragraph -->...",
    "async": true
  }'

# Check Job Status
curl https://yoursite.com/wp-json/devtb/v2/job/devtb_abc123 \
  -H "X-API-Key: devtb_your_key"

# Validate Content
curl -X POST https://yoursite.com/wp-json/devtb/v2/validate \
  -H "X-API-Key: devtb_your_key" \
  -H "Content-Type: application/json" \
  -d '{
    "framework": "elementor",
    "content": "[{\"id\":\"abc\"}]"
  }'
```

---

## Support

For API support and questions:
- **GitHub Issues:** https://github.com/coryhubbell/development-translation-bridge/issues
- **Documentation:** https://github.com/coryhubbell/development-translation-bridge

---

## Changelog

### Version 2.0 (January 2025)
- Initial release of REST API v2
- Support for 10 frameworks (90 translation pairs)
- API key authentication
- Rate limiting with 4 tiers
- Webhook notifications with retry logic
- Async job processing
- Batch translation support
- Content validation

---

**Last Updated:** January 17, 2025
**API Version:** 2.0
**DevelopmentTranslation Bridge:** v3.2.0
