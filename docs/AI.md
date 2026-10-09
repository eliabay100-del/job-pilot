# AI

Planned scope: Provider abstraction (Anthropic, OpenAI, Gemini), prompt management, usage and cost tracking, AI credits, evaluation. Spec sections 13-21, 35, 52.

Phase 5 has started with the CV parsing foundation.

## Current implementation

- `CvParser` is a provider contract under `app/Domain/AI/Contracts`.
- `LocalCvParser` extracts only verbatim, labeled contact fields from readable CV text.
- `POST /api/v1/cv/{cv}/parse` stores structured data with `needs_confirmation` status.
- `POST /api/v1/cv/{cv}/confirm` accepts an explicit field allow-list and maps only name, location,
	and summary into the candidate profile.
- `POST /api/v1/cv/{cv}/tailor` creates a source-linked tailored version for a published job by
	reordering existing profile skills and experience; it does not generate new claims.
- `TextGenerationProvider` is the provider boundary for generated text, with a deterministic local
	cover-letter template available as `LocalTextGenerationProvider` until a hosted adapter is configured.
- `GeneratedText` carries provider, model, prompt-version, and token metadata so generated features can
	record auditable usage in the existing `ai_usage` table.
- `POST /api/v1/cv/{cv}/cover-letter` stores a reviewable draft tied to a confirmed source CV and
	published job; the draft is never submitted automatically.
- Parsing never maps data into the candidate profile automatically; account email and phone are never
	changed by CV confirmation.
- The local parser is intentionally conservative and is not a replacement for a hosted AI provider.

## Next Phase 5 slices

Add hosted provider adapters and usage/credit accounting, then build career assistant conversations
and interview preparation on top of the same provider boundary.
