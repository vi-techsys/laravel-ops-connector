# Laravel Ops Connector

A lightweight, secure connector package for Laravel applications to connect to Laravel Ops central management platform.

## Overview

The connector exposes a secure API that allows Laravel Ops to:
- Monitor application health
- Discover capabilities
- Execute allowlisted operations (cache clear, migrations, etc.)

## Architecture

```
Laravel Ops (Central) 
    ↓ HTTPS + HMAC Signed Request
Connector (This Package)
    ↓ Validates Signature
Laravel Application (Local)
    ↓ Executes Operation
    ↓ Returns Result
Laravel Ops (Displays Result)
```

## Security Features

- **HMAC-SHA256 Request Signing** - All requests are cryptographically signed
- **Timestamp Validation** - Requests expire after 5 minutes
- **Replay Protection** - Nonce-based protection against replay attacks
- **Allowlisted Operations** - Only explicitly permitted operations can execute
- **No Shell Execution** - Uses Laravel's Artisan API, never shell_exec

## Installation

Install the package through Composer:

```bash
composer require laravel-ops/connector
```

The package is available through Packagist once its public source repository has
been submitted there. For development against a local checkout, Composer can
instead use a path repository in your application's `composer.json`.

### Publish Configuration

```bash
php artisan vendor:publish --tag=connector-config
```

### Configure Credentials

Add to your `.env` file:

```env
CONNECTOR_API_KEY=your_api_key_here
CONNECTOR_API_SECRET=your_api_secret_here
```

These credentials are provided by Laravel Ops when you register your application.

### Register Service Provider

For Laravel 11+, the service provider is auto-discovered. For older versions, add to `config/app.php`:

```php
'providers' => [
    // ...
    LaravelOps\Connector\ConnectorServiceProvider::class,
],
```

## Enrollment Flow

### Option A: Automatic Enrollment (Recommended)

1. In Laravel Ops, generate an enrollment token for your application
2. Run the enrollment command:

```bash
php artisan connector:enroll --token=enr_xxxxx --central-url=https://laravel-ops.test
```

This will:
- Validate the token with Laravel Ops
- Receive API credentials
- Update your `.env` file automatically

### Option B: Manual Configuration

1. Register your application in Laravel Ops
2. Copy the API credentials from the application detail page
3. Add them to your `.env` file:

```env
CONNECTOR_API_KEY=lops_xxxxx
CONNECTOR_API_SECRET=xxxxx
```

## API Endpoints

### Health Check
```
GET /api/connector/v1/health
```

Returns application health status including:
- Application info (name, URL, Laravel version, PHP version)
- Database connection status
- Cache functionality status
- Connector version

### Capability Discovery
```
GET /api/connector/v1/capabilities
```

Returns supported operations and features:
- Connector version
- Laravel version
- List of allowed operations
- Available features (queue, scheduler, etc.)

### Enrollment
```
POST /api/connector/v1/enroll
```

Enrolls the connector with Laravel Ops using a short-lived enrollment token.

## Request Signing

All requests (except enrollment) must include these headers:

```
X-Connector-Key: your_api_key
X-Connector-Timestamp: 2026-10-16T10:00:00Z
X-Connector-Nonce: random_unique_string
X-Connector-Signature: calculated_hmac_signature
```

### Signature Calculation

```php
$method = 'GET';
$path = '/api/connector/v1/health';
$timestamp = '2026-10-16T10:00:00Z';
$nonce = 'random_string';
$bodyHash = ''; // SHA256 hash of request body (empty for GET)

$stringToSign = "$method|$path|$timestamp|$nonce|$bodyHash";
$signature = hash_hmac('sha256', $stringToSign, $apiSecret);
```

## Configuration

### Allowed Operations

Edit `config/connector.php` to customize allowed operations:

```php
'allowed_operations' => [
    'app:info',
    'cache:clear',
    'config:clear',
    'route:clear',
    'view:clear',
    'event:clear',
    'optimize:clear',
    'migration:status',
],
```

### Security Settings

```php
// Timestamp tolerance (seconds)
'timestamp_tolerance' => 300, // 5 minutes

// Nonce cache duration (seconds)
'nonce_cache_duration' => 300, // 5 minutes

// Enable/disable operations
'enable_operations' => true,
```

## Testing the Connection

From Laravel Ops, you can test the connection:

1. Go to your application detail page
2. Click "Test Connection"
3. If successful, the status will change to "Connected"

Or via CLI:

```bash
# From Laravel Ops
php artisan connector:test-connection {application_id}
```

## Troubleshooting

### "Invalid API key" error
- Check that `CONNECTOR_API_KEY` in `.env` matches the key in Laravel Ops
- Ensure there are no extra spaces or line breaks

### "Request timestamp is too old" error
- Check that your server's clock is synchronized (NTP)
- Increase `timestamp_tolerance` in `config/connector.php` if needed

### "Request nonce has already been used" error
- This indicates a possible replay attack
- Each request must have a unique nonce
- Check your HTTP client isn't retrying requests

### "Invalid request signature" error
- Verify `CONNECTOR_API_SECRET` is correct
- Check signature calculation matches the expected format
- Ensure body hash is included for POST requests

### Connection timeout
- Check firewall allows HTTPS connections
- Verify the base URL in Laravel Ops is correct
- Check SSL certificate validity

## Security Best Practices

1. **Keep secrets secure** - Never commit `.env` to version control
2. **Use HTTPS** - Always use HTTPS in production
3. **Rotate credentials** - Regenerate API keys periodically
4. **Monitor logs** - Watch for authentication failures
5. **Limit operations** - Only enable operations you need
6. **Revoke when needed** - Use the revoke feature if a connector is compromised

## Requirements

- PHP 8.1 or higher
- Laravel 10.x or 11.x
- HTTPS connection to Laravel Ops
- Valid API credentials

## Version History

- **1.0.0** - Initial release
  - Health check endpoint
  - Capability discovery
  - Request signing with HMAC
  - Enrollment flow
  - Replay protection

## License

MIT License

## Support

For issues and questions, please refer to the Laravel Ops documentation or open an issue in the main repository.
