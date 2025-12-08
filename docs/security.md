# Security Best Practices

## Overview

PMPro Magic Levels includes basic security features, but for production sites we **strongly recommend** implementing additional security measures at the infrastructure level.

## Rate Limiting

### Why External Rate Limiting?

Implementing rate limiting at your CDN or proxy level provides:

- ✅ **Better Performance** - Blocks requests before they hit WordPress
- ✅ **Saves Resources** - Reduces server load
- ✅ **Professional Protection** - DDoS protection, bot detection, etc.
- ✅ **More Flexible** - Configure different limits per endpoint
- ✅ **Industry Standard** - Used by Stripe, Twilio, and other major APIs

### Built-in Rate Limiting

The plugin includes basic token-based rate limiting:
- **Default:** 100 requests per hour (site-wide, single Bearer token)
- **Purpose:** Basic protection for small sites
- **Limitation:** Runs in WordPress (after request reaches server)
- **Note:** Currently supports one Bearer token per site

#### Disable Built-in Rate Limiting

If you're using external rate limiting, disable the built-in version:

```php
// Add to functions.php
add_filter('pmpro_magic_levels_enable_rate_limit', '__return_false');
```

#### Adjust Built-in Limits

```php
// Add to functions.php
add_filter('pmpro_magic_levels_rate_limit', function() {
    return array(
        'max_requests' => 500,  // 500 requests
        'time_window'  => 3600, // Per hour
    );
});
```

---

## Cloudflare Rate Limiting (Recommended)

### Free Plan

1. Go to **Security > WAF**
2. Click **Create rule**
3. Configure:
   - **Rule name:** PMPro Magic Levels Rate Limit
   - **Field:** URI Path
   - **Operator:** equals
   - **Value:** `/wp-json/pmpro-magic-levels/v1/process`
   - **Then:** Rate Limit
   - **Requests:** 100 per minute
   - **Duration:** 1 hour
   - **Action:** Block

### Pro/Business/Enterprise Plan

More advanced options available:

```
(http.request.uri.path eq "/wp-json/pmpro-magic-levels/v1/process")
```

**Rate Limiting:**
- 100 requests per minute per IP
- Block for 1 hour on violation
- Optional: Add CAPTCHA challenge instead of block

### Cloudflare Workers (Advanced)

For more complex logic:

```javascript
addEventListener('fetch', event => {
  event.respondWith(handleRequest(event.request))
})

async function handleRequest(request) {
  const url = new URL(request.url)
  
  // Only apply to webhook endpoint
  if (url.pathname === '/wp-json/pmpro-magic-levels/v1/process') {
    // Check Authorization header
    const auth = request.headers.get('Authorization')
    if (!auth || !auth.startsWith('Bearer ')) {
      return new Response('Unauthorized', { status: 401 })
    }
    
    // Add custom rate limiting logic here
  }
  
  return fetch(request)
}
```

---

## BunnyCDN Rate Limiting

### Via Edge Rules

1. Go to your Pull Zone
2. Navigate to **Edge Rules**
3. Add new rule:

```
If Request URL contains /wp-json/pmpro-magic-levels/v1/process
Then Rate Limit: 100 requests per minute
Action: Return 429 status code
```

### Via Shield (DDoS Protection)

1. Enable **Bunny Shield**
2. Configure rate limiting in Shield settings
3. Set custom rules for API endpoints

---

## Nginx Rate Limiting

Add to your Nginx configuration:

```nginx
# Define rate limit zone (10MB can track ~160,000 IPs)
limit_req_zone $binary_remote_addr zone=webhook:10m rate=100r/m;

# Apply to webhook endpoint
location /wp-json/pmpro-magic-levels/v1/process {
    # Allow burst of 20 requests, then enforce rate
    limit_req zone=webhook burst=20 nodelay;
    
    # Return 429 on rate limit
    limit_req_status 429;
    
    # Pass to WordPress
    try_files $uri $uri/ /index.php?$args;
}
```

