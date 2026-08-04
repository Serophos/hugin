# OpenID Connect / Keycloak setup

Hugin can use an existing OpenID Connect provider in addition to its local database accounts. The implementation is tested against Keycloak-style discovery and uses the authorization-code flow with PKCE.

## Database and Hugin configuration

1. For an existing installation, follow the automated CLI procedure in [Database upgrades](database-upgrades.md). New installations already contain the required schema and migration ledger in `database.sql`.
2. Set `app.encryption_key` in `config/config.php` to a unique random value containing at least 32 characters. Generate one with:

   ```shell
   php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
   ```

3. Sign in with a local administrator and open **Settings → OpenID Connect**.
4. Enter the issuer URL, client ID, client secret, claim names, and the administrator/editor group leaf names.
5. Save the form, use **Test discovery**, and then enable automatic sign-in.

The client secret is encrypted with authenticated AES-256-GCM before it is stored in the `app_settings` table. Keep `app.encryption_key` outside database backups and restrict access to it. Losing or changing the key makes stored secrets unreadable.

Existing plaintext client secrets are intentionally unsupported. After deploying this version, enter and save the client secret again.

If automatic sign-in is enabled, `/admin/login` redirects to the provider. Local emergency accounts remain available at `/admin/login/local`.

## Keycloak client

Create a confidential OpenID Connect client with:

- Standard/authorization-code flow enabled.
- Client authentication enabled.
- Valid redirect URI set exactly to the callback URL displayed in Hugin, normally `https://hugin.example.org/admin/oidc/callback`.
- Web origin set to the Hugin origin where required by local policy.
- `openid profile` scopes (plus any deployment-specific scope configured in Hugin).

Configure protocol mappers so the ID token or UserInfo response exposes:

- `preferred_username`
- `name`
- `given_name`
- `family_name`
- `department`
- `title`
- `picture` containing an HTTP(S) URL
- `groups` as an array of strings

All claim paths can be changed in Hugin. Dot-separated paths address nested objects. Group authorization is case-sensitive and compares the final path segment: `/organisation/hugin-admin` matches a configured group name of `hugin-admin`.

OIDC identities are linked only by issuer and `sub`; they are never linked to a local account by username. A username collision denies login and is written to the PHP error log. OIDC user profiles and roles are synchronized at each successful login, while an administrator can locally deactivate an OIDC account.

Hugin logout clears only the Hugin session. It does not terminate the user’s Keycloak single-sign-on session.
