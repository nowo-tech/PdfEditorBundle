# Engram

Short facts for AI assistants and maintainers.

- Package: `nowo-tech/pdf-editor-bundle`
- Bundle class: `Nowo\PdfEditorBundle\PdfEditorBundle`
- Config alias: `nowo_whatsapp_business`
- Main services: `WhatsappClientInterface`, `WhatsappClientRegistryInterface`
- Profiles: `default_profile` + `profiles.*` (phone_number_id, access_token, timeouts, webhook secrets)
- Transports: `cloud` (Graph API via Symfony HttpClient) and `mock` (`ResetInterface`)
- Webhooks: `/whatsapp/webhook` + `/whatsapp/webhook/{profile}`
- FrankenPHP: mock transport resets; HTTP timeouts on every Graph call
- No Twig templates in the bundle / no translation domains / no frontend assets
- Host apps own persistence; use events + `sendRaw()` to integrate