**Advanced: Rate limit by Bearer token instead of IP:**

```nginx
# Extract Bearer token from Authorization header
map $http_authorization $bearer_token {
    ~^Bearer\s+(.+)$ $1;
    default "anonymous";
}

# Rate limit by token
limit_req_zone $bearer_token zone=webhook_token:10m rate=100r/m;

location /wp-json/pmpro-magic-levels/v1/process {
    limit_req zone=webhook_token burst=20 nodelay;
    limit_req_status 429;
    try_files $uri $uri/ /index.php?$args;
}
```

---

## Apache Rate Limiting

### Using mod_ratelimit

```apache
<Location "/wp-json/pmpro-magic-levels/v1/process">
    # Limit to 400 KB/s (adjust based on your needs)
    SetOutputFilter RATE_LIMIT
    SetEnv rate-limit 400
</Location>
```

### Using mod_evasive

Install and configure mod_evasive:

```apache
<IfModule mod_evasive20.c>
    DOSHashTableSize 3097
    DOSPageCount 10
    DOSSiteCount 100
    DOSPageInterval 1
    DOSSiteInterval 1
    DOSBlockingPeriod 3600
    
    # Whitelist your own IP
    DOSWhitelist 127.0.0.1
</IfModule>
```

---

## AWS API Gateway

If hosting on AWS, use API Gateway in front of your WordPress site:

1. Create REST API in API Gateway
2. Create resource for `/process`
3. Configure **Usage Plans**:
   - Rate: 100 requests per second
   - Burst: 200 requests
   - Quota: 10,000 requests per day
4. Create API Key for each integration
5. Associate API Key with Usage Plan

---

## Fail2Ban (Self-Hosted)

Monitor WordPress logs and ban abusive IPs:

### Create filter

`/etc/fail2ban/filter.d/pmpro-magic-levels.conf`:

```ini
[Definition]
failregex = ^<HOST> .* "POST /wp-json/pmpro-magic-levels/v1/process HTTP.*" 429
            ^<HOST> .* "POST /wp-json/pmpro-magic-levels/v1/process HTTP.*" 403
ignoreregex =
```

### Create jail

`/etc/fail2ban/jail.d/pmpro-magic-levels.conf`:

```ini
[pmpro-magic-levels]
enabled = true
port = http,https
filter = pmpro-magic-levels
logpath = /var/log/nginx/access.log
maxretry = 10
findtime = 600
bantime = 3600
```

Restart Fail2Ban:
```bash
sudo systemctl restart fail2ban
```

---

## IP Whitelisting

For internal integrations, whitelist specific IPs:

### Cloudflare

Create firewall rule:
```
(http.request.uri.path eq "/wp-json/pmpro-magic-levels/v1/process" and ip.src ne YOUR_IP)
Then: Block
```

### Nginx

```nginx
location /wp-json/pmpro-magic-levels/v1/process {
    allow YOUR_IP;
    deny all;
    
    try_files $uri $uri/ /index.php?$args;
}
```

### Apache

```apache
<Location "/wp-json/pmpro-magic-levels/v1/process">
    Require ip YOUR_IP
</Location>
```

### WordPress Plugin

```php
add_filter('rest_pre_dispatch', function($result, $server, $request) {
    if ($request->get_route() === '/pmpro-magic-levels/v1/process') {
        $allowed_ips = array('1.2.3.4', '5.6.7.8');
        $client_ip = $_SERVER['REMOTE_ADDR'];
        
        if (!in_array($client_ip, $allowed_ips)) {
            return new WP_Error(
                'forbidden',
                'Access denied',
                array('status' => 403)
            );
        }
    }
    return $result;
}, 10, 3);
```

---

## Webhook Services Rate Limiting

### Zapier

Zapier has built-in rate limiting:
- Free: 100 tasks/month
- Starter: 750 tasks/month
- Professional: 2,000+ tasks/month

