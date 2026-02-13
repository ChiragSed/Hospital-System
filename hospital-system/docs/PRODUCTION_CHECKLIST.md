# Production Checklist

## Security
- Change all demo credentials and remove demo users.
- Disable or remove `database/setup_demo.php` from production.
- Enforce HTTPS and secure cookies.
- Add CSRF tokens for all POST actions.
- Add rate limits on all login endpoints.
- Restrict upload types and maximum sizes.
- Store uploaded files outside public web root if possible.

## Database
- Create least-privilege DB user (do not use `root`).
- Set strong DB password and keep it outside source control.
- Run schema migrations in deployment pipeline, not at runtime.
- Enable regular backups and test restore process.

## App Configuration
- Use environment variables for all secrets and host config.
- Turn off PHP error display in production.
- Enable server-side logging with rotation.

## Infrastructure
- Put app behind reverse proxy with TLS.
- Configure firewall to allow only required ports.
- Monitor uptime and basic error rate.

## Operations
- Keep one staging environment for safe testing.
- Track releases with changelog/version tags.
- Run periodic dependency and security updates.
