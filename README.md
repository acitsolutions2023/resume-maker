# Simple Resume Maker — CodeIgniter 4

A no-account resume builder based on the supplied two-page resume template. Users choose A4 or Philippine Long Bond (8.5 × 13 in), fill editable fields, add repeatable items (max 5), save, preview, download PDF, and later retrieve using Full Name + Date of Birth + Email.

## Requirements
- PHP 8.2+ (works with PHP 8.4/8.5)
- CodeIgniter 4.7+
- MySQL/MariaDB
- Composer

## Install
1. Copy `app/` and `public/` into a clean/current CI4 project.
2. Add the routes from `ROUTES.txt` to `app/Config/Routes.php`.
3. Install PDF dependency: `composer require dompdf/dompdf`
4. Configure database in `.env`.
5. Run: `php spark migrate`
6. Ensure `writable/uploads/resumes` is writable by PHP.
7. Start locally: `php spark serve`

## Important production settings
- Keep CSRF enabled. If your CI4 CSRF configuration rejects AJAX multipart requests, include the CSRF hidden input in the form and refresh the token after saves.
- Add throttling/rate limiting to POST `/resume/retrieve` to reduce guessing attempts.
- Use HTTPS because the application stores DOB/email and uploaded photos.
- Consider an automatic retention policy for old resumes/photos.

## Behavior
- A4: 210 × 297 mm.
- Long: 8.5 × 13 inches (not US Legal 8.5 × 14).
- Education, Achievements, and Character References are capped at 5 server-side and in the UI.
- Resume data is stored as JSON for a deliberately small schema.
- Retrieval lookup uses normalized SHA-256 keys for name/email plus DOB; the original values remain in the saved resume so they can be rendered/edited.
- Draft changes are cached in browser localStorage while typing.

## Template mapping
Red text in the source template was treated as editable personal data. Blue text was treated as repeatable content. The generated PDF itself uses normal black text.