No additional configuration needed.

### n8n (Self-Hosted)

Configure rate limiting in n8n workflow:

1. Add **Function** node before webhook call
2. Implement rate limiting logic:

```javascript
// Simple rate limiter
const key = 'pmpro_webhook_calls';
const limit = 100;
const window = 3600000; // 1 hour in ms

const now = Date.now();
const calls = $node["Webhook"].json.calls || [];

// Remove old calls outside window
const recentCalls = calls.filter(time => now - time < window);

if (recentCalls.length >= limit) {
  throw new Error('Rate limit exceeded');
}

recentCalls.push(now);

return {
  json: {
    calls: recentCalls
  }
};
```

### Make.com (Integromat)

Make.com has built-in rate limiting per scenario. No configuration needed.

---

## Monitoring & Alerts

### Cloudflare Analytics

Monitor webhook traffic:
1. Go to **Analytics & Logs**
2. Filter by `/wp-json/pmpro-magic-levels/v1/process`
3. Set up alerts for unusual traffic

### WordPress Logging

Log all webhook requests:

```php
add_action('rest_pre_dispatch', function($result, $server, $request) {
    if ($request->get_route() === '/pmpro-magic-levels/v1/process') {
        error_log(sprintf(
            'PMPro Magic Levels webhook: IP=%s, Token=%s, Time=%s',
            $_SERVER['REMOTE_ADDR'],
            substr($request->get_header('authorization'), 0, 20) . '...',
            current_time('mysql')
        ));
    }
    return $result;
}, 10, 3);
```

### New Relic / DataDog

Monitor API endpoint performance and set alerts for:
- High error rates (429, 403, 500)
- Slow response times
- Unusual traffic patterns

---

## Security Checklist

- [ ] Implement rate limiting at CDN/proxy level
- [ ] Use strong Bearer tokens (64+ characters)
- [ ] Rotate tokens periodically
- [ ] Monitor webhook logs for suspicious activity
- [ ] Set up alerts for rate limit violations
- [ ] Keep WordPress and plugins updated
- [ ] Use HTTPS only (enforce SSL)
- [ ] Consider IP whitelisting for internal integrations
- [ ] Implement request logging
- [ ] Regular security audits

---

## Additional Resources

- [Cloudflare Rate Limiting](https://developers.cloudflare.com/waf/rate-limiting-rules/)
- [Nginx Rate Limiting](https://www.nginx.com/blog/rate-limiting-nginx/)
- [Apache mod_ratelimit](https://httpd.apache.org/docs/2.4/mod/mod_ratelimit.html)
- [AWS API Gateway Rate Limiting](https://docs.aws.amazon.com/apigateway/latest/developerguide/api-gateway-request-throttling.html)
- [OWASP API Security](https://owasp.org/www-project-api-security/)

---

## Current Limitations

### Single Bearer Token

The plugin currently supports **one Bearer token per site**. All integrations (Zapier, n8n, forms, etc.) must use the same token.

**Implications:**
- Rate limiting is site-wide (100 requests/hour total, not per integration)
- Cannot revoke access for individual integrations
- Cannot set different rate limits per integration

**Workarounds:**
- Use external rate limiting (Cloudflare, etc.) for per-integration limits
- Use IP whitelisting to restrict access
- Implement custom authentication via filters

### Future Enhancements

Potential improvements for future versions:

1. **Multiple API Keys**
   - Create separate tokens for each integration
   - Per-key rate limits
   - Individual key revocation

2. **Key Management**
   - Automatic token rotation
   - Expiration dates
   - Usage analytics per key

3. **Advanced Authentication**
   - OAuth 2.0 support
   - JWT tokens
   - Webhook signatures (HMAC)

**Note:** These features are not currently implemented. If you need them, consider implementing at the infrastructure level (API Gateway, etc.).

---

## Support

For security issues, please report privately to: security@yoursite.com

For general questions, use GitHub Issues.
