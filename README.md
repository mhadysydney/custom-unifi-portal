# Unifi Custom Portal

## Project Overview

This repository contains the core files for a custom Unifi portal implementation. It includes:
- 📁 PHP source files for portal logic
- 📁 Public-facing web assets
- 📁 Vendor dependencies for API integration
- 🔐 Configuration files for site settings and secrets

## Key Features
- Custom Unifi API integration
- Site management and device monitoring
- Secure authentication system
- Responsive web interface

## Project Structure

```
src/
  - CookieManager.php
  - Database.php
  - UnifiHelper.php

public/
  - assets/
  - auth.php
  - index.php
  - success.php

config/
  - secrets.php
  - sites.php

vendor/
  - art-of-wifi/unifi-api-client/

```

## Getting Started

1. Install dependencies:
```bash
composer install
```

2. Configure settings in `config/secrets.php` and `config/sites.php`

3. Run the portal:
```bash
php -S localhost:8000 -t public
```

## Documentation
- [API Reference](docs/api.md)
- [Development Guide](docs/development.md)

## Contributing
See [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

## License
MIT License - see [LICENSE](LICENSE)
